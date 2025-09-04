<?php
/**
 * Updated Tags Management Page with Working Delete
 * File: templates/admin/tags.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

// Handle form submission
if (isset($_POST['add_tag']) && wp_verify_nonce($_POST['_wpnonce'], 'ett_add_tag')) {
    $tag_name = $security->sanitize_text($_POST['tag_name']);
    $time_minutes = $security->sanitize_int($_POST['time_minutes'], 1);
    
    if ($tag_name && $time_minutes) {
        if ($database->create_tag($tag_name, $time_minutes)) {
            echo '<div class="notice notice-success"><p>Tag added successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to add tag.</p></div>';
        }
    }
}

$tags = $database->get_all_tags();
?>

<div class="wrap">
    <h1>Manage Tags</h1>
    
    <!-- Hidden nonce fields for AJAX -->
    <input type="hidden" id="ett_delete_tag_nonce" value="<?php echo wp_create_nonce('ett_delete_tag'); ?>" />
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Add New Tag</h2>
        </div>
        
        <form method="post" class="ett-admin-form">
            <?php wp_nonce_field('ett_add_tag'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="tag_name">Tag Name</label></th>
                    <td>
                        <input type="text" id="tag_name" name="tag_name" class="regular-text" required />
                        <p class="description">Name of the work activity (e.g., "Email Processing", "Data Entry")</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="time_minutes">Time per Unit (minutes)</label></th>
                    <td>
                        <input type="number" id="time_minutes" name="time_minutes" min="1" max="480" class="small-text" required />
                        <p class="description">How many minutes each count of this activity takes</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="add_tag" class="button button-primary" value="Add Tag" />
            </p>
        </form>
    </div>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Existing Tags (<?php echo count($tags); ?> total)</h2>
        </div>
        
        <?php if (!empty($tags)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column">ID</th>
                        <th scope="col" class="manage-column">Tag Name</th>
                        <th scope="col" class="manage-column">Time per Unit</th>
                        <th scope="col" class="manage-column">Created</th>
                        <th scope="col" class="manage-column">Usage</th>
                        <th scope="col" class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $tag): 
                        global $wpdb;
                        
                        // Check usage
                        $assignments_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}ett_assignments WHERE tag_id = %d",
                            $tag->id
                        ));
                        
                        $logs_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$wpdb->prefix}ett_logs WHERE tag_id = %d",
                            $tag->id
                        ));
                        
                        $can_delete = ($assignments_count == 0 && $logs_count == 0);
                    ?>
                    <tr id="tag-row-<?php echo $tag->id; ?>">
                        <td><?php echo $tag->id; ?></td>
                        <td><strong><?php echo $security->escape_html($tag->tag_name); ?></strong></td>
                        <td><?php echo $tag->time_minutes; ?> minutes</td>
                        <td><?php echo date('M j, Y', strtotime($tag->created_at)); ?></td>
                        <td>
                            <?php if ($assignments_count > 0 || $logs_count > 0): ?>
                                <span class="ett-badge ett-badge-warning">
                                    <?php echo $assignments_count; ?> assignments, <?php echo $logs_count; ?> logs
                                </span>
                            <?php else: ?>
                                <span class="ett-badge ett-badge-success">Not in use</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($can_delete): ?>
                                <button class="button button-small button-link-delete delete-tag-btn" 
                                        data-tag-id="<?php echo $tag->id; ?>"
                                        data-tag-name="<?php echo $security->escape_attr($tag->tag_name); ?>">
                                    Delete
                                </button>
                            <?php else: ?>
                                <span style="color: #999;" title="Cannot delete - tag is in use">Cannot Delete</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ett-alert ett-alert-info">
                <p>No tags found. Add your first tag above.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.ett-badge {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 500;
    text-transform: uppercase;
}

.ett-badge-success {
    background: #d4edda;
    color: #155724;
}

.ett-badge-warning {
    background: #fff3cd;
    color: #856404;
}

.ett-badge-info {
    background: #d1ecf1;
    color: #0c5460;
}
</style>