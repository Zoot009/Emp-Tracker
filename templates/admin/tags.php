<?php
/**
 * Tags Management Page
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

// Handle deletion
if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'ett_delete_tag')) {
    $tag_id = intval($_GET['delete']);
    if ($database->delete_tag($tag_id)) {
        echo '<div class="notice notice-success"><p>Tag deleted successfully!</p></div>';
    }
}

$tags = $database->get_all_tags();
?>

<div class="wrap">
    <h1>Manage Tags</h1>
    
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
                        <th scope="col" class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $tag): ?>
                    <tr>
                        <td><?php echo $tag->id; ?></td>
                        <td><strong><?php echo $security->escape_html($tag->tag_name); ?></strong></td>
                        <td><?php echo $tag->time_minutes; ?> minutes</td>
                        <td><?php echo date('M j, Y', strtotime($tag->created_at)); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin.php?page=ett-tags&delete=' . $tag->id),
                                'ett_delete_tag'
                            ); ?>" 
                               onclick="return confirm('Are you sure you want to delete this tag? This will affect all assignments and logs.')" 
                               class="button button-small button-link-delete">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No tags found. Add your first tag above.</p>
        <?php endif; ?>
    </div>
</div>