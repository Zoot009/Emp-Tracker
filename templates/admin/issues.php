<?php
/**
 * Issues Management Admin Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

$status_filter = isset($_GET['status']) ? $security->sanitize_text($_GET['status']) : 'all';
$category_filter = isset($_GET['category']) ? $security->sanitize_text($_GET['category']) : 'all';

global $wpdb;

$query = "SELECT i.*, e.name as employee_name, e.employee_code 
          FROM {$wpdb->prefix}ett_issues i
          LEFT JOIN {$wpdb->prefix}ett_employees e ON i.employee_id = e.id
          WHERE 1=1";

$params = array();

if ($status_filter !== 'all') {
    $query .= " AND i.issue_status = %s";
    $params[] = $status_filter;
}

if ($category_filter !== 'all') {
    $query .= " AND i.issue_category = %s";
    $params[] = $category_filter;
}

$query .= " ORDER BY i.raised_date DESC";

$issues = !empty($params) ? $wpdb->get_results($wpdb->prepare($query, ...$params)) : $wpdb->get_results($query);

// Get categories for filter
$categories = $wpdb->get_col("SELECT DISTINCT issue_category FROM {$wpdb->prefix}ett_issues ORDER BY issue_category");

// Get statistics
$stats = array(
    'total' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_issues"),
    'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_issues WHERE issue_status = 'pending'"),
    'in_progress' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_issues WHERE issue_status = 'in_progress'"),
    'resolved' => $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_issues WHERE issue_status = 'resolved'")
);
?>

<div class="wrap">
    <h1>Issue Management</h1>
    
    <!-- Statistics Dashboard -->
    <div class="ett-dashboard-stats">
        <div class="ett-stat-card">
            <h3>Total Issues</h3>
            <p class="ett-stat-number"><?php echo $stats['total']; ?></p>
        </div>
        <div class="ett-stat-card">
            <h3>Pending</h3>
            <p class="ett-stat-number" style="color: #dc3545;"><?php echo $stats['pending']; ?></p>
        </div>
        <div class="ett-stat-card">
            <h3>In Progress</h3>
            <p class="ett-stat-number" style="color: #ffc107;"><?php echo $stats['in_progress']; ?></p>
        </div>
        <div class="ett-stat-card">
            <h3>Resolved</h3>
            <p class="ett-stat-number" style="color: #28a745;"><?php echo $stats['resolved']; ?></p>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="ett-card">
        <form method="get" class="ett-filter-form">
            <input type="hidden" name="page" value="ett-issues" />
            <label>Status:
                <select name="status">
                    <option value="all" <?php selected($status_filter, 'all'); ?>>All</option>
                    <option value="pending" <?php selected($status_filter, 'pending'); ?>>Pending</option>
                    <option value="in_progress" <?php selected($status_filter, 'in_progress'); ?>>In Progress</option>
                    <option value="resolved" <?php selected($status_filter, 'resolved'); ?>>Resolved</option>
                </select>
            </label>
            <label>Category:
                <select name="category">
                    <option value="all" <?php selected($category_filter, 'all'); ?>>All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $security->escape_attr($category); ?>" <?php selected($category_filter, $category); ?>>
                            <?php echo $security->escape_html($category); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <input type="submit" class="button button-primary" value="Filter" />
        </form>
    </div>
    
    <!-- Issues Table -->
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Issues List (<?php echo count($issues); ?> found)</h2>
        </div>
        
        <?php if (!empty($issues)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column">ID</th>
                        <th scope="col" class="manage-column">Employee</th>
                        <th scope="col" class="manage-column">Category</th>
                        <th scope="col" class="manage-column">Description</th>
                        <th scope="col" class="manage-column">Raised Date</th>
                        <th scope="col" class="manage-column">Days Open</th>
                        <th scope="col" class="manage-column">Status</th>
                        <th scope="col" class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($issues as $issue): 
                        $raised = new DateTime($issue->raised_date);
                        $now = new DateTime();
                        $days = $now->diff($raised)->days;
                    ?>
                    <tr>
                        <td><strong>#<?php echo $issue->id; ?></strong></td>
                        <td>
                            <strong><?php echo $security->escape_html($issue->employee_name); ?></strong><br>
                            <small><?php echo $security->escape_html($issue->employee_code); ?></small>
                        </td>
                        <td>
                            <span class="ett-badge ett-badge-info">
                                <?php echo $security->escape_html($issue->issue_category); ?>
                            </span>
                        </td>
                        <td>
                            <div class="issue-description">
                                <?php echo $security->escape_html(substr($issue->issue_description, 0, 100)); ?>
                                <?php if (strlen($issue->issue_description) > 100): ?>...<?php endif; ?>
                            </div>
                        </td>
                        <td><?php echo $raised->format('M j, Y g:i A'); ?></td>
                        <td>
                            <span class="days-count <?php echo $days > 7 ? 'urgent' : ($days > 3 ? 'warning' : 'normal'); ?>">
                                <?php echo $days; ?> days
                            </span>
                        </td>
                        <td>
                            <select class="issue-status" data-issue-id="<?php echo $issue->id; ?>">
                                <option value="pending" <?php selected($issue->issue_status, 'pending'); ?>>Pending</option>
                                <option value="in_progress" <?php selected($issue->issue_status, 'in_progress'); ?>>In Progress</option>
                                <option value="resolved" <?php selected($issue->issue_status, 'resolved'); ?>>Resolved</option>
                            </select>
                        </td>
                        <td>
                            <button class="button button-small view-issue" 
                                    data-issue='<?php echo $security->escape_attr(json_encode($issue)); ?>'>
                                View Details
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ett-alert ett-alert-info">
                <p>No issues found matching the selected criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Issue View Modal -->
<div id="issue-modal" class="ett-modal" style="display:none;">
    <div class="ett-modal-content">
        <span class="ett-close">&times;</span>
        <h2>Issue Details</h2>
        <div id="issue-details"></div>
        <div class="issue-response">
            <h3>Admin Response</h3>
            <textarea id="admin-response" rows="4" style="width:100%;" placeholder="Enter your response to the employee..."></textarea>
            <br><br>
            <button class="button button-primary" id="save-response">Save Response</button>
        </div>
    </div>
</div>

<style>
.issue-description {
    max-width: 250px;
    word-wrap: break-word;
}

.days-count.normal {
    color: #28a745;
}

.days-count.warning {
    color: #ffc107;
    font-weight: bold;
}

.days-count.urgent {
    color: #dc3545;
    font-weight: bold;
}

.issue-status {
    width: 120px;
}
</style>

<script>
jQuery(document).ready(function($) {
    $('.issue-status').change(function() {
        var issueId = $(this).data('issue-id');
        var status = $(this).val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ett_update_issue_status',
                issue_id: issueId,
                status: status,
                nonce: '<?php echo wp_create_nonce('ett_update_issue'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    var notice = $('<div class="notice notice-success is-dismissible"><p>Issue status updated successfully</p></div>');
                    $('.wrap h1').after(notice);
                    setTimeout(function() {
                        notice.fadeOut();
                    }, 3000);
                } else {
                    alert('Failed to update status: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Network error occurred');
            }
        });
    });
    
    $('.view-issue').click(function() {
        var issue = $(this).data('issue');
        
        var html = '<div class="issue-full-details">';
        html += '<table class="form-table">';
        html += '<tr><th>Employee:</th><td>' + issue.employee_name + ' (' + issue.employee_code + ')</td></tr>';
        html += '<tr><th>Category:</th><td>' + issue.issue_category + '</td></tr>';
        html += '<tr><th>Status:</th><td>' + issue.issue_status + '</td></tr>';
        html += '<tr><th>Raised Date:</th><td>' + issue.raised_date + '</td></tr>';
        html += '<tr><th>Description:</th><td>' + issue.issue_description + '</td></tr>';
        if (issue.resolved_date) {
            html += '<tr><th>Resolved Date:</th><td>' + issue.resolved_date + '</td></tr>';
        }
        html += '</table></div>';
        
        $('#issue-details').html(html);
        $('#admin-response').val(issue.admin_response || '');
        $('#save-response').data('issue-id', issue.id);
        $('#issue-modal').show();
    });
    
    $('.ett-close').click(function() {
        $('#issue-modal').hide();
    });
    
    $('#save-response').click(function() {
        var issueId = $(this).data('issue-id');
        var response = $('#admin-response').val();
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'ett_update_issue_status',
                issue_id: issueId,
                admin_response: response,
                nonce: '<?php echo wp_create_nonce('ett_update_issue'); ?>'
            },
            beforeSend: function() {
                $('#save-response').prop('disabled', true).text('Saving...');
            },
            success: function(response) {
                if (response.success) {
                    alert('Response saved successfully');
                    $('#issue-modal').hide();
                } else {
                    alert('Failed to save response: ' + (response.data || 'Unknown error'));
                }
            },
            error: function() {
                alert('Network error occurred');
            },
            complete: function() {
                $('#save-response').prop('disabled', false).text('Save Response');
            }
        });
    });
    
    // Close modal on outside click
    $('#issue-modal').click(function(e) {
        if (e.target === this) {
            $(this).hide();
        }
    });
});
</script>