<?php
/**
 * Admin interface handler
 */

class ETT_Admin {
    
    private $database;
    private $security;
    
    public function __construct($database, $security) {
        $this->database = $database;
        $this->security = $security;
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_menu_page(
            'Employee Tag Tracker',
            'Employee Tag Tracker',
            'manage_options',
            'ett-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-groups',
            30
        );
        
        $submenu_pages = array(
            'ett-daily-chart' => array('Daily Chart', 'Daily Chart', 'daily_chart_page'),
            'ett-employees' => array('Employees', 'Employees', 'employees_page'),
            'ett-tags' => array('Tags', 'Tags', 'tags_page'),
            'ett-assignments' => array('Assignments', 'Assignments', 'assignments_page'),
            'ett-edit-logs' => array('Edit Logs', 'Edit Logs', 'edit_logs_page'),
            'ett-missing-data' => array('Missing Data', 'Missing Data', 'missing_data_page'),
            'ett-warnings' => array('Warning Chart', 'Warning Chart', 'warnings_page'),
            'ett-breaks' => array('Break Management', 'Break Management', 'breaks_management_page'),
            'ett-issues' => array('Issue Management', 'Issue Management', 'issues_management_page')
        );
        
        foreach ($submenu_pages as $slug => $page_data) {
            add_submenu_page(
                'ett-dashboard',
                $page_data[0],
                $page_data[1],
                'manage_options',
                $slug,
                array($this, $page_data[2])
            );
        }
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'ett-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'ett-admin-styles', 
            ETT_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            ETT_PLUGIN_VERSION
        );
        
        wp_enqueue_script(
            'ett-admin-scripts',
            ETT_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery'),
            ETT_PLUGIN_VERSION,
            true
        );
        
