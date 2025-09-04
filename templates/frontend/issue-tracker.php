<?php
/**
 * Issue Tracker Frontend Display
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$issues = $wpdb->get_results("
    SELECT i.*, e.name as employee_name, e.employee_code
    FROM {$wpdb->prefix}ett_issues i
    LEFT JOIN {$wpdb->prefix}ett_employees e ON i.employee_id = e.id
    ORDER BY i.raised_date DESC
    LIMIT 50
");

// Group issues by status
$issues_by_status = array(
    'pending' => array(),
    'in_progress' => array(),
    'resolved' => array()
);

foreach ($issues as $issue) {
    $issues_by_status[$issue->issue_status][] = $issue;
}
?>

<div class="ett-issue-tracker ett-card">
    <div class="ett-card-header">
        <h2 class="ett-card-title">Issue Tracker</h2>
        <p>Current status of reported issues</p>
    </div>
    
    <div class="issue-status-summary">
        <div class="status-card pending">
            <h3><?php echo count($issues_by_status['pending']); ?></h3>
            <p>Pending</p>
        </div>
        <div class="status-card in-progress">
            <h3><?php echo count($issues_by_status['in_progress']); ?></h3>
            <p>In Progress</p>
        </div>
        <div class="status-card resolved">
            <h3><?php echo count($issues_by_status['resolved']); ?></h3>
            <p>Resolved</p>
        </div>
    </div>
    
    <?php if (!empty($issues)): ?>
        <div class="ett-table-responsive">
            <table class="ett-public-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Employee</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Raised Date</th>
                        <th>Days</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $issue): 
                        $raised = new DateTime($issue->raised_date);
                        $now = new DateTime();
                        $days = $now->diff($raised)->days;
                    ?>
                    <tr class="issue-row status-<?php echo $issue->issue_status; ?>">
                        <td><strong>#<?php echo $issue->id; ?></strong></td>
                        <td>
                            <?php echo esc_html($issue->employee_name); ?><br>
                            <small><?php echo esc_html($issue->employee_code); ?></small>
                        </td>
                        <td>
                            <span class="category-badge">
                                <?php echo esc_html($issue->issue_category); ?>
                            </span>
                        </td>
                        <td>
                            <div class="issue-description">
                                <?php echo esc_html(substr($issue->issue_description, 0, 60)); ?>
                                <?php if (strlen($issue->issue_description) > 60): ?>...<?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo $raised->format('M d, Y'); ?></td>
                        <td>
                            <span class="days-badge <?php echo $days > 7 ? 'urgent' : ($days > 3 ? 'warning' : 'normal'); ?>">
                                <?php echo $days; ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($issue->issue_status == 'resolved'): ?>
                                <span class="ett-badge ett-badge-success">✓ Resolved</span>
                            <?php elseif ($issue->issue_status == 'in_progress'): ?>
                                <span class="ett-badge ett-badge-warning">⏳ In Progress</span>
                            <?php else: ?>
                                <span class="ett-badge ett-badge-danger">⏸ Pending</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="ett-alert ett-alert-success">
            <p>No issues reported yet. Great work everyone!</p>
        </div>
    <?php endif; ?>
    
    <div class="issue-tracker-footer">
        <p><em>Last updated: <?php echo date('F j, Y g:i A'); ?></em></p>
        <p>Report issues through the employee panel for quick resolution.</p>
    </div>
</div>

<style>
.issue-status-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
    margin: 20px 0;
}

.status-card {
    text-align: center;
    padding: 20px;
    border-radius: 8px;
    color: white;
}

.status-card.pending {
    background: linear-gradient(135deg, #dc3545, #c82333);
}

.status-card.in-progress {
    background: linear-gradient(135deg, #ffc107, #e0a800);
}

.status-card.resolved {
    background: linear-gradient(135deg, #28a745, #1e7e34);
}

.status-card h3 {
    margin: 0;
    font-size: 2em;
    font-weight: bold;
}

.status-card p {
    margin: 5px 0 0 0;
    font-size: 14px;
    opacity: 0.9;
}

.issue-row.status-resolved {
    opacity: 0.7;
}

.category-badge {
    background: #e9ecef;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    color: #495057;
}

.days-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
}

.days-badge.normal {
    background: #d4edda;
    color: #155724;
}

.days-badge.warning {
    background: #fff3cd;
    color: #856404;
}

.days-badge.urgent {
    background: #f8d7da;
    color: #721c24;
}

.issue-tracker-footer {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 4px;
    text-align: center;
    color: #666;
}

@media (max-width: 768px) {
    .issue-status-summary {
        grid-template-columns: 1fr;
    }
    
    .ett-public-table {
        font-size: 12px;
    }
    
    .ett-public-table th,
    .ett-public-table td {
        padding: 8px 4px;
    }
}
</style>