<?php
/**
 * Warnings Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

$status_filter = isset($_GET['status']) ? $security->sanitize_text($_GET['status']) : 'all';

global $wpdb;

$query = "SELECT w.*, e.name as employee_name, e.employee_code
          FROM {$wpdb->prefix}ett_warnings w
          LEFT JOIN {$wpdb->prefix}ett_employees e ON w.employee_id = e.id";

if ($status_filter !== 'all') {
    $is_active = ($status_filter === 'active') ? 1 : 0;
    $query .= $wpdb->prepare(" WHERE w.is_active = %d", $is_active);
}

$query .= " ORDER BY w.created_at DESC";

$warnings = $wpdb->get_results($query);

// Get warning statistics
$total_warnings = count($warnings);
$active_warnings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_warnings WHERE is_active = 1");
$dismissed_warnings = $total_warnings - $active_warnings;
?>

<div class="wrap">
    <h1>Warning Management</h1>
    
    <!-- Warning Statistics -->
    <div class="ett-dashboard-stats" style="margin-bottom: 20px;">
        <div class="ett-stat-card">
            <h3>Total Warnings</h3>
            <p class="ett-stat-number"><?php echo $total_warnings; ?></p>
        </div>
        <div class="ett-stat-card">
            <h3>Active Warnings</h3>
            <p class="ett-stat-number" style="color: #dc3545;"><?php echo $active_warnings; ?></p>
        </div>
        <div class="ett-stat-card">
            <h3>Dismissed Warnings</h3>
            <p class="ett-stat-number" style="color: #28a745;"><?php echo $dismissed_warnings; ?></p>
        </div>
    </div>
    
    <!-- Filter Form -->
    <div class="ett-card">
        <form method="get" class="ett-filter-form">
            <input type="hidden" name="page" value="ett-warnings" />
            <label>Status:</label>
            <select name="status">
                <option value="all" <?php selected($status_filter, 'all'); ?>>All Warnings</option>
                <option value="active" <?php selected($status_filter, 'active'); ?>>Active Only</option>
                <option value="dismissed" <?php selected($status_filter, 'dismissed'); ?>>Dismissed Only</option>
            </select>
            <input type="submit" class="button" value="Filter" />
        </form>
    </div>
    
    <!-- Warnings Table -->
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">
                <?php 
                switch($status_filter) {
                    case 'active': echo 'Active Warnings'; break;
                    case 'dismissed': echo 'Dismissed Warnings'; break;
                    default: echo 'All Warnings';
                }
                ?>
                (<?php echo count($warnings); ?> total)
            </h2>
        </div>
        
        <?php if (!empty($warnings)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column">Employee</th>
                        <th scope="col" class="manage-column">Warning Date</th>
                        <th scope="col" class="manage-column">Message</th>
                        <th scope="col" class="manage-column">Status</th>
                        <th scope="col" class="manage-column">Created</th>
                        <th scope="col" class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($warnings as $warning): ?>
                    <tr id="warning-<?php echo $warning->id; ?>">
                        <td>
                            <strong><?php echo $security->escape_html($warning->employee_name); ?></strong><br>
                            <small><?php echo $security->escape_html($warning->employee_code); ?></small>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($warning->warning_date)); ?></td>
                        <td>
                            <div class="warning-message">
                                <?php echo $security->escape_html($warning->warning_message); ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($warning->is_active): ?>
                                <span class="ett-badge ett-badge-danger">Active</span>
                            <?php else: ?>
                                <span class="ett-badge ett-badge-success">Dismissed</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php echo date('M j, Y g:i A', strtotime($warning->created_at)); ?>
                        </td>
                        <td>
                            <?php if ($warning->is_active): ?>
                                <button class="button button-small ett-dismiss-warning" 
                                        data-warning-id="<?php echo $warning->id; ?>">
                                    Dismiss
                                </button>
                            <?php else: ?>
                                <span style="color: #999;">No actions available</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ett-alert ett-alert-info">
                <p>No warnings found for the selected criteria.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    $('.ett-dismiss-warning').click(function() {
        var $btn = $(this);
        var warningId = $btn.data('warning-id');
        
        if (confirm('Are you sure you want to dismiss this warning?')) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'ett_dismiss_warning',
                    warning_id: warningId,
                    nonce: '<?php echo wp_create_nonce('ett_dismiss_warning'); ?>'
                },
                beforeSend: function() {
                    $btn.prop('disabled', true).text('Dismissing...');
                },
                success: function(response) {
                    if (response.success) {
                        $('#warning-' + warningId).fadeOut(function() {
                            $(this).remove();
                        });
                        
                        // Show success message
                        var notice = $('<div class="notice notice-success is-dismissible"><p>Warning dismissed successfully</p></div>');
                        $('.wrap h1').after(notice);
                        
                        setTimeout(function() {
                            notice.fadeOut();
                        }, 3000);
                    } else {
                        alert('Failed to dismiss warning: ' + (response.data || 'Unknown error'));
                        $btn.prop('disabled', false).text('Dismiss');
                    }
                },
                error: function() {
                    alert('Network error occurred');
                    $btn.prop('disabled', false).text('Dismiss');
                }
            });
        }
    });
});
</script>