<?php
/**
 * Complete Admin Class Implementation
 * File: includes/class-admin.php
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
        // Main menu page
        add_menu_page(
            'Employee Tag Tracker',
            'Employee Tag Tracker',
            'manage_options',
            'ett-dashboard',
            array($this, 'dashboard_page'),
            'dashicons-groups',
            30
        );
        
        // Submenu pages
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
        
        // Add settings page
        add_submenu_page(
            'ett-dashboard',
            'Settings',
            'Settings',
            'manage_options',
            'ett-settings',
            array($this, 'settings_page')
        );
    }
    
    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'ett-') === false) {
            return;
        }
        
        wp_enqueue_style(
            'ett-admin-styles', 
            ETT_PLUGIN_URL . 'assets/css/admin.css', 
            array(), 
            ETT_PLUGIN_VERSION
        );
        
        wp_enqueue_style(
            'ett-components-styles', 
            ETT_PLUGIN_URL . 'assets/css/components.css', 
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
        
        // Enqueue Chart.js for admin charts
        wp_enqueue_script(
            'chart-js', 
            'https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js', 
            array(), 
            '3.9.1', 
            true
        );
        
        wp_localize_script('ett-admin-scripts', 'ettAdmin', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonces' => array(
                'delete_tag' => wp_create_nonce('ett_delete_tag'),
                'delete_employee' => wp_create_nonce('ett_delete_employee'),
                'delete_assignment' => wp_create_nonce('ett_delete_assignment'),
                'update_log' => wp_create_nonce('ett_update_log'),
                'send_warning' => wp_create_nonce('ett_send_warning'),
                'dismiss_warning' => wp_create_nonce('ett_dismiss_warning'),
                'update_issue' => wp_create_nonce('ett_update_issue'),
                'send_break_warning' => wp_create_nonce('ett_send_break_warning')
            ),
            'strings' => array(
                'confirm_delete' => __('Are you sure you want to delete this item?', 'employee-tag-tracker'),
                'deleting' => __('Deleting...', 'employee-tag-tracker'),
                'updating' => __('Updating...', 'employee-tag-tracker'),
                'error' => __('An error occurred', 'employee-tag-tracker'),
                'success' => __('Success', 'employee-tag-tracker')
            )
        ));
    }
    
    /**
     * Dashboard page
     */
    public function dashboard_page() {
        // Check database connection
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/dashboard.php';
    }
    
    /**
     * Daily chart page
     */
    public function daily_chart_page() {
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/daily-chart.php';
    }
    
    /**
     * Employees page
     */
    public function employees_page() {
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/employees.php';
    }
    
    /**
     * Tags page
     */
    public function tags_page() {
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/tags.php';
    }
    
    /**
     * Assignments page
     */
    public function assignments_page() {
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/assignments.php';
    }
    
    /**
     * Edit logs page
     */
    public function edit_logs_page() {
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/edit-logs.php';
    }
    
    /**
     * Missing data page
     */
    public function missing_data_page() {
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/missing-data.php';
    }
    
    /**
     * Warnings page
     */
    public function warnings_page() {
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/warnings.php';
    }
    
    /**
     * Breaks management page
     */
    public function breaks_management_page() {
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/breaks.php';
    }
    
    /**
     * Issues management page
     */
    public function issues_management_page() {
        if (!$this->database->is_connected()) {
            $this->show_database_error();
            return;
        }
        
        include ETT_PLUGIN_PATH . 'templates/admin/issues.php';
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        // Handle form submission
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['_wpnonce'], 'ett_settings')) {
            $this->save_settings();
        }
        
        $this->render_settings_page();
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        $settings = array(
            'delete_data_on_uninstall' => isset($_POST['delete_data_on_uninstall']) ? 1 : 0,
            'email_notifications' => isset($_POST['email_notifications']) ? 1 : 0,
            'notification_email' => sanitize_email($_POST['notification_email'] ?? ''),
            'break_time_limit' => intval($_POST['break_time_limit'] ?? 20),
            'auto_dismiss_warnings' => intval($_POST['auto_dismiss_warnings'] ?? 30),
            'working_days' => array_map('sanitize_text_field', $_POST['working_days'] ?? array()),
            'timezone' => sanitize_text_field($_POST['timezone'] ?? 'Asia/Kolkata')
        );
        
        foreach ($settings as $key => $value) {
            update_option('ett_' . $key, $value);
        }
        
        add_settings_error('ett_settings', 'settings_saved', 'Settings saved successfully', 'updated');
    }
    
    /**
     * Render settings page
     */
    private function render_settings_page() {
        $delete_data = get_option('ett_delete_data_on_uninstall', 0);
        $email_notifications = get_option('ett_email_notifications', 0);
        $notification_email = get_option('ett_notification_email', get_option('admin_email'));
        $break_time_limit = get_option('ett_break_time_limit', 20);
        $auto_dismiss_warnings = get_option('ett_auto_dismiss_warnings', 30);
        $working_days = get_option('ett_working_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'));
        $timezone = get_option('ett_timezone', 'Asia/Kolkata');
        
        ?>
        <div class="wrap">
            <h1>Employee Tag Tracker Settings</h1>
            
            <?php settings_errors('ett_settings'); ?>
            
            <form method="post" action="">
                <?php wp_nonce_field('ett_settings'); ?>
                
                <div class="ett-card">
                    <div class="ett-card-header">
                        <h2 class="ett-card-title">General Settings</h2>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Timezone</th>
                            <td>
                                <select name="timezone">
                                    <option value="Asia/Kolkata" <?php selected($timezone, 'Asia/Kolkata'); ?>>Asia/Kolkata (IST)</option>
                                    <option value="UTC" <?php selected($timezone, 'UTC'); ?>>UTC</option>
                                    <option value="America/New_York" <?php selected($timezone, 'America/New_York'); ?>>America/New_York</option>
                                    <option value="Europe/London" <?php selected($timezone, 'Europe/London'); ?>>Europe/London</option>
                                </select>
                                <p class="description">Select the timezone for your organization</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Working Days</th>
                            <td>
                                <fieldset>
                                    <?php
                                    $days = array(
                                        'monday' => 'Monday',
                                        'tuesday' => 'Tuesday', 
                                        'wednesday' => 'Wednesday',
                                        'thursday' => 'Thursday',
                                        'friday' => 'Friday',
                                        'saturday' => 'Saturday',
                                        'sunday' => 'Sunday'
                                    );
                                    
                                    foreach ($days as $key => $label) {
                                        $checked = in_array($key, $working_days) ? 'checked' : '';
                                        echo '<label><input type="checkbox" name="working_days[]" value="' . $key . '" ' . $checked . '> ' . $label . '</label><br>';
                                    }
                                    ?>
                                    <p class="description">Select which days are considered working days</p>
                                </fieldset>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Break Time Limit</th>
                            <td>
                                <input type="number" name="break_time_limit" value="<?php echo esc_attr($break_time_limit); ?>" min="5" max="120" class="small-text"> minutes
                                <p class="description">Maximum allowed break time before warning</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ett-card">
                    <div class="ett-card-header">
                        <h2 class="ett-card-title">Notification Settings</h2>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Email Notifications</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="email_notifications" value="1" <?php checked($email_notifications, 1); ?>>
                                    Enable weekly email reports
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Notification Email</th>
                            <td>
                                <input type="email" name="notification_email" value="<?php echo esc_attr($notification_email); ?>" class="regular-text">
                                <p class="description">Email address for notifications and reports</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">Auto-dismiss Warnings</th>
                            <td>
                                <input type="number" name="auto_dismiss_warnings" value="<?php echo esc_attr($auto_dismiss_warnings); ?>" min="1" max="365" class="small-text"> days
                                <p class="description">Automatically dismiss warnings older than this many days</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="ett-card">
                    <div class="ett-card-header">
                        <h2 class="ett-card-title">Data Management</h2>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">Uninstall Options</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked($delete_data, 1); ?>>
                                    Delete all plugin data when uninstalling
                                </label>
                                <p class="description"><strong>Warning:</strong> This will permanently delete all employee data, logs, and settings when the plugin is uninstalled.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Save Settings">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Show database error
     */
    private function show_database_error() {
        ?>
        <div class="wrap">
            <h1>Employee Tag Tracker</h1>
            <div class="notice notice-error">
                <p><strong>Database Error:</strong> <?php echo esc_html($this->database->get_last_error()); ?></p>
                <p>Please check your database connection and try again.</p>
            </div>
        </div>
        <?php
    }
    
    /**
     * Add admin bar menu
     */
    public function add_admin_bar_menu($wp_admin_bar) {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $wp_admin_bar->add_menu(array(
            'id' => 'ett-menu',
            'title' => 'ETT Dashboard',
            'href' => admin_url('admin.php?page=ett-dashboard'),
            'meta' => array('class' => 'ett-admin-bar-menu')
        ));
        
        // Add quick access submenu
        $wp_admin_bar->add_menu(array(
            'id' => 'ett-daily-chart',
            'parent' => 'ett-menu',
            'title' => 'Daily Chart',
            'href' => admin_url('admin.php?page=ett-daily-chart')
        ));
        
        $wp_admin_bar->add_menu(array(
            'id' => 'ett-warnings',
            'parent' => 'ett-menu',
            'title' => 'Active Warnings',
            'href' => admin_url('admin.php?page=ett-warnings')
        ));
    }
}