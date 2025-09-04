<?php
/**
 * FIXED EMPLOYEES ADMIN TEMPLATE
 * File: templates/admin/employees.php
 */

if (!defined('ABSPATH')) {
    exit;
}

$plugin = ETT_Plugin::get_instance();
$database = $plugin->get_database();
$security = $plugin->get_security();

// Debug: Check if database is connected
if (!$database->is_connected()) {
    echo '<div class="notice notice-error"><p><strong>Database Error:</strong> ' . esc_html($database->get_last_error()) . '</p></div>';
    echo '<p><a href="' . admin_url('admin.php?page=ett-debug') . '">Go to Debug Page</a></p>';
    return;
}

$message = '';
$message_type = '';

// Handle form submission with enhanced error handling
if (isset($_POST['add_employee']) && wp_verify_nonce($_POST['_wpnonce'], 'ett_add_employee')) {
    $name = $security->sanitize_text($_POST['name']);
    $email = sanitize_email($_POST['email']);
    $employee_code = $security->sanitize_text($_POST['employee_code']);
    
    // Enhanced validation
    if (empty($name)) {
        $message = 'Name is required';
        $message_type = 'error';
    } elseif (empty($email) || !is_email($email)) {
        $message = 'Valid email is required';
        $message_type = 'error';
    } elseif (empty($employee_code)) {
        $message = 'Employee code is required';
        $message_type = 'error';
    } else {
        // Attempt to create employee
        $result = $database->create_employee($name, $email, $employee_code);
        
        if ($result) {
            $message = "Employee '{$name}' added successfully with ID: {$result}";
            $message_type = 'success';
            
            // Log success
            error_log("ETT Admin: Successfully added employee - Name: {$name}, Email: {$email}, Code: {$employee_code}, ID: {$result}");
        } else {
            $error = $database->get_last_error();
            $message = 'Failed to add employee: ' . $error;
            $message_type = 'error';
            
            // Log failure
            error_log("ETT Admin: Failed to add employee - Name: {$name}, Email: {$email}, Code: {$employee_code}, Error: {$error}");
        }
    }
}

// Handle deletion with proper validation
if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'ett_delete_employee')) {
    $employee_id = intval($_GET['delete']);
    
    if ($employee_id > 0) {
        if ($database->delete_employee($employee_id)) {
            $message = 'Employee deleted successfully';
            $message_type = 'success';
        } else {
            $message = 'Failed to delete employee: ' . $database->get_last_error();
            $message_type = 'error';
        }
    } else {
        $message = 'Invalid employee ID';
        $message_type = 'error';
    }
}

// Get employees with error handling
$employees = $database->get_all_employees();
if ($employees === false) {
    $message = 'Failed to retrieve employees: ' . $database->get_last_error();
    $message_type = 'error';
    $employees = array();
}

// Display message
if (!empty($message)) {
    echo '<div class="notice notice-' . $message_type . '"><p>' . esc_html($message) . '</p></div>';
}
?>

<div class="wrap">
    <h1>Manage Employees</h1>
    
    <!-- Debug Info (removable in production) -->
    <div class="ett-debug-info" style="background:#f0f8ff; padding:10px; border-radius:4px; margin:10px 0;">
        <strong>Debug Info:</strong>
        Database Connected: <?php echo $database->is_connected() ? '✓ Yes' : '✗ No'; ?> |
        Current Employees: <?php echo count($employees); ?> |
        Last Error: <?php echo $database->get_last_error() ?: 'None'; ?>
    </div>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Add New Employee</h2>
        </div>
        
        <form method="post" class="ett-admin-form">
            <?php wp_nonce_field('ett_add_employee'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="name">Full Name *</label></th>
                    <td>
                        <input type="text" 
                               id="name" 
                               name="name" 
                               class="regular-text" 
                               required 
                               value="<?php echo isset($_POST['name']) ? esc_attr($_POST['name']) : ''; ?>" />
                        <p class="description">Enter the employee's full name</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="email">Email Address *</label></th>
                    <td>
                        <input type="email" 
                               id="email" 
                               name="email" 
                               class="regular-text" 
                               required 
                               value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>" />
                        <p class="description">Must be a valid email address</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="employee_code">Employee Code *</label></th>
                    <td>
                        <input type="text" 
                               id="employee_code" 
                               name="employee_code" 
                               class="regular-text" 
                               required 
                               value="<?php echo isset($_POST['employee_code']) ? esc_attr($_POST['employee_code']) : ''; ?>" />
                        <p class="description">Unique identifier for employee login (e.g., EMP001)</p>
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
                        <td><?php echo esc_html($employee->id); ?></td>
                        <td><strong><?php echo esc_html($employee->name); ?></strong></td>
                        <td><?php echo esc_html($employee->email); ?></td>
                        <td><code><?php echo esc_html($employee->employee_code); ?></code></td>
                        <td><?php echo date('M j, Y', strtotime($employee->created_at)); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin.php?page=ett-employees&delete=' . $employee->id),
                                'ett_delete_employee'
                            ); ?>" 
                               onclick="return confirm('Are you sure you want to delete <?php echo esc_js($employee->name); ?>? This will also delete all their logs.')" 
                               class="button button-small button-link-delete">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="ett-alert ett-alert-info">
                <p>No employees found. Add your first employee above.</p>
                <p><em>If you just added an employee and don't see it here, there may be a database issue.</em></p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Test Section -->
    <div class="ett-card" style="background:#fffacd; border:1px solid #ffd700;">
        <div class="ett-card-header">
            <h3>Quick Database Test</h3>
        </div>
        <p>
            <button onclick="ettTestDatabase()" class="button">Test Database Connection</button>
            <button onclick="ettRefreshPage()" class="button">Refresh Page</button>
        </p>
        <div id="ett-test-results"></div>
    </div>
