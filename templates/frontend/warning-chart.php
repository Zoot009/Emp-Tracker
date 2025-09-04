<?php
/**
 * Warning Chart Frontend Display
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$warnings = $wpdb->get_results("
    SELECT w.*, e.name as employee_name, e.employee_code
    FROM {$wpdb->prefix}ett_warnings w
    LEFT JOIN {$wpdb->prefix}ett_employees e ON w.employee_id = e.id
    WHERE w.is_active = 1
    ORDER BY w.created_at DESC
    LIMIT 20
");
?>

<div class="ett-warning-chart ett-card">
    <div class="ett-card-header">
        <h2 class="ett-card-title">Active Warnings</h2>
        <p>Current date: <?php echo date('F j, Y g:i A'); ?></p>
    </div>
    
    <?php if (!empty($warnings)): ?>
        <div class="ett-table-responsive">
            <table class="ett-public-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Warning Date</th>
                        <th>Message</th>
                        <th>Days Since</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warnings as $warning): 
                        $warning_date = new DateTime($warning->warning_date);
                        $now = new DateTime();
                        $days_since = $now->diff($warning_date)->days;
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($warning->employee_name); ?></strong><br>
                            <small><?php echo esc_html($warning->employee_code); ?></small>
                        </td>
                        <td><?php echo $warning_date->format('M j, Y'); ?></td>
                        <td>
                            <div class="warning-text">
                                <?php echo esc_html($warning->warning_message); ?>
                            </div>
                        </td>
                        <td>
                            <span class="days-badge <?php echo $days_since > 7 ? 'urgent' : ($days_since > 3 ? 'attention' : 'recent'); ?>">
                                <?php echo $days_since; ?> days
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="warning-summary">
            <p><strong>Total Active Warnings:</strong> <?php echo count($warnings); ?></p>
            <p><em>Last updated: <?php echo date('g:i A'); ?></em></p>
        </div>
    <?php else: ?>
        <div class="ett-alert ett-alert-success">
            <p>No active warnings at this time. Great work everyone!</p>
        </div>
    <?php endif; ?>
</div>

<style>
.warning-text {
    max-width: 300px;
    word-wrap: break-word;
}

.days-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.days-badge.recent {
    background: #d4edda;
    color: #155724;
}

.days-badge.attention {
    background: #fff3cd;
    color: #856404;
}

.days-badge.urgent {
    background: #f8d7da;
    color: #721c24;
}

.warning-summary {
    margin-top: 15px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    text-align: center;
}
</style>