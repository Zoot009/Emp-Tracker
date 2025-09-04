<?php
/**
 * FIXED PLUGIN ACTIVATION WITH COMPREHENSIVE DATABASE DEBUGGING
 * Add this to your main plugin file: employee-tag-tracker.php
 */

// Enhanced activation hook with detailed logging
register_activation_hook(__FILE__, 'ett_enhanced_activation_with_debugging');

function ett_enhanced_activation_with_debugging() {
    global $wpdb;
    
    // Enable WordPress debugging for this activation
    if (!defined('WP_DEBUG')) {
        define('WP_DEBUG', true);
    }
    if (!defined('WP_DEBUG_LOG')) {
        define('WP_DEBUG_LOG', true);
    }
    
    error_log('=== ETT PLUGIN ACTIVATION START ===');
    
    // Step 1: Environment validation
    if (!defined('ABSPATH')) {
        wp_die('Invalid WordPress environment');
    }
    
    // Step 2: Version checks
    if (version_compare(get_bloginfo('version'), '5.0', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Employee Tag Tracker requires WordPress 5.0 or higher. Current version: ' . get_bloginfo('version'));
    }
    
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die('Employee Tag Tracker requires PHP 7.4 or higher. Current version: ' . PHP_VERSION);
    }
    
    // Step 3: Database validation with detailed logging
    if (!$wpdb || !is_object($wpdb)) {
        error_log('ETT Activation Error: WordPress database object not available');
        wp_die('WordPress database connection not available');
    }
    
    // Test basic database connectivity
    $test_query = $wpdb->get_var("SELECT 1");
    if ($wpdb->last_error) {
        error_log('ETT Activation Error: Database test query failed - ' . $wpdb->last_error);
        wp_die('Database connection test failed: ' . $wpdb->last_error);
    }
    
    if ($test_query !== '1') {
        error_log('ETT Activation Error: Database test returned unexpected value: ' . $test_query);
        wp_die('Database connection test returned unexpected result');
    }
    
    error_log('ETT Activation: Basic database tests passed');
    
    // Step 4: Load autoloader and classes
    if (!class_exists('ETT_Database')) {
        require_once plugin_dir_path(__FILE__) . 'includes/class-autoloader.php';
        ETT_Autoloader::init();
    }
    
    // Step 5: Create database instance with enhanced debugging
    try {
        error_log('ETT Activation: Creating database instance');
        $database = new ETT_Database();
        
        if (!$database->is_connected()) {
            $error = $database->get_last_error();
            error_log('ETT Activation Error: Database instance not connected - ' . $error);
            wp_die('Database instance validation failed: ' . $error);
        }
        
        error_log('ETT Activation: Database instance created successfully');
        
        // Step 6: Create tables with detailed logging
        error_log('ETT Activation: Starting table creation');
        
        if ($database->create_tables()) {
            error_log('ETT Activation: Tables creation method returned true');
            
            // Verify all tables were actually created
            if ($database->verify_tables_exist()) {
                error_log('ETT Activation: All tables verified as existing');
                
                // Step 7: Insert sample data safely
                $sample_result = ett_insert_sample_data_enhanced();
                if ($sample_result) {
                    error_log('ETT Activation: Sample data inserted successfully');
                } else {
                    error_log('ETT Activation Warning: Sample data insertion failed');
                }
                
                // Step 8: Update options
                update_option('ett_db_version', ETT_DB_VERSION);
                update_option('ett_plugin_activated', time());
                update_option('ett_activation_check', array(
                    'time' => time(),
                    'version' => ETT_PLUGIN_VERSION,
                    'db_version' => ETT_DB_VERSION,
                    'tables_verified' => true,
                    'sample_data' => $sample_result
                ));
                
                // Clear any cached data
                if (function_exists('wp_cache_flush')) {
                    wp_cache_flush();
                }
                
                error_log('ETT Activation: Plugin activated successfully');
                error_log('=== ETT PLUGIN ACTIVATION SUCCESS ===');
                
            } else {
                $error = $database->get_last_error();
                error_log('ETT Activation Error: Table verification failed - ' . $error);
                wp_die('Table creation verification failed: ' . $error);
            }
        } else {
            $error = $database->get_last_error();
            error_log('ETT Activation Error: Table creation failed - ' . $error);
            wp_die('Failed to create database tables: ' . $error);
        }
        
    } catch (Exception $e) {
        error_log('ETT Activation Exception: ' . $e->getMessage());
        error_log('ETT Activation Exception Stack: ' . $e->getTraceAsString());
        wp_die('Plugin activation failed with exception: ' . $e->getMessage());
    }
}

