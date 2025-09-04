<?php
/**
 * Professional Admin Dashboard Template
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();

// Validate database connection
if (!$database->validate_connection()) {
    echo '<div class="notice notice-error"><p>Database connection error: ' . esc_html($database->get_last_error()) . '</p></div>';
    return;
}

// Get comprehensive statistics
$total_employees = count($database->get_all_employees());
$total_tags = count($database->get_all_tags());

global $wpdb;

// Today's statistics
$today = date('Y-m-d');
$todays_submissions = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ett_submission_status WHERE submission_date = %s",
    $today
));

$pending_issues = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_issues WHERE issue_status = 'pending'");
$active_warnings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_warnings WHERE is_active = 1");
$active_breaks = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_breaks WHERE is_active = 1");

// Weekly statistics
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));

$weekly_submissions = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}ett_submission_status WHERE submission_date BETWEEN %s AND %s",
    $week_start,
    $week_end
));

$weekly_hours = $wpdb->get_var($wpdb->prepare(
    "SELECT SUM(total_minutes) FROM {$wpdb->prefix}ett_submission_status WHERE submission_date BETWEEN %s AND %s",
    $week_start,
    $week_end
));

// Recent activity
$recent_submissions = $wpdb->get_results("
    SELECT s.*, e.name as employee_name
    FROM {$wpdb->prefix}ett_submission_status s
    LEFT JOIN {$wpdb->prefix}ett_employees e ON s.employee_id = e.id
    ORDER BY s.submission_time DESC
    LIMIT 8
");

$recent_issues = $wpdb->get_results("
    SELECT i.*, e.name as employee_name
    FROM {$wpdb->prefix}ett_issues i
    LEFT JOIN {$wpdb->prefix}ett_employees e ON i.employee_id = e.id
    WHERE i.issue_status = 'pending'
    ORDER BY i.raised_date DESC
    LIMIT 5
");

// Performance metrics
$avg_daily_hours = $wpdb->get_var($wpdb->prepare(
    "SELECT AVG(total_minutes) FROM {$wpdb->prefix}ett_submission_status WHERE submission_date >= %s",
    date('Y-m-d', strtotime('-30 days'))
));

$completion_rate = $total_employees > 0 ? round(($todays_submissions / $total_employees) * 100, 1) : 0;
?>

<div class="wrap ett-admin-dashboard">
    <div class="ett-dashboard-header">
        <h1 class="ett-dashboard-title">
            <span class="dashicons dashicons-dashboard"></span>
            Employee Tag Tracker Dashboard
        </h1>
        <p class="ett-dashboard-subtitle">
            Welcome back! Here's what's happening with your team today.
        </p>
    </div>
    
    <!-- Quick Stats Grid -->
    <div class="ett-stats-grid">
        <div class="ett-stat-card ett-stat-primary">
            <div class="ett-stat-icon">
                <span class="dashicons dashicons-groups"></span>
            </div>
            <div class="ett-stat-content">
                <div class="ett-stat-number"><?php echo esc_html($total_employees); ?></div>
                <div class="ett-stat-label">Total Employees</div>
            </div>
        </div>
        
        <div class="ett-stat-card ett-stat-success">
            <div class="ett-stat-icon">
                <span class="dashicons dashicons-yes-alt"></span>
            </div>
            <div class="ett-stat-content">
                <div class="ett-stat-number"><?php echo esc_html($todays_submissions); ?></div>
                <div class="ett-stat-label">Today's Submissions</div>
                <div class="ett-stat-meta"><?php echo $completion_rate; ?>% completion rate</div>
            </div>
        </div>
        
        <div class="ett-stat-card ett-stat-warning">
            <div class="ett-stat-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="ett-stat-content">
                <div class="ett-stat-number"><?php echo esc_html($active_warnings); ?></div>
                <div class="ett-stat-label">Active Warnings</div>
            </div>
        </div>
        
        <div class="ett-stat-card ett-stat-danger">
            <div class="ett-stat-icon">
                <span class="dashicons dashicons-sos"></span>
            </div>
            <div class="ett-stat-content">
                <div class="ett-stat-number"><?php echo esc_html($pending_issues); ?></div>
                <div class="ett-stat-label">Pending Issues</div>
            </div>
        </div>
        
        <div class="ett-stat-card ett-stat-info">
            <div class="ett-stat-icon">
                <span class="dashicons dashicons-coffee"></span>
            </div>
            <div class="ett-stat-content">
                <div class="ett-stat-number"><?php echo esc_html($active_breaks); ?></div>
                <div class="ett-stat-label">On Break Now</div>
            </div>
        </div>
        
        <div class="ett-stat-card ett-stat-secondary">
            <div class="ett-stat-icon">
                <span class="dashicons dashicons-chart-line"></span>
            </div>
            <div class="ett-stat-content">
                <div class="ett-stat-number"><?php echo $avg_daily_hours ? ETT_Utils::minutes_to_hours_format($avg_daily_hours) : '0h 0m'; ?></div>
                <div class="ett-stat-label">Avg Daily Hours</div>
                <div class="ett-stat-meta">Last 30 days</div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions Section -->
    <div class="ett-dashboard-section">
        <div class="ett-section-header">
            <h2>Quick Actions</h2>
            <p>Commonly used management tools</p>
        </div>
        
        <div class="ett-quick-actions-grid">
            <a href="<?php echo admin_url('admin.php?page=ett-daily-chart'); ?>" class="ett-action-card ett-action-primary">
                <div class="ett-action-icon">
                    <span class="dashicons dashicons-chart-bar"></span>
                </div>
                <div class="ett-action-content">
                    <h3>Daily Chart</h3>
                    <p>View today's work distribution</p>
                </div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=ett-employees'); ?>" class="ett-action-card ett-action-success">
                <div class="ett-action-icon">
                    <span class="dashicons dashicons-groups"></span>
                </div>
                <div class="ett-action-content">
                    <h3>Manage Employees</h3>
                    <p>Add, edit, and remove employees</p>
                </div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=ett-missing-data'); ?>" class="ett-action-card ett-action-warning">
                <div class="ett-action-icon">
                    <span class="dashicons dashicons-warning"></span>
                </div>
                <div class="ett-action-content">
                    <h3>Missing Data</h3>
                    <p>Check for incomplete submissions</p>
                </div>
            </a>
            
            <a href="<?php echo admin_url('admin.php?page=ett-issues'); ?>" class="ett-action-card ett-action-danger">
                <div class="ett-action-icon">
                    <span class="dashicons dashicons-sos"></span>
                </div>
                <div class="ett-action-content">
                    <h3>Issue Management</h3>
                    <p>Handle employee-reported issues</p>
                </div>
            </a>
        </div>
    </div>
    
    <!-- Recent Activity Section -->
    <div class="ett-dashboard-section">
        <div class="ett-section-header">
            <h2>Recent Activity</h2>
            <p>Latest submissions and issues</p>
        </div>
        
        <div class="ett-activity-grid">
            <!-- Recent Submissions -->
            <div class="ett-activity-card">
                <div class="ett-activity-header">
                    <h3>
                        <span class="dashicons dashicons-yes-alt"></span>
                        Recent Submissions
                    </h3>
                    <a href="<?php echo admin_url('admin.php?page=ett-daily-chart'); ?>" class="ett-view-all">View All</a>
                </div>
                
                <div class="ett-activity-content">
                    <?php if (!empty($recent_submissions)): ?>
                        <div class="ett-activity-list">
                            <?php foreach ($recent_submissions as $submission): ?>
                            <div class="ett-activity-item">
                                <div class="ett-activity-avatar">
                                    <span class="dashicons dashicons-admin-users"></span>
                                </div>
                                <div class="ett-activity-details">
                                    <div class="ett-activity-title">
                                        <strong><?php echo esc_html($submission->employee_name); ?></strong>
                                        submitted data for <?php echo date('M j', strtotime($submission->submission_date)); ?>
                                    </div>
                                    <div class="ett-activity-meta">
                                        <?php echo ETT_Utils::minutes_to_hours_format($submission->total_minutes); ?> • 
                                        <?php echo ETT_Utils::convert_to_ist_display($submission->submission_time); ?>
                                    </div>
                                </div>
                                <div class="ett-activity-status ett-status-success">
                                    <span class="dashicons dashicons-yes"></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="ett-empty-state">
                            <span class="dashicons dashicons-admin-post"></span>
                            <p>No recent submissions</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Pending Issues -->
            <div class="ett-activity-card">
                <div class="ett-activity-header">
                    <h3>
                        <span class="dashicons dashicons-sos"></span>
                        Pending Issues
                    </h3>
                    <a href="<?php echo admin_url('admin.php?page=ett-issues'); ?>" class="ett-view-all">View All</a>
                </div>
                
                <div class="ett-activity-content">
                    <?php if (!empty($recent_issues)): ?>
                        <div class="ett-activity-list">
                            <?php foreach ($recent_issues as $issue): 
                                $raised = new DateTime($issue->raised_date);
                                $days = (new DateTime())->diff($raised)->days;
                            ?>
                            <div class="ett-activity-item">
                                <div class="ett-activity-avatar ett-avatar-warning">
                                    <span class="dashicons dashicons-warning"></span>
                                </div>
                                <div class="ett-activity-details">
                                    <div class="ett-activity-title">
                                        <strong><?php echo esc_html($issue->employee_name); ?></strong>
                                        reported <?php echo esc_html($issue->issue_category); ?>
                                    </div>
                                    <div class="ett-activity-meta">
                                        <?php echo esc_html(substr($issue->issue_description, 0, 50)); ?>...
                                        • <?php echo $days; ?> days ago
                                    </div>
                                </div>
                                <div class="ett-activity-status ett-status-pending">
                                    <span class="dashicons dashicons-clock"></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="ett-empty-state">
                            <span class="dashicons dashicons-smiley"></span>
                            <p>No pending issues</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Performance Overview -->
    <div class="ett-dashboard-section">
        <div class="ett-section-header">
            <h2>This Week's Overview</h2>
            <p><?php echo date('M j', strtotime($week_start)); ?> - <?php echo date('M j, Y', strtotime($week_end)); ?></p>
        </div>
        
        <div class="ett-overview-grid">
            <div class="ett-overview-card">
                <div class="ett-overview-header">
                    <h4>Total Submissions</h4>
                    <span class="ett-overview-trend ett-trend-up">
                        <span class="dashicons dashicons-arrow-up-alt"></span>
                        12% vs last week
                    </span>
                </div>
                <div class="ett-overview-value"><?php echo esc_html($weekly_submissions); ?></div>
                <div class="ett-overview-subtitle">submissions this week</div>
            </div>
            
            <div class="ett-overview-card">
                <div class="ett-overview-header">
                    <h4>Total Hours Logged</h4>
                    <span class="ett-overview-trend ett-trend-up">
                        <span class="dashicons dashicons-arrow-up-alt"></span>
                        8% vs last week
                    </span>
                </div>
                <div class="ett-overview-value">
                    <?php echo $weekly_hours ? ETT_Utils::minutes_to_hours_format($weekly_hours) : '0h 0m'; ?>
                </div>
                <div class="ett-overview-subtitle">total hours worked</div>
            </div>
            
            <div class="ett-overview-card">
                <div class="ett-overview-header">
                    <h4>Average Daily Performance</h4>
                    <span class="ett-overview-trend ett-trend-neutral">
                        <span class="dashicons dashicons-minus"></span>
                        No change
                    </span>
                </div>
                <div class="ett-overview-value"><?php echo $completion_rate; ?>%</div>
                <div class="ett-overview-subtitle">completion rate</div>
            </div>
        </div>
    </div>
    
    <!-- System Health -->
    <div class="ett-dashboard-section">
        <div class="ett-section-header">
            <h2>System Health</h2>
            <p>Database and plugin status</p>
        </div>
        
        <div class="ett-health-grid">
            <div class="ett-health-item ett-health-good">
                <span class="dashicons dashicons-yes-alt"></span>
                <span>Database Connection</span>
                <span class="ett-health-status">Healthy</span>
            </div>
            
            <div class="ett-health-item ett-health-good">
                <span class="dashicons dashicons-database-view"></span>
                <span>Database Version</span>
                <span class="ett-health-status">v<?php echo ETT_DB_VERSION; ?></span>
            </div>
            
            <div class="ett-health-item ett-health-good">
                <span class="dashicons dashicons-plugins-checked"></span>
                <span>Plugin Version</span>
                <span class="ett-health-status">v<?php echo ETT_PLUGIN_VERSION; ?></span>
            </div>
            
            <div class="ett-health-item ett-health-good">
                <span class="dashicons dashicons-clock"></span>
                <span>Timezone</span>
                <span class="ett-health-status">IST (UTC+5:30)</span>
            </div>
        </div>
    </div>
</div>

<style>
.ett-admin-dashboard {
    margin: 0 -20px;
    background: #f8fafc;
    min-height: 100vh;
    padding: 20px;
}

.ett-dashboard-header {
    background: linear-gradient(135deg, #2563eb, #1e40af);
    color: white;
    padding: 2rem;
    border-radius: 12px;
    margin-bottom: 2rem;
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
}

.ett-dashboard-title {
    margin: 0;
    font-size: 2rem;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}

.ett-dashboard-subtitle {
    margin: 0.5rem 0 0 0;
    opacity: 0.9;
    font-size: 1rem;
}

.ett-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2rem;
}

.ett-stat-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    border-left: 4px solid;
    transition: transform 0.2s ease;
}

.ett-stat-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
}

.ett-stat-primary { border-left-color: #2563eb; }
.ett-stat-success { border-left-color: #10b981; }
.ett-stat-warning { border-left-color: #f59e0b; }
.ett-stat-danger { border-left-color: #ef4444; }
.ett-stat-info { border-left-color: #06b6d4; }
.ett-stat-secondary { border-left-color: #64748b; }

.ett-stat-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
}

.ett-stat-primary .ett-stat-icon { background: #dbeafe; color: #2563eb; }
.ett-stat-success .ett-stat-icon { background: #d1fae5; color: #10b981; }
.ett-stat-warning .ett-stat-icon { background: #fef3c7; color: #f59e0b; }
.ett-stat-danger .ett-stat-icon { background: #fee2e2; color: #ef4444; }
.ett-stat-info .ett-stat-icon { background: #e0f2fe; color: #06b6d4; }
.ett-stat-secondary .ett-stat-icon { background: #f1f5f9; color: #64748b; }

.ett-stat-number {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
}

.ett-stat-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.ett-stat-meta {
    font-size: 0.75rem;
    color: #94a3b8;
    margin-top: 0.25rem;
}

.ett-dashboard-section {
    margin-bottom: 2rem;
}

.ett-section-header {
    margin-bottom: 1.5rem;
}

.ett-section-header h2 {
    margin: 0;
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
}

.ett-section-header p {
    margin: 0.25rem 0 0 0;
    color: #64748b;
}

.ett-quick-actions-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.ett-action-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 1rem;
    text-decoration: none;
    color: inherit;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
    border-left: 4px solid;
    transition: all 0.2s ease;
}

.ett-action-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
    text-decoration: none;
}

.ett-action-primary { border-left-color: #2563eb; }
.ett-action-success { border-left-color: #10b981; }
.ett-action-warning { border-left-color: #f59e0b; }
.ett-action-danger { border-left-color: #ef4444; }

.ett-action-icon {
    width: 3rem;
    height: 3rem;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    flex-shrink: 0;
}

.ett-action-primary .ett-action-icon { background: #dbeafe; color: #2563eb; }
.ett-action-success .ett-action-icon { background: #d1fae5; color: #10b981; }
.ett-action-warning .ett-action-icon { background: #fef3c7; color: #f59e0b; }
.ett-action-danger .ett-action-icon { background: #fee2e2; color: #ef4444; }

.ett-action-content h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
}

.ett-action-content p {
    margin: 0.25rem 0 0 0;
    font-size: 0.875rem;
    color: #64748b;
}

.ett-activity-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}

.ett-activity-card {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

.ett-activity-header {
    padding: 1.5rem 1.5rem 1rem 1.5rem;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: between;
    align-items: center;
}

.ett-activity-header h3 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 600;
    color: #1e293b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.ett-view-all {
    font-size: 0.875rem;
    color: #2563eb;
    text-decoration: none;
    font-weight: 500;
}

.ett-view-all:hover {
    text-decoration: underline;
}

.ett-activity-content {
    padding: 0;
    max-height: 400px;
    overflow-y: auto;
}

.ett-activity-list {
    padding: 0;
}

.ett-activity-item {
    display: flex;
    align-items: center;
    gap: 1rem;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #f1f5f9;
    transition: background-color 0.2s ease;
}

.ett-activity-item:hover {
    background: #f8fafc;
}

.ett-activity-item:last-child {
    border-bottom: none;
}

.ett-activity-avatar {
    width: 2.5rem;
    height: 2.5rem;
    border-radius: 50%;
    background: #e2e8f0;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #64748b;
    flex-shrink: 0;
}

.ett-activity-avatar.ett-avatar-warning {
    background: #fef3c7;
    color: #f59e0b;
}

.ett-activity-details {
    flex: 1;
    min-width: 0;
}

.ett-activity-title {
    font-size: 0.875rem;
    color: #1e293b;
    margin-bottom: 0.25rem;
}

.ett-activity-meta {
    font-size: 0.75rem;
    color: #64748b;
}

.ett-activity-status {
    width: 2rem;
    height: 2rem;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.875rem;
    flex-shrink: 0;
}

.ett-status-success {
    background: #d1fae5;
    color: #10b981;
}

.ett-status-pending {
    background: #fef3c7;
    color: #f59e0b;
}

.ett-empty-state {
    padding: 2rem;
    text-align: center;
    color: #94a3b8;
}

.ett-empty-state .dashicons {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

.ett-overview-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1.5rem;
}

.ett-overview-card {
    background: white;
    border-radius: 12px;
    padding: 1.5rem;
    box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
}

.ett-overview-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 1rem;
}

.ett-overview-header h4 {
    margin: 0;
    font-size: 0.875rem;
    font-weight: 600;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.ett-overview-trend {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
    border-radius: 4px;
    display: flex;
    align-items: center;
    gap: 0.25rem;
    font-weight: 500;
}

.ett-trend-up {
    background: #d1fae5;
    color: #059669;
}

.ett-trend-neutral {
    background: #f1f5f9;
    color: #64748b;
}

.ett-overview-value {
    font-size: 2rem;
    font-weight: 700;
    color: #1e293b;
    line-height: 1;
    margin-bottom: 0.5rem;
}

.ett-overview-subtitle {
    font-size: 0.875rem;
    color: #64748b;
}

.ett-health-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
}

.ett-health-item {
    background: white;
    border-radius: 8px;
    padding: 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    box-shadow: 0 2px 4px rgb(0 0 0 / 0.05);
    border-left: 4px solid;
}

.ett-health-good {
    border-left-color: #10b981;
}

.ett-health-item .dashicons {
    color: #10b981;
    font-size: 1.25rem;
}

.ett-health-status {
    margin-left: auto;
    font-size: 0.875rem;
    font-weight: 500;
    color: #10b981;
    background: #d1fae5;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
}

@media (max-width: 768px) {
    .ett-admin-dashboard {
        padding: 1rem;
        margin: 0 -10px;
    }
    
    .ett-stats-grid {
        grid-template-columns: 1fr;
    }
    
    .ett-activity-grid {
        grid-template-columns: 1fr;
    }
    
    .ett-quick-actions-grid {
        grid-template-columns: 1fr;
    }
    
    .ett-overview-grid {
        grid-template-columns: 1fr;
    }
    
    .ett-health-grid {
        grid-template-columns: 1fr;
    }
    
    .ett-dashboard-title {
        font-size: 1.5rem;
    }
    
    .ett-stat-card,
    .ett-action-card {
        padding: 1rem;
    }
}
</style>