</div>

<script>
function ettTestDatabase() {
    var resultsDiv = document.getElementById('ett-test-results');
    resultsDiv.innerHTML = 'Testing...';
    
    fetch(ajaxurl, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=ett_test_connection'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            resultsDiv.innerHTML = '<div style="color:green;"><strong>✓ Database Test Results:</strong><br>' +
                'Connection: ' + (data.data.connection ? 'OK' : 'FAILED') + '<br>' +
                'Tables Exist: ' + (data.data.tables_exist ? 'OK' : 'FAILED') + '<br>' +
                'Employees: ' + data.data.employee_count + '<br>' +
                'Tags: ' + data.data.tag_count + '</div>';
        } else {
            resultsDiv.innerHTML = '<div style="color:red;"><strong>✗ Test Failed:</strong> ' + data.data + '</div>';
        }
    })
    .catch(error => {
        resultsDiv.innerHTML = '<div style="color:red;"><strong>✗ Network Error:</strong> ' + error + '</div>';
    });
}

function ettRefreshPage() {
    window.location.reload();
}
</script>

<?php
/**
 * FIXED TAGS ADMIN TEMPLATE
 * File: templates/admin/tags.php
 */
?>

<div class="wrap">
    <h1>Manage Tags</h1>
    
    <!-- Hidden nonce fields for AJAX -->
    <input type="hidden" id="ett_delete_tag_nonce" value="<?php echo wp_create_nonce('ett_delete_tag'); ?>" />
    
    <?php
    // Handle form submission with enhanced error handling
    $message = '';
    $message_type = '';
    
    if (isset($_POST['add_tag']) && wp_verify_nonce($_POST['_wpnonce'], 'ett_add_tag')) {
        $tag_name = $security->sanitize_text($_POST['tag_name']);
        $time_minutes = $security->sanitize_int($_POST['time_minutes'], 1);
        
        // Enhanced validation
        if (empty($tag_name)) {
            $message = 'Tag name is required';
            $message_type = 'error';
        } elseif ($time_minutes <= 0) {
            $message = 'Time per unit must be greater than 0';
            $message_type = 'error';
        } else {
            // Attempt to create tag
            $result = $database->create_tag($tag_name, $time_minutes);
            
            if ($result) {
                $message = "Tag '{$tag_name}' added successfully with ID: {$result}";
                $message_type = 'success';
                
                // Log success
                error_log("ETT Admin: Successfully added tag - Name: {$tag_name}, Time: {$time_minutes}, ID: {$result}");
            } else {
                $error = $database->get_last_error();
                $message = 'Failed to add tag: ' . $error;
                $message_type = 'error';
                
                // Log failure
                error_log("ETT Admin: Failed to add tag - Name: {$tag_name}, Time: {$time_minutes}, Error: {$error}");
            }
        }
    }
    
    // Get tags with error handling
    $tags = $database->get_all_tags();
    if ($tags === false) {
        $message = 'Failed to retrieve tags: ' . $database->get_last_error();
        $message_type = 'error';
        $tags = array();
    }
    
    // Display message
    if (!empty($message)) {
        echo '<div class="notice notice-' . $message_type . '"><p>' . esc_html($message) . '</p></div>';
    }
    ?>
    
    <!-- Debug Info -->
    <div class="ett-debug-info" style="background:#f0f8ff; padding:10px; border-radius:4px; margin:10px 0;">
        <strong>Debug Info:</strong>
        Database Connected: <?php echo $database->is_connected() ? '✓ Yes' : '✗ No'; ?> |
        Current Tags: <?php echo count($tags); ?> |
        Last Error: <?php echo $database->get_last_error() ?: 'None'; ?>
    </div>
    
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Add New Tag</h2>
        </div>
        
        <form method="post" class="ett-admin-form">
            <?php wp_nonce_field('ett_add_tag'); ?>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="tag_name">Tag Name *</label></th>
                    <td>
                        <input type="text" 
                               id="tag_name" 
                               name="tag_name" 
                               class="regular-text" 
                               required 
                               value="<?php echo isset($_POST['tag_name']) ? esc_attr($_POST['tag_name']) : ''; ?>" />
                        <p class="description">Name of the work activity (e.g., "Email Processing", "Data Entry")</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="time_minutes">Time per Unit (minutes) *</label></th>
                    <td>
                        <input type="number" 
                               id="time_minutes" 
                               name="time_minutes" 
                               min="1" 
                               max="480" 
                               class="small-text" 
                               required 
                               value="<?php echo isset($_POST['time_minutes']) ? esc_attr($_POST['time_minutes']) : ''; ?>" />
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
                        <td><?php echo esc_html($tag->id); ?></td>
                        <td><strong><?php echo esc_html($tag->tag_name); ?></strong></td>
                        <td><?php echo esc_html($tag->time_minutes); ?> minutes</td>
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
                                        data-tag-name="<?php echo esc_attr($tag->tag_name); ?>">
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
                <p><em>If you just added a tag and don't see it here, there may be a database issue.</em></p>
            </div>
        <?php endif; ?>
    </div>
</div>