/**
 * Enhanced sample data insertion with comprehensive error handling
 */
function ett_insert_sample_data_enhanced() {
    global $wpdb;
    
    try {
        error_log('ETT Sample Data: Starting insertion');
        
        // Check if employees table exists
        $employees_table = $wpdb->prefix . 'ett_employees';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$employees_table'");
        
        if (!$table_exists) {
            error_log('ETT Sample Data Error: Employees table does not exist');
            return false;
        }
        
        // Only insert if tables are empty
        $employee_count = $wpdb->get_var("SELECT COUNT(*) FROM $employees_table");
        
        if ($wpdb->last_error) {
            error_log('ETT Sample Data Error: Could not check employee count - ' . $wpdb->last_error);
            return false;
        }
        
        error_log("ETT Sample Data: Current employee count: $employee_count");
        
        if ($employee_count == 0) {
            error_log('ETT Sample Data: No existing employees, inserting sample data');
            
            // Insert sample employees with enhanced validation
            $sample_employees = array(
                array(
                    'name' => 'John Doe',
                    'email' => 'john.doe@company.com',
                    'employee_code' => 'EMP001'
                ),
                array(
                    'name' => 'Jane Smith', 
                    'email' => 'jane.smith@company.com',
                    'employee_code' => 'EMP002'
                )
            );
            
            foreach ($sample_employees as $employee) {
                error_log('ETT Sample Data: Inserting employee - ' . $employee['name']);
                
                $result = $wpdb->insert(
                    $employees_table,
                    array(
                        'name' => $employee['name'],
                        'email' => $employee['email'],
                        'employee_code' => $employee['employee_code'],
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s')
                );
                
                if ($result === false) {
                    error_log('ETT Sample Data Error: Failed to insert employee ' . $employee['name'] . ' - ' . $wpdb->last_error);
                    return false;
                }
                
                $employee_id = $wpdb->insert_id;
                error_log("ETT Sample Data: Successfully inserted employee {$employee['name']} with ID: $employee_id");
            }
            
            // Insert sample tags with validation
            $tags_table = $wpdb->prefix . 'ett_tags';
            $sample_tags = array(
                array('tag_name' => 'Email Processing', 'time_minutes' => 5),
                array('tag_name' => 'Data Entry', 'time_minutes' => 10),
                array('tag_name' => 'Customer Support', 'time_minutes' => 15)
            );
            
            foreach ($sample_tags as $tag) {
                error_log('ETT Sample Data: Inserting tag - ' . $tag['tag_name']);
                
                $result = $wpdb->insert(
                    $tags_table,
                    array(
                        'tag_name' => $tag['tag_name'],
                        'time_minutes' => $tag['time_minutes'],
                        'created_at' => current_time('mysql')
                    ),
                    array('%s', '%d', '%s')
                );
                
                if ($result === false) {
                    error_log('ETT Sample Data Error: Failed to insert tag ' . $tag['tag_name'] . ' - ' . $wpdb->last_error);
                    return false;
                }
                
                $tag_id = $wpdb->insert_id;
                error_log("ETT Sample Data: Successfully inserted tag {$tag['tag_name']} with ID: $tag_id");
            }
            
            error_log('ETT Sample Data: All sample data inserted successfully');
            return true;
        } else {
            error_log('ETT Sample Data: Data already exists, skipping insertion');
            return true;
        }
        
    } catch (Exception $e) {
        error_log('ETT Sample Data Exception: ' . $e->getMessage());
        return false;
    }
}

/**
 * Manual table creation function for troubleshooting
 */
function ett_manual_table_creation() {
    global $wpdb;
    
    error_log('ETT Manual: Starting manual table creation');
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // Create employees table manually
    $employees_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_employees (
        id int(11) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        employee_code varchar(50) NOT NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY email (email),
        UNIQUE KEY employee_code (employee_code),
        KEY idx_employee_code (employee_code),
        KEY idx_email (email),
        KEY idx_name (name)
    ) $charset_collate;";
    
    $result = $wpdb->query($employees_sql);
    error_log("ETT Manual: Employees table creation result: " . ($result !== false ? 'SUCCESS' : 'FAILED'));
    if ($result === false) {
        error_log("ETT Manual: Employees table error: " . $wpdb->last_error);
    }
    
    // Create tags table manually
    $tags_sql = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}ett_tags (
        id int(11) NOT NULL AUTO_INCREMENT,
        tag_name varchar(255) NOT NULL,
        time_minutes int(11) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY tag_name (tag_name),
        KEY idx_tag_name (tag_name)
    ) $charset_collate;";
    
    $result = $wpdb->query($tags_sql);
    error_log("ETT Manual: Tags table creation result: " . ($result !== false ? 'SUCCESS' : 'FAILED'));
    if ($result === false) {
        error_log("ETT Manual: Tags table error: " . $wpdb->last_error);
    }
    
    // Verify tables exist
    $employees_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ett_employees'");
    $tags_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ett_tags'");
    
    error_log("ETT Manual: Employees table exists: " . ($employees_exists ? 'YES' : 'NO'));
    error_log("ETT Manual: Tags table exists: " . ($tags_exists ? 'YES' : 'NO'));
    
    return ($employees_exists && $tags_exists);
}

