<?php
/**
 * Assignments Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

// Handle form submission
if (isset($_POST['add_assignment']) && wp_verify_nonce($_POST['_wpnonce'], 'ett_add_assignment')) {
    $employee_id = intval($_POST['employee_id']);
    $tag_id = intval($_POST['tag_id']);
    $is_mandatory = intval($_POST['is_mandatory']);
    
    if ($employee_id && $tag_id) {
        if ($database->create_assignment($employee_id, $tag_id, $is_mandatory)) {
            echo '<div class="notice notice-success"><p>Assignment created successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to create assignment.</p></div>';
        }
    }
}

// Handle deletion
if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'ett_delete_assignment')) {
    global $wpdb;
    $assignment_id = intval($_GET['delete']);
    if ($wpdb->delete($wpdb->prefix . 'ett_assignments', array('id' => $assignment_id))) {
        echo '<div class="notice notice-success"><p>Assignment deleted successfully!</p></div>';
    }
}

$employees = $database->get_all_employees();
$tags = $database->get_all_tags();

global $wpdb;
$assignments = $wpdb->get_results("
    SELECT a.*, e.name as employee_name, t.tag_name, t.time_minutes
    FROM {$wpdb->prefix}ett_assignments a
    LEFT JOIN {$wpdb->prefix}ett_employees e ON a.employee_id = e.id
    LEFT JOIN {$wpdb->prefix}ett_tags t ON a.tag_id = t.id
    ORDER BY e.name, t.tag_name
");
?>

<div class="wrap">
    <h1>Manage Tag Assignments</h1>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Assign Tag to Employee</h2>
        </div>
        
        <form method="post" class="ett-admin-form">
            <?php wp_nonce_field('ett_add_assignment'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="employee_id">Employee</label></th>
                    <td>
                        <select id="employee_id" name="employee_id" class="regular-text" required>
                            <option value="">Select Employee</option>
                            <?php foreach ($employees as $employee): ?>
                                <option value="<?php echo $employee->id; ?>">
                                    <?php echo $security->escape_html($employee->name); ?> (<?php echo $security->escape_html($employee->employee_code); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="tag_id">Tag</label></th>
                    <td>
                        <select id="tag_id" name="tag_id" class="regular-text" required>
                            <option value="">Select Tag</option>
                            <?php foreach ($tags as $tag): ?>
                                <option value="<?php echo $tag->id; ?>">
                                    <?php echo $security->escape_html($tag->tag_name); ?> (<?php echo $tag->time_minutes; ?> min)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="is_mandatory">Assignment Type</label></th>
                    <td>
                        <select id="is_mandatory" name="is_mandatory">
                            <option value="0">Optional</option>
                            <option value="1">Mandatory</option>
                        </select>
                        <p class="description">Mandatory tags will trigger warnings if not filled</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="add_assignment" class="button button-primary" value="Create Assignment" />
            </p>
        </form>
    </div>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Current Assignments (<?php echo count($assignments); ?> total)</h2>
        </div>
        
        <?php if (!empty($assignments)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column">Employee</th>
                        <th scope="col" class="manage-column">Tag</th>
                        <th scope="col" class="manage-column">Time per Unit</th>
                        <th scope="col" class="manage-column">Type</th>
                        <th scope="col" class="manage-column">Created</th>
                        <th scope="col" class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><strong><?php echo $security->escape_html($assignment->employee_name); ?></strong></td>
                        <td><?php echo $security->escape_html($assignment->tag_name); ?></td>
                        <td><?php echo $assignment->time_minutes; ?> min</td>
                        <td>
                            <?php if ($assignment->is_mandatory): ?>
                                <span class="ett-badge ett-badge-danger">Mandatory</span>
                            <?php else: ?>
                                <span class="ett-badge ett-badge-success">Optional</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M j, Y', strtotime($assignment->created_at)); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin.php?page=ett-assignments&delete=' . $assignment->id),
                                'ett_delete_assignment'
                            ); ?>" 
                               onclick="return confirm('Are you sure you want to delete this assignment?')" 
                               class="button button-small button-link-delete">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No assignments found. Create your first assignment above.</p>
        <?php endif; ?>
    </div>
</div>