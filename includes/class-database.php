<?php
/**
 * COMPLETELY FIXED DATABASE CLASS
 * File: includes/class-database.php
 */

class ETT_Database {
    
    private $wpdb;
    private $last_error = '';
    private $is_connected = false;
    private $connection_validated = false;
    
    public function __construct() {
        global $wpdb;
        
        // Ensure we have a valid wpdb instance
        if (!$wpdb || !is_object($wpdb)) {
            $this->last_error = 'WordPress database object not available';
            $this->is_connected = false;
            error_log('ETT Database Error: WordPress database object not available');
            return;
        }
        
        $this->wpdb = $wpdb;
        
        // Force immediate connection validation
        $this->is_connected = $this->validate_connection();
        
        if ($this->is_connected) {
            // Set timezone for IST
            $this->set_timezone();
        } else {
            error_log('ETT Database Error: Connection validation failed - ' . $this->last_error);
        }
    }
    
    /**
     * Enhanced connection validation with multiple checks
     */
    public function validate_connection() {
        try {
            // Check 1: Ensure wpdb exists and is object
            if (!$this->wpdb || !is_object($this->wpdb)) {
                $this->last_error = 'WordPress database object not available';
                error_log('ETT Database: wpdb object check failed');
                return false;
            }
            
            // Check 2: Test basic query with error handling
            $result = $this->wpdb->get_var("SELECT 1 as test");
            
            if ($this->wpdb->last_error) {
                $this->last_error = 'Database connection test failed: ' . $this->wpdb->last_error;
                error_log('ETT Database: Basic query failed - ' . $this->wpdb->last_error);
                return false;
            }
            
            if ($result !== '1') {
                $this->last_error = 'Database connection test returned unexpected result: ' . $result;
                error_log('ETT Database: Query returned unexpected result');
                return false;
            }
            
            // Check 3: Test table access capability
            $tables_test = $this->wpdb->get_var("SHOW TABLES LIKE '{$this->wpdb->prefix}users'");
            if ($this->wpdb->last_error) {
                $this->last_error = 'Cannot access database tables: ' . $this->wpdb->last_error;
                error_log('ETT Database: Table access test failed');
                return false;
            }
            
            $this->connection_validated = true;
            error_log('ETT Database: Connection validation successful');
            return true;
            
        } catch (Exception $e) {
            $this->last_error = 'Database validation exception: ' . $e->getMessage();
            error_log('ETT Database Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Force reconnection attempt
     */
    public function reconnect() {
        global $wpdb;
        
        // Reset validation flag
        $this->connection_validated = false;
        
        // Clear any cached errors
        $this->last_error = '';
        
        // Try to get fresh wpdb instance
        if (function_exists('wp_cache_flush')) {
            wp_cache_flush();
        }
        
        $this->wpdb = $wpdb;
        $this->is_connected = $this->validate_connection();
        
        if ($this->is_connected) {
            error_log('ETT Database: Reconnection successful');
        } else {
            error_log('ETT Database: Reconnection failed - ' . $this->last_error);
        }
        
        return $this->is_connected;
    }
    
    /**
     * Set MySQL timezone to IST with error handling
     */
    private function set_timezone() {
        try {
            // Try to set timezone, but don't fail if it doesn't work
            $result = $this->wpdb->query("SET time_zone = '+05:30'");
            if ($result === false && $this->wpdb->last_error) {
                error_log('ETT Database Warning: Could not set timezone - ' . $this->wpdb->last_error);
            } else {
                error_log('ETT Database: Timezone set to IST successfully');
            }
        } catch (Exception $e) {
            error_log('ETT Database Warning: Timezone setting failed - ' . $e->getMessage());
        }
    }
    
    /**
     * Enhanced table creation with better error handling and logging
     */
    public function create_tables() {
        if (!$this->validate_connection()) {
            error_log('ETT Database: Cannot create tables - connection invalid');
            return false;
        }
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Ensure we have dbDelta function
        if (!function_exists('dbDelta')) {
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        }
        
        if (!function_exists('dbDelta')) {
            $this->last_error = 'WordPress dbDelta function not available';
            error_log('ETT Database Error: dbDelta function not available');
            return false;
        }
        
        // Table creation order is important due to relationships
        $tables = array(
            'employees' => $this->get_employees_table_sql($charset_collate),
            'tags' => $this->get_tags_table_sql($charset_collate),
            'assignments' => $this->get_assignments_table_sql($charset_collate),
            'logs' => $this->get_logs_table_sql($charset_collate),
            'warnings' => $this->get_warnings_table_sql($charset_collate),
            'submission_status' => $this->get_submission_status_table_sql($charset_collate),
            'breaks' => $this->get_breaks_table_sql($charset_collate),
            'issues' => $this->get_issues_table_sql($charset_collate)
        );
        
        $success = true;
        $created_tables = array();
        
        foreach ($tables as $table_name => $sql) {
            try {
                error_log("ETT Database: Creating table {$table_name}");
                error_log("ETT Database: SQL for {$table_name}: " . substr($sql, 0, 200) . "...");
                
                $result = dbDelta($sql);
                
                if (is_array($result)) {
                    error_log("ETT Database: dbDelta result for {$table_name}: " . print_r($result, true));
                }
                
                // Verify table was actually created
                $table_full_name = $this->wpdb->prefix . 'ett_' . $table_name;
                $table_exists = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_full_name}'");
                
                if ($this->wpdb->last_error) {
                    error_log("ETT Database Error checking table {$table_name}: " . $this->wpdb->last_error);
                    $this->last_error = "Error checking table {$table_name}: " . $this->wpdb->last_error;
                    $success = false;
                    continue;
                }
                
                if ($table_exists === $table_full_name) {
                    $created_tables[] = $table_name;
                    error_log("ETT Database: Successfully verified {$table_name} table exists");
                } else {
                    $this->last_error = "Failed to create table: {$table_name}";
                    $success = false;
                    error_log("ETT Database Error: Table {$table_name} was not created properly");
                    
                    // Try manual creation as fallback
                    error_log("ETT Database: Attempting manual creation for {$table_name}");
                    $manual_result = $this->wpdb->query($sql);
                    if ($manual_result !== false) {
                        // Re-check if table exists now
                        $table_exists_retry = $this->wpdb->get_var("SHOW TABLES LIKE '{$table_full_name}'");
                        if ($table_exists_retry === $table_full_name) {
                            $created_tables[] = $table_name;
                            error_log("ETT Database: Manual creation successful for {$table_name}");
                        }
                    }
                }
                
            } catch (Exception $e) {
                $this->last_error = "Exception creating table {$table_name}: " . $e->getMessage();
                $success = false;
                error_log("ETT Database Exception: {$table_name} - " . $e->getMessage());
            }
        }
        
        // Final verification
        $required_tables = array_keys($tables);
        $missing_tables = array_diff($required_tables, $created_tables);
        
        if (!empty($missing_tables)) {
            $this->last_error = "Failed to create tables: " . implode(', ', $missing_tables);
            $success = false;
            error_log("ETT Database Error: Missing tables: " . implode(', ', $missing_tables));
        }
        
        if ($success) {
            error_log("ETT Database: All tables created successfully");
        } else {
            error_log("ETT Database Error: Table creation failed - " . $this->last_error);
        }
        
        return $success;
    }
    
    /**
     * Enhanced CRUD operations with proper error handling
     */
    public function create_employee($name, $email, $employee_code) {
        if (!$this->validate_connection()) {
            $this->last_error = 'Database not connected';
            error_log('ETT Database: Cannot create employee - not connected');
            return false;
        }
        
        if (empty($name) || empty($email) || empty($employee_code)) {
            $this->last_error = 'All fields are required';
            error_log('ETT Database: Cannot create employee - missing required fields');
            return false;
        }
        
        // Sanitize inputs
        $name = sanitize_text_field(trim($name));
        $email = sanitize_email(trim($email));
        $employee_code = sanitize_text_field(trim(strtoupper($employee_code)));
        
        // Validate email
        if (!is_email($email)) {
            $this->last_error = 'Invalid email format';
            error_log('ETT Database: Invalid email format: ' . $email);
            return false;
        }
        
        try {
            // Check for duplicate email
            $existing_email = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->wpdb->prefix}ett_employees WHERE email = %s",
                $email
            ));
            
            if ($this->wpdb->last_error) {
                $this->last_error = 'Database error checking duplicate email: ' . $this->wpdb->last_error;
                error_log('ETT Database Error: ' . $this->last_error);
                return false;
            }
            
            if ($existing_email) {
                $this->last_error = 'Email already exists';
                error_log('ETT Database: Duplicate email attempted: ' . $email);
                return false;
            }
            
            // Check for duplicate employee code
            $existing_code = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->wpdb->prefix}ett_employees WHERE employee_code = %s",
                $employee_code
            ));
            
            if ($this->wpdb->last_error) {
                $this->last_error = 'Database error checking duplicate employee code: ' . $this->wpdb->last_error;
                error_log('ETT Database Error: ' . $this->last_error);
                return false;
            }
            
            if ($existing_code) {
                $this->last_error = 'Employee code already exists';
                error_log('ETT Database: Duplicate employee code attempted: ' . $employee_code);
                return false;
            }
            
            // Insert new employee
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_employees',
                array(
                    'name' => $name,
                    'email' => $email,
                    'employee_code' => $employee_code,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create employee: ' . $this->wpdb->last_error;
                error_log('ETT Database Error creating employee: ' . $this->wpdb->last_error);
                return false;
            }
            
            $employee_id = $this->wpdb->insert_id;
            error_log("ETT Database: Successfully created employee with ID: {$employee_id}");
            return $employee_id;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception creating employee: ' . $e->getMessage();
            error_log('ETT Database Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    public function create_tag($tag_name, $time_minutes) {
        if (!$this->validate_connection()) {
            $this->last_error = 'Database not connected';
            error_log('ETT Database: Cannot create tag - not connected');
            return false;
        }
        
        if (empty($tag_name) || !is_numeric($time_minutes) || $time_minutes <= 0) {
            $this->last_error = 'Invalid tag data - name required and time must be positive number';
            error_log('ETT Database: Invalid tag data - name: ' . $tag_name . ', time: ' . $time_minutes);
            return false;
        }
        
        // Sanitize inputs
        $tag_name = sanitize_text_field(trim($tag_name));
        $time_minutes = intval($time_minutes);
        
        try {
            // Check for duplicate tag name
            $existing_tag = $this->wpdb->get_var($this->wpdb->prepare(
                "SELECT id FROM {$this->wpdb->prefix}ett_tags WHERE tag_name = %s",
                $tag_name
            ));
            
            if ($this->wpdb->last_error) {
                $this->last_error = 'Database error checking duplicate tag: ' . $this->wpdb->last_error;
                error_log('ETT Database Error: ' . $this->last_error);
                return false;
            }
            
            if ($existing_tag) {
                $this->last_error = 'Tag name already exists';
                error_log('ETT Database: Duplicate tag name attempted: ' . $tag_name);
                return false;
            }
            
            // Insert new tag
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_tags',
                array(
                    'tag_name' => $tag_name,
                    'time_minutes' => $time_minutes,
                    'created_at' => current_time('mysql')
                ),
                array('%s', '%d', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create tag: ' . $this->wpdb->last_error;
                error_log('ETT Database Error creating tag: ' . $this->wpdb->last_error);
                return false;
            }
            
            $tag_id = $this->wpdb->insert_id;
            error_log("ETT Database: Successfully created tag with ID: {$tag_id}");
            return $tag_id;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception creating tag: ' . $e->getMessage();
            error_log('ETT Database Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get all employees with enhanced error handling
     */
    public function get_all_employees() {
        if (!$this->validate_connection()) {
            error_log('ETT Database: Cannot get employees - connection invalid');
            return array();
        }
        
        try {
            $results = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}ett_employees ORDER BY name ASC");
            
            if ($this->wpdb->last_error) {
                $this->last_error = 'Error retrieving employees: ' . $this->wpdb->last_error;
                error_log('ETT Database Error getting employees: ' . $this->wpdb->last_error);
                return array();
            }
            
            if ($results === null) {
                error_log('ETT Database: get_all_employees returned null');
                return array();
            }
            
            error_log('ETT Database: Successfully retrieved ' . count($results) . ' employees');
            return $results;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting employees: ' . $e->getMessage();
            error_log('ETT Database Exception: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Get all tags with enhanced error handling
     */
    public function get_all_tags() {
        if (!$this->validate_connection()) {
            error_log('ETT Database: Cannot get tags - connection invalid');
            return array();
        }
        
        try {
            $results = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}ett_tags ORDER BY tag_name ASC");
            
            if ($this->wpdb->last_error) {
                $this->last_error = 'Error retrieving tags: ' . $this->wpdb->last_error;
                error_log('ETT Database Error getting tags: ' . $this->wpdb->last_error);
                return array();
            }
            
            if ($results === null) {
                error_log('ETT Database: get_all_tags returned null');
                return array();
            }
            
            error_log('ETT Database: Successfully retrieved ' . count($results) . ' tags');
            return $results;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting tags: ' . $e->getMessage();
            error_log('ETT Database Exception: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Check if all required tables exist
     */
    public function verify_tables_exist() {
        if (!$this->validate_connection()) {
            return false;
        }
        
        $required_tables = array(
            'ett_employees',
            'ett_tags', 
            'ett_assignments',
            'ett_logs',
            'ett_warnings',
            'ett_submission_status',
            'ett_breaks',
            'ett_issues'
        );
        
        foreach ($required_tables as $table) {
            $table_name = $this->wpdb->prefix . $table;
            $exists = $this->wpdb->get_var("SHOW TABLES LIKE '$table_name'");
            
            if ($this->wpdb->last_error) {
                $this->last_error = "Error checking table {$table}: " . $this->wpdb->last_error;
                error_log('ETT Database Error: ' . $this->last_error);
                return false;
            }
            
            if ($exists !== $table_name) {
                $this->last_error = "Missing required table: $table_name";
                error_log("ETT Database Error: Missing table {$table_name}");
                return false;
            }
        }
        
        error_log('ETT Database: All required tables verified');
        return true;
    }
    
    // Table SQL methods with enhanced error handling
    private function get_employees_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_employees';
        return "CREATE TABLE $table_name (
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
    }
    
    private function get_tags_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_tags';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            tag_name varchar(255) NOT NULL,
            time_minutes int(11) NOT NULL DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY tag_name (tag_name),
            KEY idx_tag_name (tag_name)
        ) $charset_collate;";
    }
    
    private function get_assignments_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_assignments';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            tag_id int(11) NOT NULL,
            is_mandatory tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_tag (employee_id, tag_id),
            KEY idx_employee_id (employee_id),
            KEY idx_tag_id (tag_id),
            KEY idx_mandatory (is_mandatory)
        ) $charset_collate;";
    }
    
    private function get_logs_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_logs';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            tag_id int(11) NOT NULL,
            count int(11) NOT NULL DEFAULT 0,
            total_minutes int(11) NOT NULL DEFAULT 0,
            log_date date NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_tag_date (employee_id, tag_id, log_date),
            KEY idx_employee_date (employee_id, log_date),
            KEY idx_log_date (log_date)
        ) $charset_collate;";
    }
    
    private function get_warnings_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_warnings';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            warning_date date NOT NULL,
            warning_message text,
            is_active tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_employee_active (employee_id, is_active),
            KEY idx_warning_date (warning_date)
        ) $charset_collate;";
    }
    
    private function get_submission_status_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_submission_status';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            submission_date date NOT NULL,
            submission_time datetime DEFAULT CURRENT_TIMESTAMP,
            is_locked tinyint(1) DEFAULT 1,
            total_minutes int(11) DEFAULT 0,
            status_message varchar(255) DEFAULT 'Data submitted successfully',
            PRIMARY KEY (id),
            UNIQUE KEY employee_date (employee_id, submission_date),
            KEY idx_submission_date (submission_date)
        ) $charset_collate;";
    }
    
    private function get_breaks_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_breaks';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            break_date date NOT NULL,
            break_in_time datetime NULL,
            break_out_time datetime NULL,
            break_duration int(11) DEFAULT 0,
            is_active tinyint(1) DEFAULT 0,
            warning_sent tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_employee_date (employee_id, break_date),
            KEY idx_active_breaks (is_active, employee_id)
        ) $charset_collate;";
    }
    
    private function get_issues_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_issues';
        return "CREATE TABLE $table_name (
            id int(11) NOT NULL AUTO_INCREMENT,
            employee_id int(11) NOT NULL,
            issue_category varchar(100) NOT NULL,
            issue_description text NOT NULL,
            issue_status varchar(50) DEFAULT 'pending',
            raised_date datetime DEFAULT CURRENT_TIMESTAMP,
            resolved_date datetime NULL,
            admin_response text NULL,
            days_elapsed int(11) DEFAULT 0,
            PRIMARY KEY (id),
            KEY idx_employee_status (employee_id, issue_status),
            KEY idx_status (issue_status),
            KEY idx_raised_date (raised_date)
        ) $charset_collate;";
    }
    
    // Additional helper methods
    public function get_employee_by_code($employee_code) {
        if (!$this->validate_connection()) {
            return false;
        }
        
        try {
            $result = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}ett_employees WHERE employee_code = %s",
                sanitize_text_field($employee_code)
            ));
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return false;
            }
            
            return $result;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting employee: ' . $e->getMessage();
            return false;
        }
    }
    
    public function get_employee_assignments($employee_id) {
        if (!$this->validate_connection()) {
            return array();
        }
        
        try {
            $result = $this->wpdb->get_results($this->wpdb->prepare("
                SELECT a.*, t.tag_name, t.time_minutes
                FROM {$this->wpdb->prefix}ett_assignments a
                LEFT JOIN {$this->wpdb->prefix}ett_tags t ON a.tag_id = t.id
                WHERE a.employee_id = %d
                ORDER BY a.is_mandatory DESC, t.tag_name
            ", intval($employee_id)));
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return array();
            }
            
            return $result ?: array();
            
        } catch (Exception $e) {
            $this->last_error = 'Exception getting employee assignments: ' . $e->getMessage();
            return array();
        }
    }
    
    public function create_assignment($employee_id, $tag_id, $is_mandatory = 0) {
        if (!$this->validate_connection()) {
            return false;
        }
        
        try {
            $result = $this->wpdb->replace(
                $this->wpdb->prefix . 'ett_assignments',
                array(
                    'employee_id' => intval($employee_id),
                    'tag_id' => intval($tag_id),
                    'is_mandatory' => intval($is_mandatory),
                    'created_at' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create assignment: ' . $this->wpdb->last_error;
                return false;
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception creating assignment: ' . $e->getMessage();
            return false;
        }
    }
    
    // Getter methods
    public function get_last_error() {
        if ($this->last_error) {
            return $this->last_error;
        }
        
        return $this->wpdb->last_error ?: 'Unknown database error';
    }
    
    public function is_connected() {
        // Re-validate connection if needed
        if (!$this->connection_validated) {
            $this->is_connected = $this->validate_connection();
        }
        
        return $this->is_connected;
    }
    
    /**
     * Delete operations with proper error handling
     */
    public function delete_employee($id) {
        if (!$this->validate_connection()) {
            return false;
        }
        
        try {
            $result = $this->wpdb->delete(
                $this->wpdb->prefix . 'ett_employees',
                array('id' => intval($id)),
                array('%d')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to delete employee: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $result > 0;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception deleting employee: ' . $e->getMessage();
            return false;
        }
    }
    
    public function delete_tag($id) {
        if (!$this->validate_connection()) {
            return false;
        }
        
        try {
            $result = $this->wpdb->delete(
                $this->wpdb->prefix . 'ett_tags',
                array('id' => intval($id)),
                array('%d')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to delete tag: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $result > 0;
            
        } catch (Exception $e) {
            $this->last_error = 'Exception deleting tag: ' . $e->getMessage();
            return false;
        }
    }
}