/**
 * Add admin notice for database status
 */
add_action('admin_notices', 'ett_database_status_notice');

function ett_database_status_notice() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Only show on ETT admin pages
    if (!isset($_GET['page']) || strpos($_GET['page'], 'ett-') !== 0) {
        return;
    }
    
    try {
        $database = new ETT_Database();
        
        if (!$database->is_connected()) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>Employee Tag Tracker:</strong> Database connection error - ';
            echo esc_html($database->get_last_error());
            echo ' <button onclick="ettDatabaseDebug()" class="button button-small">Debug Info</button>';
            echo '</p></div>';
            
            // Add debug script
            echo '<script>
            function ettDatabaseDebug() {
                var info = "Database Debug Info:\\n";
                info += "WordPress Version: ' . get_bloginfo('version') . '\\n";
                info += "PHP Version: ' . PHP_VERSION . '\\n";
                info += "MySQL Version: Unknown\\n";
                info += "Plugin Version: ' . (defined('ETT_PLUGIN_VERSION') ? ETT_PLUGIN_VERSION : 'Unknown') . '\\n";
                info += "Table Prefix: ' . $GLOBALS['wpdb']->prefix . '\\n";
                alert(info);
            }
            </script>';
            
            return;
        }
        
        // Check if tables exist
        if (!$database->verify_tables_exist()) {
            echo '<div class="notice notice-warning">';
            echo '<p><strong>Employee Tag Tracker:</strong> Some database tables are missing. ';
            echo '<a href="#" onclick="ettRecreateTable()" class="button button-small">Recreate Tables</a>';
            echo '</p></div>';
            
            echo '<script>
            function ettRecreateTable() {
                if (confirm("This will attempt to recreate missing database tables. Continue?")) {
                    window.location.href = "' . admin_url('admin.php?page=ett-dashboard&action=recreate_tables&nonce=' . wp_create_nonce('ett_recreate_tables')) . '";
                }
            }
            </script>';
        }
        
    } catch (Exception $e) {
        echo '<div class="notice notice-error">';
        echo '<p><strong>Employee Tag Tracker:</strong> Database error - ';
        echo esc_html($e->getMessage());
        echo '</p></div>';
    }
}

/**
 * Handle table recreation request
 */
add_action('admin_init', 'ett_handle_recreate_tables');

function ett_handle_recreate_tables() {
    if (!isset($_GET['action']) || $_GET['action'] !== 'recreate_tables') {
        return;
    }
    
    if (!isset($_GET['nonce']) || !wp_verify_nonce($_GET['nonce'], 'ett_recreate_tables')) {
        return;
    }
    
    if (!current_user_can('manage_options')) {
        return;
    }
    
    try {
        $database = new ETT_Database();
        
        if ($database->create_tables()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible">';
                echo '<p><strong>Employee Tag Tracker:</strong> Database tables recreated successfully!</p>';
                echo '</div>';
            });
        } else {
            add_action('admin_notices', function() use ($database) {
                echo '<div class="notice notice-error">';
                echo '<p><strong>Employee Tag Tracker:</strong> Failed to recreate tables - ';
                echo esc_html($database->get_last_error());
                echo '</p></div>';
            });
        }
        
    } catch (Exception $e) {
        add_action('admin_notices', function() use ($e) {
            echo '<div class="notice notice-error">';
            echo '<p><strong>Employee Tag Tracker:</strong> Exception during table recreation - ';
            echo esc_html($e->getMessage());
            echo '</p></div>';
        });
    }
    
    // Redirect to remove the action parameter
    wp_redirect(admin_url('admin.php?page=ett-dashboard'));
    exit;
}