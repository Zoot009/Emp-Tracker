<?php
/**
 * Employee Management Page
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

// Handle form submission
if (isset($_POST['add_employee']) && wp_verify_nonce($_POST['_wpnonce'], 'ett_add_employee')) {
    $name = $security->sanitize_text($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $employee_code = $security->sanitize_text($_POST['employee_code']);
    
    if ($name && $email && $employee_code) {
        if ($database->create_employee($name, $email, $employee_code)) {
            echo '<div class="notice notice-success"><p>Employee added successfully!</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Failed to add employee. Employee code or email may already exist.</p></div>';
        }
    }
}

// Handle deletion
if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'ett_delete_employee')) {
    $employee_id = intval($_GET['delete']);
    if ($database->delete_employee($employee_id)) {
        echo '<div class="notice notice-success"><p>Employee deleted successfully!</p></div>';
    }
}

$employees = $database->get_all_employees();
?>

<div class="wrap">
    <h1>Manage Employees</h1>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Add New Employee</h2>
        </div>
        
        <form method="post" class="ett-admin-form">
            <?php wp_nonce_field('ett_add_employee'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="name">Full Name</label></th>
                    <td><input type="text" id="name" name="name" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="email">Email Address</label></th>
                    <td><input type="email" id="email" name="email" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="employee_code">Employee Code</label></th>
                    <td>
                        <input type="text" id="employee_code" name="employee_code" class="regular-text" required />
                        <p class="description">Unique identifier for employee login</p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="add_employee" class="button button-primary" value="Add Employee" />
            </p>
        </form>
    </div>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Employee List (<?php echo count($employees); ?> total)</h2>
        </div>
        
        <?php if (!empty($employees)): ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th scope="col" class="manage-column">ID</th>
                        <th scope="col" class="manage-column">Name</th>
                        <th scope="col" class="manage-column">Email</th>
                        <th scope="col" class="manage-column">Employee Code</th>
                        <th scope="col" class="manage-column">Created</th>
                        <th scope="col" class="manage-column">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><?php echo $employee->id; ?></td>
                        <td><strong><?php echo $security->escape_html($employee->name); ?></strong></td>
                        <td><?php echo $security->escape_html($employee->email); ?></td>
                        <td><code><?php echo $security->escape_html($employee->employee_code); ?></code></td>
                        <td><?php echo date('M j, Y', strtotime($employee->created_at)); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin.php?page=ett-employees&delete=' . $employee->id),
                                'ett_delete_employee'
                            ); ?>" 
                               onclick="return confirm('Are you sure you want to delete this employee? This will also delete all their logs.')" 
                               class="button button-small button-link-delete">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No employees found. Add your first employee above.</p>
        <?php endif; ?>
    </div>
</div>