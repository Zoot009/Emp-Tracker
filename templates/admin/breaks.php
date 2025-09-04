<?php
/**
 * Break Management Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

$date_from = isset($_GET['date_from']) ? $security->sanitize_text($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $security->sanitize_text($_GET['date_to']) : date('Y-m-d');
$employee_filter = isset($_GET['employee_id']) ? intval($_GET['employee_id']) : 0;

$employees = $database->get_all_employees();

global $wpdb;

// Get active breaks
$active_breaks = $wpdb->get_results("
    SELECT b.*, e.name as employee_name, e.employee_code
    FROM {$wpdb->prefix}ett_breaks b
    LEFT JOIN {$wpdb->prefix}ett_employees e ON b.employee_id = e.id
    WHERE b.is_active = 1
    ORDER BY b.break_in_time DESC
");

// Build query for all breaks with filters
$query = "SELECT b.*, e.name as employee_name, e.employee_code
          FROM {$wpdb->prefix}ett_breaks b
          LEFT JOIN {$wpdb->prefix}ett_employees e ON b.employee_id = e.id
          WHERE b.break_date >= %s AND b.break_date <= %s";

$query_params = array($date_from, $date_to);

if ($employee_filter > 0) {
    $query .= " AND b.employee_id = %d";
    $query_params[] = $employee_filter;
}

$query .= " ORDER BY b.break_date DESC, b.break_in_time DESC";

$all_breaks = $wpdb->get_results($wpdb->prepare($query, ...$query_params));
?>

<div class="wrap">
    <h1>Break Management</h1>
    
    <?php if (!empty($active_breaks)): ?>
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title" style="color: #ffc107;">Currently on Break (<?php echo count($active_breaks); ?>)</h2>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Break Started</th>
                    <th>Duration</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_breaks as $break): 
                    $break_in = new DateTime($break->break_in_time);
                    $now = new DateTime();
                    $interval = $now->diff($break_in);
                    $minutes = ($interval->h * 60) + $interval->i;
                ?>
                <tr>
                    <td>
                        <strong><?php echo $security->escape_html($break->employee_name); ?></strong><br>
                        <small><?php echo $security->escape_html($break->employee_code); ?></small>
                    </td>
                    <td><?php echo $break_in->format('h:i A'); ?></td>
                    <td class="live-duration" data-start="<?php echo $break->break_in_time; ?>">
                        <strong><?php echo $minutes; ?> minutes</strong>
                        <?php if ($minutes > 20): ?>
                            <span style="color:red;"> ⚠️ Exceeded!</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($minutes > 20): ?>
                            <span class="ett-badge ett-badge-danger">Over time</span>
                        <?php else: ?>
                            <span class="ett-badge ett-badge-warning">Within limit</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($minutes > 20 && !$break->warning_sent): ?>
                        <button class="button button-small ett-send-break-warning" 
                                data-employee-id="<?php echo $break->employee_id; ?>"
                                data-break-id="<?php echo $break->id; ?>">
                            Send Warning
                        </button>
                        <?php elseif ($break->warning_sent): ?>
                            <span style="color: #28a745;">Warning Sent</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Break History</h2>
        </div>
        
        <form method="get" class="ett-filter-form">
            <input type="hidden" name="page" value="ett-breaks" />
            <label>From: 
                <input type="date" name="date_from" value="<?php echo $security->escape_attr($date_from); ?>" />
            </label>
            <label>To: 
                <input type="date" name="date_to" value="<?php echo $security->escape_attr($date_to); ?>" />
            </label>
            <label>Employee: 
                <select name="employee_id">
                    <option value="0">All Employees</option>
                    <?php foreach ($employees as $employee): ?>
                        <option value="<?php echo $employee->id; ?>" <?php selected($employee_filter, $employee->id); ?>>
                            <?php echo $security->escape_html($employee->name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <input type="submit" class="button button-primary" value="Filter" />
        </form>
        
        <?php if (!empty($all_breaks)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Employee</th>
                        <th>Break In</th>
                        <th>Break Out</th>
                        <th>Duration</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($all_breaks as $break): ?>
                    <tr>
                        <td><?php echo date('M j, Y', strtotime($break->break_date)); ?></td>
                        <td>
                            <strong><?php echo $security->escape_html($break->employee_name); ?></strong><br>
                            <small><?php echo $security->escape_html($break->employee_code); ?></small>
                        </td>
                        <td>
                            <?php 
                            if ($break->break_in_time) {
                                $break_in = new DateTime($break->break_in_time);
                                echo $break_in->format('h:i A');
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($break->break_out_time) {
                                $break_out = new DateTime($break->break_out_time);
                                echo $break_out->format('h:i A');
                            } else {
                                echo '<span class="ett-badge ett-badge-warning">Active</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($break->break_duration): ?>
                                <strong><?php echo $break->break_duration; ?> min</strong>
                            <?php else: ?>
                                <em>Active</em>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($break->is_active): ?>
                                <span class="ett-badge ett-badge-warning">On Break</span>
                            <?php elseif ($break->break_duration > 20): ?>
                                <span class="ett-badge ett-badge-danger">Exceeded</span>
                            <?php else: ?>
                                <span class="ett-badge ett-badge-success">Normal</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ett-alert ett-alert-info">
                <p>No break data found for the selected period.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Update live durations every minute
    function updateLiveDurations() {
        $('.live-duration').each(function() {
            var startTime = new Date($(this).data('start'));
            var now = new Date();
            var diffMinutes = Math.floor((now - startTime) / 1000 / 60);
            
            var html = '<strong>' + diffMinutes + ' minutes</strong>';
            if (diffMinutes > 20) {
                html += '<span style="color:red;"> ⚠️ Exceeded!</span>';
            }
            $(this).html(html);
        });
    }
    
    if ($('.live-duration').length > 0) {
        setInterval(updateLiveDurations, 60000); // Update every minute
    }
    
    $('.ett-send-break-warning').click(function() {
        var $btn = $(this);
        var employeeId = $btn.data('employee-id');
        var breakId = $btn.data('break-id');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ett_send_break_warning',
                employee_id: employeeId,
                break_id: breakId,
                nonce: '<?php echo wp_create_nonce('ett_send_break_warning'); ?>'
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('Sending...');
            },
            success: function(response) {
                if (response.success) {
                    $btn.text('Warning Sent').css('color', '#28a745').prop('disabled', true);
                } else {
                    alert('Failed to send warning: ' + (response.data || 'Unknown error'));
                    $btn.prop('disabled', false).text('Send Warning');
                }
            },
            error: function() {
                alert('Network error occurred');
                $btn.prop('disabled', false).text('Send Warning');
            }
        });
    });
});
</script>