        wp_localize_script('ett-admin-scripts', 'ettAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('ett_admin_nonce')
        ));
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        $total_employees = count($this->database->get_all_employees());
        $total_tags = count($this->database->get_all_tags());
        
        global $wpdb;
        $todays_submissions = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ett_submission_status WHERE submission_date = %s",
            date('Y-m-d')
        ));
        $pending_issues = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_issues WHERE issue_status = 'pending'");
        ?>
        <div class="wrap">
            <h1>Employee Tag Tracker Dashboard</h1>
            
            <div style="display:flex;gap:20px;margin:20px 0;">
                <div style="background:#fff;padding:20px;border:1px solid #ddd;flex:1;text-align:center;">
                    <h3>Total Employees</h3>
                    <p style="font-size:36px;font-weight:bold;color:#007cba;"><?php echo $total_employees; ?></p>
                </div>
                <div style="background:#fff;padding:20px;border:1px solid #ddd;flex:1;text-align:center;">
                    <h3>Total Tags</h3>
                    <p style="font-size:36px;font-weight:bold;color:#007cba;"><?php echo $total_tags; ?></p>
                </div>
                <div style="background:#fff;padding:20px;border:1px solid #ddd;flex:1;text-align:center;">
                    <h3>Today's Submissions</h3>
                    <p style="font-size:36px;font-weight:bold;color:#007cba;"><?php echo $todays_submissions; ?></p>
                </div>
                <div style="background:#fff;padding:20px;border:1px solid #ddd;flex:1;text-align:center;">
                    <h3>Pending Issues</h3>
                    <p style="font-size:36px;font-weight:bold;color:#ff6b6b;"><?php echo $pending_issues; ?></p>
                </div>
            </div>
            
            <h2>Quick Links</h2>
            <ul style="font-size:16px;line-height:2;">
                <li><a href="<?php echo admin_url('admin.php?page=ett-daily-chart'); ?>">📊 View Daily Chart</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=ett-employees'); ?>">👥 Manage Employees</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=ett-tags'); ?>">🏷️ Manage Tags</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=ett-assignments'); ?>">📋 Manage Assignments</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=ett-edit-logs'); ?>">✏️ Edit Logs</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=ett-missing-data'); ?>">⚠️ Check Missing Data</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=ett-warnings'); ?>">🚨 Warning Management</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=ett-breaks'); ?>">☕ Break Management</a></li>
                <li><a href="<?php echo admin_url('admin.php?page=ett-issues'); ?>">🎫 Issue Management</a></li>
            </ul>
        </div>
        <?php
    }
    
    // Page methods
    public function daily_chart_page() {
        echo '<div class="wrap"><h1>Daily Chart</h1><p>Feature under development</p></div>';
    }
    
    public function employees_page() {
        // Handle form submission
        if (isset($_POST['add_employee']) && wp_verify_nonce($_POST['_wpnonce'], 'ett_add_employee')) {
            $name = $this->security->sanitize_text($_POST['name']);
            $email = sanitize_email($_POST['email']);
            $employee_code = $this->security->sanitize_text($_POST['employee_code']);
            
            if ($name && $email && $employee_code) {
                if ($this->database->create_employee($name, $email, $employee_code)) {
                    echo '<div class="notice notice-success"><p>Employee added successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Failed to add employee. Employee code or email may already exist.</p></div>';
                }
            }
        }
        
        // Handle deletion
        if (isset($_GET['delete']) && wp_verify_nonce($_GET['_wpnonce'], 'ett_delete_employee')) {
            $employee_id = intval($_GET['delete']);
            if ($this->database->delete_employee($employee_id)) {
                echo '<div class="notice notice-success"><p>Employee deleted successfully!</p></div>';
            }
        }
        
        $employees = $this->database->get_all_employees();
        ?>
        <div class="wrap">
            <h1>Manage Employees</h1>
            
            <h2>Add New Employee</h2>
            <form method="post" style="background:#fff;padding:20px;border:1px solid #ddd;">
                <?php wp_nonce_field('ett_add_employee'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>Full Name</label></th>
                        <td><input type="text" name="name" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label>Email Address</label></th>
                        <td><input type="email" name="email" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label>Employee Code</label></th>
                        <td><input type="text" name="employee_code" class="regular-text" required /></td>
                    </tr>
                </table>
                <input type="submit" name="add_employee" class="button button-primary" value="Add Employee" />
            </form>
            
            <h2>Employee List</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Employee Code</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): ?>
                    <tr>
                        <td><?php echo $employee->id; ?></td>
                        <td><?php echo $this->security->escape_html($employee->name); ?></td>
                        <td><?php echo $this->security->escape_html($employee->email); ?></td>
                        <td><?php echo $this->security->escape_html($employee->employee_code); ?></td>
                        <td>
                            <a href="<?php echo wp_nonce_url(
                                admin_url('admin.php?page=ett-employees&delete=' . $employee->id),
                                'ett_delete_employee'
                            ); ?>" 
                               onclick="return confirm('Are you sure?')" 
                               class="button button-small">Delete</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function tags_page() {
        // Handle form submission
        if (isset($_POST['add_tag']) && wp_verify_nonce($_POST['_wpnonce'], 'ett_add_tag')) {
            $tag_name = $this->security->sanitize_text($_POST['tag_name']);
            $time_minutes = $this->security->sanitize_int($_POST['time_minutes'], 1);
            
            if ($tag_name && $time_minutes) {
                if ($this->database->create_tag($tag_name, $time_minutes)) {
                    echo '<div class="notice notice-success"><p>Tag added successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Failed to add tag.</p></div>';
                }
            }
        }
        
        $tags = $this->database->get_all_tags();
        ?>
        <div class="wrap">
            <h1>Manage Tags</h1>
            
            <h2>Add New Tag</h2>
            <form method="post" style="background:#fff;padding:20px;border:1px solid #ddd;">
                <?php wp_nonce_field('ett_add_tag'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>Tag Name</label></th>
                        <td><input type="text" name="tag_name" class="regular-text" required /></td>
                    </tr>
                    <tr>
                        <th><label>Time (minutes)</label></th>
                        <td><input type="number" name="time_minutes" min="1" class="small-text" required /></td>
                    </tr>
                </table>
                <input type="submit" name="add_tag" class="button button-primary" value="Add Tag" />
            </form>
            
            <h2>Tag List</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Tag Name</th>
                        <th>Time (minutes)</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tags as $tag): ?>
                    <tr>
                        <td><?php echo $tag->id; ?></td>
                        <td><?php echo $this->security->escape_html($tag->tag_name); ?></td>
                        <td><?php echo $tag->time_minutes; ?></td>
                        <td>
                            <button class="button button-small">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    public function assignments_page() {
        // Handle form submission
        if (isset($_POST['add_assignment']) && wp_verify_nonce($_POST['_wpnonce'], 'ett_add_assignment')) {
            $employee_id = intval($_POST['employee_id']);
            $tag_id = intval($_POST['tag_id']);
            $is_mandatory = intval($_POST['is_mandatory']);
            
            if ($employee_id && $tag_id) {
                if ($this->database->create_assignment($employee_id, $tag_id, $is_mandatory)) {
                    echo '<div class="notice notice-success"><p>Assignment created successfully!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Failed to create assignment.</p></div>';
                }
            }
        }
        
        $employees = $this->database->get_all_employees();
        $tags = $this->database->get_all_tags();
        
        global $wpdb;
        $assignments = $wpdb->get_results("
            SELECT a.*, e.name as employee_name, t.tag_name
            FROM {$wpdb->prefix}ett_assignments a
            LEFT JOIN {$wpdb->prefix}ett_employees e ON a.employee_id = e.id
            LEFT JOIN {$wpdb->prefix}ett_tags t ON a.tag_id = t.id
            ORDER BY e.name, t.tag_name
        ");
        ?>
        <div class="wrap">
            <h1>Manage Tag Assignments</h1>
            
            <h2>Assign Tag to Employee</h2>
            <form method="post" style="background:#fff;padding:20px;border:1px solid #ddd;">
                <?php wp_nonce_field('ett_add_assignment'); ?>
                <table class="form-table">
                    <tr>
                        <th><label>Employee</label></th>
                        <td>
                            <select name="employee_id" class="regular-text" required>
                                <option value="">Select Employee</option>
                                <?php foreach ($employees as $employee): ?>
                                    <option value="<?php echo $employee->id; ?>">
                                        <?php echo $this->security->escape_html($employee->name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Tag</label></th>
                        <td>
                            <select name="tag_id" class="regular-text" required>
                                <option value="">Select Tag</option>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?php echo $tag->id; ?>">
                                        <?php echo $this->security->escape_html($tag->tag_name); ?> (<?php echo $tag->time_minutes; ?> min)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label>Type</label></th>
                        <td>
                            <select name="is_mandatory">
                                <option value="0">Optional</option>
                                <option value="1">Mandatory</option>
                            </select>
                        </td>
                    </tr>
                </table>
                <input type="submit" name="add_assignment" class="button button-primary" value="Create Assignment" />
            </form>
            
            <h2>Current Assignments</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Tag</th>
                        <th>Type</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assignments as $assignment): ?>
                    <tr>
                        <td><?php echo $this->security->escape_html($assignment->employee_name); ?></td>
                        <td><?php echo $this->security->escape_html($assignment->tag_name); ?></td>
                        <td>
                            <?php echo $assignment->is_mandatory ? 
                                '<span style="color:red;">Mandatory</span>' : 
                                '<span style="color:green;">Optional</span>'; ?>
                        </td>
                        <td>
                            <button class="button button-small">Delete</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
    
    // Placeholder methods for other pages
    public function edit_logs_page() {
        echo '<div class="wrap"><h1>Edit Logs</h1><p>Feature under development</p></div>';
    }
    
    public function missing_data_page() {
        echo '<div class="wrap"><h1>Missing Data</h1><p>Feature under development</p></div>';
    }
    
    public function warnings_page() {
        echo '<div class="wrap"><h1>Warnings</h1><p>Feature under development</p></div>';
    }
    
    public function breaks_management_page() {
        echo '<div class="wrap"><h1>Break Management</h1><p>Feature under development</p></div>';
    }
    
    public function issues_management_page() {
        echo '<div class="wrap"><h1>Issue Management</h1><p>Feature under development</p></div>';
    }
}