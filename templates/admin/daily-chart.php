<?php
/**
 * Daily Chart Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

$selected_date = isset($_GET['log_date']) ? $security->sanitize_text($_GET['log_date']) : date('Y-m-d');

// Get all employees and tags
$employees = $database->get_all_employees();
$tags = $database->get_all_tags();

global $wpdb;
?>

<div class="wrap">
    <h1>Daily Work Chart</h1>
    
    <form method="get" class="ett-filter-form">
        <input type="hidden" name="page" value="ett-daily-chart" />
        <label>Select Date: 
            <input type="date" name="log_date" value="<?php echo $security->escape_attr($selected_date); ?>" max="<?php echo date('Y-m-d'); ?>" />
        </label>
        <input type="submit" class="button button-primary" value="View Chart" />
    </form>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Work Log for <?php echo date('F j, Y', strtotime($selected_date)); ?></h2>
        </div>
        
        <div style="overflow-x:auto;">
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width:150px;">Employee</th>
                        <?php foreach ($tags as $tag): ?>
                            <th style="min-width:100px;">
                                <?php echo $security->escape_html($tag->tag_name); ?><br>
                                <small>(<?php echo $tag->time_minutes; ?> min)</small>
                            </th>
                        <?php endforeach; ?>
                        <th style="width:100px;">Total Time</th>
                        <th style="width:100px;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                        <tr>
                            <td><strong><?php echo $security->escape_html($employee->name); ?></strong></td>
                            <?php 
                            $total_minutes = 0;
                            foreach ($tags as $tag): 
                                $log = $wpdb->get_row($wpdb->prepare(
                                    "SELECT * FROM {$wpdb->prefix}ett_logs 
                                     WHERE employee_id = %d AND tag_id = %d AND log_date = %s",
                                    $employee->id,
                                    $tag->id,
                                    $selected_date
                                ));
                                $count = $log ? $log->count : 0;
                                $minutes = $log ? $log->total_minutes : 0;
                                $total_minutes += $minutes;
                                ?>
                                <td style="text-align:center;">
                                    <?php if ($count > 0): ?>
                                        <strong><?php echo $count; ?></strong><br>
                                        <small>(<?php echo $minutes; ?> min)</small>
                                    <?php else: ?>
                                        <span style="color:#ccc;">-</span>
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                            <td style="text-align:center;">
                                <?php 
                                if ($total_minutes > 0) {
                                    echo '<strong>' . ETT_Utils::minutes_to_hours_format($total_minutes) . '</strong>';
                                } else {
                                    echo '<span style="color:#ccc;">-</span>';
                                }
                                ?>
                            </td>
                            <td style="text-align:center;">
                                <?php
                                $submission = $wpdb->get_row($wpdb->prepare(
                                    "SELECT * FROM {$wpdb->prefix}ett_submission_status 
                                     WHERE employee_id = %d AND submission_date = %s",
                                    $employee->id,
                                    $selected_date
                                ));
                                if ($submission && $submission->is_locked) {
                                    echo '<span class="ett-badge ett-badge-success">Submitted</span>';
                                } else {
                                    echo '<span class="ett-badge ett-badge-warning">Pending</span>';
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>