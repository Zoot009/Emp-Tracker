<?php
/**
 * Employee Dashboard Content
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

// Get employee data
$employee = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$wpdb->prefix}ett_employees WHERE id = %d",
    $employee_id
));

if (!$employee) {
    echo '<p>Invalid session. Please login again.</p>';
    return;
}

// Date selection
$selected_date = isset($_POST['log_date']) ? sanitize_text_field($_POST['log_date']) : date('Y-m-d');
$current_date = date('Y-m-d');

// Get active warnings
$active_warnings = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}ett_warnings 
    WHERE employee_id = %d AND is_active = 1
    ORDER BY created_at DESC
", $employee_id));

// Get submission history
$submission_history = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}ett_submission_status 
    WHERE employee_id = %d 
    AND submission_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY submission_date DESC
", $employee_id));

// Check if data already submitted for selected date
$selected_submission = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}ett_submission_status 
    WHERE employee_id = %d AND submission_date = %s AND is_locked = 1
", $employee_id, $selected_date));

// Get assigned tags
$assigned_tags = $database->get_employee_assignments($employee_id);
?>

<div class="ett-employee-panel">
    <!-- Header -->
    <div class="ett-panel-header">
        <h2>Welcome, <?php echo esc_html($employee->name); ?>!</h2>
        <button id="ett-logout-btn" class="ett-logout-btn">Logout</button>
    </div>
    
    <!-- Current Date and Time -->
    <div class="ett-current-datetime">
        <p><strong>Current Date & Time:</strong> <span id="current-datetime"></span></p>
    </div>
    
    <!-- Warnings Display -->
    <?php if (!empty($active_warnings)): ?>
        <div class="ett-warnings-display">
            <h3>⚠️ Active Warnings</h3>
            <?php foreach ($active_warnings as $warning): ?>
                <div class="ett-warning-item">
                    <span class="ett-warning-text">
                        <?php echo esc_html($warning->warning_message); ?> 
                        (<?php echo esc_html($warning->warning_date); ?>)
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Break Management Section -->
    <?php include ETT_PLUGIN_PATH . 'templates/frontend/partials/break-management.php'; ?>
    
    <!-- Submission History -->
    <?php include ETT_PLUGIN_PATH . 'templates/frontend/partials/submission-history.php'; ?>
    
    <!-- Date Selection -->
    <div class="ett-date-selection">
        <h3>Select Date for Work Log</h3>
        <form method="post" id="date-selection-form">
            <input type="date" 
                   name="log_date" 
                   id="log_date" 
                   value="<?php echo esc_attr($selected_date); ?>" 
                   max="<?php echo date('Y-m-d'); ?>" />
            <button type="submit" class="ett-button">Load Data</button>
        </form>
    </div>
    
    <!-- Work Log Form -->
    <?php if ($selected_submission): ?>
        <div class="ett-locked-notice">
            <h3>🔒 Data Already Submitted for <?php echo date('F j, Y', strtotime($selected_date)); ?></h3>
            <p>This data has been submitted and cannot be edited.</p>
            <p>If you wrongly added any data, kindly contact your HR or Team Leader.</p>
        </div>
    <?php else: ?>
        <?php include ETT_PLUGIN_PATH . 'templates/frontend/partials/work-log-form.php'; ?>
    <?php endif; ?>
    
    <!-- Issue Reporting Section -->
    <?php include ETT_PLUGIN_PATH . 'templates/frontend/partials/issue-reporting.php'; ?>
</div>

<script>
// Pass data to JavaScript
var ettEmployeeData = {
    employeeId: <?php echo $employee_id; ?>,
    selectedDate: '<?php echo esc_js($selected_date); ?>'
};
</script>