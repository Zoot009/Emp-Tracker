<?php
/**
 * Issue Reporting Section
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get recent issues
$recent_issues = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}ett_issues 
    WHERE employee_id = %d 
    ORDER BY raised_date DESC
    LIMIT 5
", $employee_id));
?>

<div class="ett-issue-section ett-card">
    <div class="ett-card-header">
        <h3 class="ett-card-title">Report an Issue</h3>
    </div>
    
    <form id="ett-issue-form">
        <div class="ett-form-group">
            <label for="issue-category">Category:</label>
            <select id="issue-category" required>
                <option value="">Select Category</option>
                <option value="Equipment">Equipment Issues</option>
                <option value="Cleanliness">Cleanliness & Hygiene</option>
                <option value="Documents">Documents & Paperwork</option>
                <option value="Stationery">Stationery & Supplies</option>
                <option value="IT Support">IT & Technical Support</option>
                <option value="Facilities">Facilities & Infrastructure</option>
                <option value="Safety">Safety & Security</option>
                <option value="Other">Other</option>
            </select>
        </div>
        
        <div class="ett-form-group">
            <label for="issue-description">Description:</label>
            <textarea id="issue-description" 
                      rows="4" 
                      placeholder="Please describe the issue in detail..."
                      required></textarea>
        </div>
        
        <div class="ett-form-group">
            <button type="submit" class="ett-button ett-button-primary">Submit Issue</button>
        </div>
    </form>
    
    <?php if (!empty($recent_issues)): ?>
        <div class="ett-recent-issues">
            <h4>Your Recent Issues</h4>
            <table class="ett-public-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Days Open</th>
                        <th>Response</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent_issues as $issue): 
                        $raised = new DateTime($issue->raised_date);
                        $now = new DateTime();
                        $days = $now->diff($raised)->days;
                    ?>
                    <tr>
                        <td><?php echo $raised->format('M d'); ?></td>
                        <td><?php echo esc_html($issue->issue_category); ?></td>
                        <td>
                            <?php if ($issue->issue_status == 'resolved'): ?>
                                <span class="ett-badge ett-badge-success">Resolved</span>
                            <?php elseif ($issue->issue_status == 'in_progress'): ?>
                                <span class="ett-badge ett-badge-warning">In Progress</span>
                            <?php else: ?>
                                <span class="ett-badge ett-badge-danger">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $days; ?> days</td>
                        <td>
                            <?php if ($issue->admin_response): ?>
                                <span title="<?php echo esc_attr($issue->admin_response); ?>" style="cursor:help;">
                                    View Response
                                </span>
                            <?php else: ?>
                                <span style="color:#999;">No response yet</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>