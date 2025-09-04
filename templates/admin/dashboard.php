<?php
/**
 * Admin Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();

// Get statistics
$total_employees = count($database->get_all_employees());
$total_tags = count($database->get_all_tags());

global $wpdb;
$todays_submissions = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ett_submission_status WHERE submission_date = %s",
    date('Y-m-d')
));

$pending_issues = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_issues WHERE issue_status = 'pending'");
?>

<div class="wrap">
    <h1>Employee Tag Tracker Dashboard</h1>
    
    <div class="ett-dashboard-stats">
        <div class="ett-stat-card">
            <h3>Total Employees</h3>
            <p class="ett-stat-number"><?php echo esc_html($total_employees); ?></p>
        </div>
        <div class="ett-stat-card">
            <h3>Total Tags</h3>
            <p class="ett-stat-number"><?php echo esc_html($total_tags); ?></p>
        </div>
        <div class="ett-stat-card">
            <h3>Today's Submissions</h3>
            <p class="ett-stat-number"><?php echo esc_html($todays_submissions); ?></p>
        </div>
        <div class="ett-stat-card">
            <h3>Pending Issues</h3>
            <p class="ett-stat-number" style="color:#ff6b6b;"><?php echo esc_html($pending_issues); ?></p>
        </div>
    </div>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Quick Actions</h2>
        </div>
        <div class="ett-quick-links">
            <a href="<?php echo admin_url('admin.php?page=ett-daily-chart'); ?>" class="ett-btn ett-btn-primary">
                📊 View Daily Chart
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-employees'); ?>" class="ett-btn ett-btn-primary">
                👥 Manage Employees
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-tags'); ?>" class="ett-btn ett-btn-primary">
                🏷️ Manage Tags
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-assignments'); ?>" class="ett-btn ett-btn-primary">
                📋 Manage Assignments
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-edit-logs'); ?>" class="ett-btn ett-btn-warning">
                ✏️ Edit Logs
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-missing-data'); ?>" class="ett-btn ett-btn-warning">
                ⚠️ Check Missing Data
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-warnings'); ?>" class="ett-btn ett-btn-danger">
                🚨 Warning Management
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-breaks'); ?>" class="ett-btn ett-btn-info">
                ☕ Break Management
            </a>
            <a href="<?php echo admin_url('admin.php?page=ett-issues'); ?>" class="ett-btn ett-btn-info">
                🎫 Issue Management
            </a>
        </div>
    </div>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Recent Activity</h2>
        </div>
        <div class="ett-recent-activity">
            <?php
            // Get recent submissions
            $recent_submissions = $wpdb->get_results("
                SELECT s.*, e.name as employee_name
                FROM {$wpdb->prefix}ett_submission_status s
                LEFT JOIN {$wpdb->prefix}ett_employees e ON s.employee_id = e.id
                ORDER BY s.submission_time DESC
                LIMIT 5
            ");
            
            if (!empty($recent_submissions)): ?>
                <h4>Recent Submissions</h4>
                <ul>
                    <?php foreach ($recent_submissions as $submission): ?>
                    <li>
                        <strong><?php echo esc_html($submission->employee_name); ?></strong> 
                        submitted data for <?php echo esc_html($submission->submission_date); ?>
                        (<?php echo ETT_Utils::minutes_to_hours_format($submission->total_minutes); ?>)
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No recent activity.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
.ett-quick-links {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin: 20px 0;
}

.ett-quick-links .ett-btn {
    text-align: center;
    padding: 15px;
    text-decoration: none;
    border-radius: 8px;
    transition: transform 0.2s ease;
}

.ett-quick-links .ett-btn:hover {
    transform: translateY(-2px);
}

.ett-recent-activity ul {
    list-style: none;
    padding: 0;
}

.ett-recent-activity li {
    padding: 10px;
    border-bottom: 1px solid #eee;
    margin: 0;
}

.ett-recent-activity li:last-child {
    border-bottom: none;
}
</style>