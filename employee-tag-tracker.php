<?php
/**
 * Plugin Name: Employee Tag & Time Tracker
 * Plugin URI: https://example.com/
 * Description: Track employee work time based on tags with mandatory tag warnings, break management, and issue tracking
 * Version: 1.4.0
 * Author: Your Company
 * License: GPL v2 or later
 * Text Domain: employee-tag-tracker
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('ETT_PLUGIN_FILE', __FILE__);
define('ETT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ETT_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('ETT_PLUGIN_VERSION', '1.4.0');
define('ETT_DB_VERSION', '1.4.0');

// Include autoloader
require_once ETT_PLUGIN_PATH . 'includes/class-autoloader.php';

// Initialize autoloader
ETT_Autoloader::init();

/**
 * FIXED: Direct database creation on activation
 */
register_activation_hook(__FILE__, 'ett_create_database_tables_fixed');

function ett_create_database_tables_fixed() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Array to store all table creation queries
    $tables = array();
    
    // Employees table
    $tables['employees'] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_employees (
        id INT(11) NOT NULL AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        employee_code VARCHAR(50) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        UNIQUE KEY employee_code (employee_code)
    ) $charset_collate;";
    
    // Tags table
    $tables['tags'] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_tags (
        id INT(11) NOT NULL AUTO_INCREMENT,
        tag_name VARCHAR(255) NOT NULL,
        time_minutes INT(11) NOT NULL DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    // Assignments table
    $tables['assignments'] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_assignments (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        tag_id INT(11) NOT NULL,
        is_mandatory TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY employee_tag (employee_id, tag_id),
        FOREIGN KEY (employee_id) REFERENCES {$wpdb->prefix}ett_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES {$wpdb->prefix}ett_tags(id) ON DELETE CASCADE
    ) $charset_collate;";
    
    // Logs table
    $tables['logs'] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_logs (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        tag_id INT(11) NOT NULL,
        count INT(11) NOT NULL DEFAULT 0,
        total_minutes INT(11) NOT NULL DEFAULT 0,
        log_date DATE NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY employee_tag_date (employee_id, tag_id, log_date),
        FOREIGN KEY (employee_id) REFERENCES {$wpdb->prefix}ett_employees(id) ON DELETE CASCADE,
        FOREIGN KEY (tag_id) REFERENCES {$wpdb->prefix}ett_tags(id) ON DELETE CASCADE
    ) $charset_collate;";
    
    // Warnings table
    $tables['warnings'] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_warnings (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        warning_date DATE NOT NULL,
        warning_message TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        FOREIGN KEY (employee_id) REFERENCES {$wpdb->prefix}ett_employees(id) ON DELETE CASCADE
    ) $charset_collate;";
    
    // Submission status table
    $tables['submission_status'] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_submission_status (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        submission_date DATE NOT NULL,
        submission_time DATETIME DEFAULT CURRENT_TIMESTAMP,
        is_locked TINYINT(1) DEFAULT 1,
        total_minutes INT(11) DEFAULT 0,
        status_message VARCHAR(255) DEFAULT 'Data submitted successfully',
        PRIMARY KEY (id),
        UNIQUE KEY employee_date (employee_id, submission_date),
        FOREIGN KEY (employee_id) REFERENCES {$wpdb->prefix}ett_employees(id) ON DELETE CASCADE
    ) $charset_collate;";
    
    // Breaks table
    $tables['breaks'] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_breaks (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        break_date DATE NOT NULL,
        break_in_time DATETIME NULL,
        break_out_time DATETIME NULL,
        break_duration INT(11) DEFAULT 0,
        is_active TINYINT(1) DEFAULT 0,
        warning_sent TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY employee_date (employee_id, break_date),
        FOREIGN KEY (employee_id) REFERENCES {$wpdb->prefix}ett_employees(id) ON DELETE CASCADE
    ) $charset_collate;";
    
    // Issues table
    $tables['issues'] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_issues (
        id INT(11) NOT NULL AUTO_INCREMENT,
        employee_id INT(11) NOT NULL,
        issue_category VARCHAR(100) NOT NULL,
        issue_description TEXT NOT NULL,
        issue_status VARCHAR(50) DEFAULT 'pending',
        raised_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        resolved_date DATETIME NULL,
        admin_response TEXT NULL,
        days_elapsed INT(11) DEFAULT 0,
        PRIMARY KEY (id),
        KEY employee_status (employee_id, issue_status),
        FOREIGN KEY (employee_id) REFERENCES {$wpdb->prefix}ett_employees(id) ON DELETE CASCADE
    ) $charset_collate;";
    
    // Load WordPress database upgrade functions
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    
    // Create tables in dependency order (employees first, then dependent tables)
    $creation_order = ['employees', 'tags', 'assignments', 'logs', 'warnings', 'submission_status', 'breaks', 'issues'];
    
    foreach ($creation_order as $table_key) {
        if (isset($tables[$table_key])) {
            $result = dbDelta($tables[$table_key]);
            
            // Log the result for debugging
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("ETT Plugin: Created table {$table_key} - " . print_r($result, true));
            }
        }
    }
    
    // Update database version
    update_option('ett_db_version', ETT_DB_VERSION);
    
    // Insert sample data for testing (only if tables are empty)
    ett_insert_sample_data();
    
    // Log successful creation
    error_log('ETT Plugin: Database tables created successfully with version ' . ETT_DB_VERSION);
}

/**
 * Insert sample data for testing
 */
function ett_insert_sample_data() {
    global $wpdb;
    
    // Check if employees table is empty
    $employee_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ett_employees");
    
    if ($employee_count == 0) {
        // Insert sample employees
        $wpdb->insert(
            $wpdb->prefix . 'ett_employees',
            array(
                'name' => 'John Doe',
                'email' => 'john.doe@company.com',
                'employee_code' => 'EMP001'
            )
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ett_employees',
            array(
                'name' => 'Jane Smith',
                'email' => 'jane.smith@company.com',
                'employee_code' => 'EMP002'
            )
        );
        
        // Insert sample tags
        $wpdb->insert(
            $wpdb->prefix . 'ett_tags',
            array(
                'tag_name' => 'Email Processing',
                'time_minutes' => 5
            )
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ett_tags',
            array(
                'tag_name' => 'Data Entry',
                'time_minutes' => 10
            )
        );
        
        $wpdb->insert(
            $wpdb->prefix . 'ett_tags',
            array(
                'tag_name' => 'Customer Support',
                'time_minutes' => 15
            )
        );
        
        error_log('ETT Plugin: Sample data inserted');
    }
}

/**
 * Database version check and upgrade
 */
function ett_check_database_version() {
    $installed_version = get_option('ett_db_version', '0');
    
    if (version_compare($installed_version, ETT_DB_VERSION, '<')) {
        ett_create_database_tables_fixed();
    }
}

// Check database version on admin init
add_action('admin_init', 'ett_check_database_version');

// Plugin lifecycle hooks
register_deactivation_hook(__FILE__, array('ETT_Plugin', 'deactivate'));
register_uninstall_hook(__FILE__, array('ETT_Plugin', 'uninstall'));

// Initialize plugin AFTER WordPress is fully loaded
add_action('plugins_loaded', array('ETT_Plugin', 'get_instance'));

/**
 * Network activation support
 */
function ett_activate_network($network_wide) {
    if (is_multisite() && $network_wide) {
        $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
        foreach ($blog_ids as $blog_id) {
            switch_to_blog($blog_id);
            ett_create_database_tables_fixed();
            restore_current_blog();
        }
    } else {
        ett_create_database_tables_fixed();
    }
}

register_activation_hook(__FILE__, 'ett_activate_network');