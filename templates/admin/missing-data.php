<?php
/**
 * Missing Data Report Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

$date_from = isset($_GET['date_from']) ? $security->sanitize_text($_GET['date_from']) : date('Y-m-01');
$date_to = isset($_GET['date_to']) ? $security->sanitize_text($_GET['date_to']) : date('Y-m-d');

$employees = $database->get_all_employees();

global $wpdb;
?>

<div class="wrap">
    <h1>Missing Data Report</h1>
    
    <div class="ett-card">
        <form method="get" class="ett-filter-form">
            <input type="hidden" name="page" value="ett-missing-data" />
            <label>From: 
                <input type="date" name="date_from" value="<?php echo $security->escape_attr($date_from); ?>" />
            </label>
            <label>To: 
                <input type="date" name="date_to" value="<?php echo $security->escape_attr($date_to); ?>" />
            </label>
            <input type="submit" class="button button-primary" value="Generate Report" />
        </form>
    </div>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Missing Submissions Report</h2>
            <p>Period: <?php echo date('M j, Y', strtotime($date_from)); ?> to <?php echo date('M j, Y', strtotime($date_to)); ?></p>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Missing Dates</th>
                    <th>Total Missing Days</th>
                    <th>Completion Rate</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $total_employees = 0;
                $employees_with_missing = 0;
                
                foreach ($employees as $employee): 
                    $total_employees++;
                    
                    // Get submitted dates
                    $submitted_dates = $wpdb->get_col($wpdb->prepare("
                        SELECT submission_date 
                        FROM {$wpdb->prefix}ett_submission_status 
                        WHERE employee_id = %d 
                        AND submission_date >= %s 
                        AND submission_date <= %s
                        AND is_locked = 1
                    ", $employee->id, $date_from, $date_to));
                    
                    // Calculate working days in period
                    $working_days = ETT_Utils::get_date_range($date_from, $date_to);
                    $total_working_days = count($working_days);
                    
                    // Find missing dates
                    $missing_dates = array_diff($working_days, $submitted_dates);
                    $missing_count = count($missing_dates);
                    
                    if ($missing_count > 0) {
                        $employees_with_missing++;
                    }
                    
                    $completion_rate = $total_working_days > 0 ? 
                        round((($total_working_days - $missing_count) / $total_working_days) * 100, 1) : 100;
                ?>
                <tr>
                    <td>
                        <strong><?php echo $security->escape_html($employee->name); ?></strong><br>
                        <small><?php echo $security->escape_html($employee->employee_code); ?></small>
                    </td>
                    <td>
                        <?php if (!empty($missing_dates)): ?>
                            <div class="missing-dates-list">
                                <?php 
                                $display_dates = array_slice($missing_dates, 0, 5);
                                foreach ($display_dates as $date) {
                                    echo '<span class="ett-badge ett-badge-danger">' . date('M j', strtotime($date)) . '</span> ';
                                }
                                if (count($missing_dates) > 5) {
                                    echo '<span class="ett-badge ett-badge-warning">+' . (count($missing_dates) - 5) . ' more</span>';
                                }
                                ?>
                            </div>
                        <?php else: ?>
                            <span class="ett-badge ett-badge-success">None</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($missing_count > 0): ?>
                            <span class="ett-badge ett-badge-danger"><?php echo $missing_count; ?></span>
                        <?php else: ?>
                            <span class="ett-badge ett-badge-success">0</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <div class="completion-rate">
                            <span class="rate-text <?php echo $completion_rate >= 90 ? 'text-success' : ($completion_rate >= 70 ? 'text-warning' : 'text-danger'); ?>">
                                <?php echo $completion_rate; ?>%
                            </span>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $completion_rate; ?>%; background-color: <?php 
                                    echo $completion_rate >= 90 ? '#28a745' : ($completion_rate >= 70 ? '#ffc107' : '#dc3545'); 
                                ?>;"></div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <?php if (!empty($missing_dates)): ?>
                            <button class="button button-small send-warning-btn"
                                    data-employee-id="<?php echo $employee->id; ?>"
                                    data-employee-name="<?php echo $security->escape_attr($employee->name); ?>"
                                    data-missing-count="<?php echo $missing_count; ?>"
                                    data-missing-dates="<?php echo $security->escape_attr(implode(', ', array_slice($missing_dates, 0, 3))); ?>">
                                Send Warning
                            </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="report-summary" style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h3>Report Summary</h3>
            <div class="summary-stats">
                <div class="stat-item">
                    <strong>Total Employees:</strong> <?php echo $total_employees; ?>
                </div>
                <div class="stat-item">
                    <strong>Employees with Missing Data:</strong> <?php echo $employees_with_missing; ?>
                </div>
                <div class="stat-item">
                    <strong>Overall Compliance Rate:</strong> 
                    <?php 
                    $overall_rate = $total_employees > 0 ? 
                        round((($total_employees - $employees_with_missing) / $total_employees) * 100, 1) : 100;
                    echo $overall_rate; 
                    ?>%
                </div>
                <div class="stat-item">
                    <strong>Reporting Period:</strong> 
                    <?php echo count(ETT_Utils::get_date_range($date_from, $date_to)); ?> working days
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.missing-dates-list .ett-badge {
    margin: 2px;
    font-size: 11px;
}

.completion-rate {
    text-align: center;
}

.progress-bar {
    width: 100%;
    height: 8px;
    background: #e0e0e0;
    border-radius: 4px;
    margin-top: 5px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    transition: width 0.3s ease;
}

.text-success { color: #28a745; }
.text-warning { color: #ffc107; }
.text-danger { color: #dc3545; }

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.stat-item {
    padding: 10px;
    background: white;
    border-radius: 4px;
    border-left: 3px solid #007cba;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.send-warning-btn').click(function() {
        var $btn = $(this);
        var employeeId = $btn.data('employee-id');
        var employeeName = $btn.data('employee-name');
        var missingCount = $btn.data('missing-count');
        var missingDates = $btn.data('missing-dates');
        
        var message = 'Send warning to ' + employeeName + ' for ' + missingCount + ' missing submissions?';
        
        if (confirm(message)) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_send_missing_data_warning',
                    employee_id: employeeId,
                    missing_dates: missingDates,
                    nonce: '<?php echo wp_create_nonce('ett_send_warning'); ?>'
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Sending...');
                },
                success: function(response) {
                    if (response.success) {
                        $btn.text('Warning Sent').addClass('button-primary').prop('disabled', true);
                        
                        // Show success message
                        var notice = $('<div class="notice notice-success is-dismissible"><p>Warning sent successfully to ' + employeeName + '</p></div>');
                        $('.wrap h1').after(notice);
                        
                        setTimeout(function() {
                            notice.fadeOut();
                        }, 5000);
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
        }
    });
});
</script>