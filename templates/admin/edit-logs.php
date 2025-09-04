<?php
/**
 * Edit Logs Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

$selected_date = isset($_GET['log_date']) ? $security->sanitize_text($_GET['log_date']) : date('Y-m-d');
$selected_employee = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

$employees = $database->get_all_employees();

global $wpdb;
?>

<div class="wrap">
    <h1>Edit Employee Logs</h1>
    
    <div class="ett-card">
        <form method="get" class="ett-filter-form">
            <input type="hidden" name="page" value="ett-edit-logs" />
            <label>Date: 
                <input type="date" name="log_date" value="<?php echo $security->escape_attr($selected_date); ?>" />
            </label>
            <label>Employee: 
                <select name="employee_id">
                    <option value="0">Select Employee</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee->id; ?>" <?php selected($selected_employee, $employee->id); ?>>
                            <?php echo $security->escape_html($employee->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <input type="submit" class="button button-primary" value="Load Logs" />
        </form>
    </div>
    
    <?php if ($selected_employee > 0): 
        $logs = $wpdb->get_results($wpdb->prepare("
            SELECT l.*, t.tag_name, t.time_minutes, e.name as employee_name
            FROM {$wpdb->prefix}ett_logs l
            LEFT JOIN {$wpdb->prefix}ett_tags t ON l.tag_id = t.id
            LEFT JOIN {$wpdb->prefix}ett_employees e ON l.employee_id = e.id
            WHERE l.employee_id = %d AND l.log_date = %s
            ORDER BY t.tag_name
        ", $selected_employee, $selected_date));
        
        $employee = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}ett_employees WHERE id = %d", $selected_employee));
        
        if (!empty($logs)): ?>
            <div class="ett-card">
                <div class="ett-card-header">
                    <h2 class="ett-card-title">
                        Edit Logs for <?php echo $security->escape_html($employee->name); ?> - 
                        <?php echo date('F j, Y', strtotime($selected_date)); ?>
                    </h2>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Tag</th>
                            <th>Time per Unit</th>
                            <th>Count</th>
                            <th>Total Minutes</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                        <tr id="log-row-<?php echo $log->id; ?>">
                            <td><strong><?php echo $security->escape_html($log->tag_name); ?></strong></td>
                            <td><?php echo $log->time_minutes; ?> min</td>
                            <td>
                                <input type="number" 
                                       id="count-<?php echo $log->id; ?>"
                                       value="<?php echo $log->count; ?>"
                                       min="0"
                                       max="999"
                                       style="width:80px;" />
                            </td>
                            <td id="total-<?php echo $log->id; ?>">
                                <strong><?php echo $log->total_minutes; ?> min</strong>
                            </td>
                            <td>
                                <button class="button button-primary button-small update-log-btn"
                                        data-log-id="<?php echo $log->id; ?>"
                                        data-time="<?php echo $log->time_minutes; ?>">
                                    Update
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3"><strong>Total:</strong></td>
                            <td><strong id="grand-total"><?php 
                                $total = array_sum(array_column($logs, 'total_minutes'));
                                echo ETT_Utils::minutes_to_hours_format($total);
                            ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="ett-alert ett-alert-warning" style="margin-top:20px;">
                    <strong>Warning:</strong> Changes made here will affect submitted data. 
                    Use this feature only for corrections.
                </div>
            </div>
        <?php else: ?>
            <div class="ett-card">
                <p>No logs found for the selected employee and date.</p>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class="ett-card">
            <p>Please select an employee to view and edit logs.</p>
        </div>
    <?php endif; ?>
</div>

<script>
jQuery(document).ready(function($) {
    function calculateGrandTotal() {
        var total = 0;
        $('.update-log-btn').each(function() {
            var logId = $(this).data('log-id');
            var timePerUnit = $(this).data('time');
            var count = parseInt($('#count-' + logId).val()) || 0;
            total += count * timePerUnit;
        });
        
        var hours = Math.floor(total / 60);
        var minutes = total % 60;
        $('#grand-total').text(hours + 'h ' + minutes + 'm');
    }
    
    $('.update-log-btn').click(function() {
        var $btn = $(this);
        var logId = $btn.data('log-id');
        var timePerUnit = $btn.data('time');
        var count = $('#count-' + logId).val();
        var totalMinutes = count * timePerUnit;
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ett_update_log',
                log_id: logId,
                count: count,
                nonce: '<?php echo wp_create_nonce('ett_update_log'); ?>'
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('Updating...');
            },
            success: function(response) {
                if (response.success) {
                    $('#total-' + logId).html('<strong>' + totalMinutes + ' min</strong>');
                    $btn.text('Updated!').css('background', '#28a745').css('color', 'white');
                    calculateGrandTotal();
                    setTimeout(function() {
                        $btn.text('Update').css('background', '').css('color', '');
                    }, 2000);
                } else {
                    alert('Failed to update log: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Network error occurred');
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });
    
    $('input[type="number"]').on('input', function() {
        var logId = $(this).attr('id').replace('count-', '');
        var timePerUnit = $('.update-log-btn[data-log-id="' + logId + '"]').data('time');
        var count = parseInt($(this).val()) || 0;
        var total = count * timePerUnit;
        $('#total-' + logId).html('<em>' + total + ' min</em>');
    });
});
</script>