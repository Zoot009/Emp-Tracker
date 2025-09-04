<?php
/**
 * Database operations handler - COMPLETELY FIXED VERSION
 * Improved error handling, connection validation, and IST timezone support
 */

class ETT_Database {
    
    private $wpdb;
    private $last_error = '';
    private $is_connected = false;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        
        // Validate connection immediately
        $this->is_connected = $this->validate_connection();
        
        if ($this->is_connected) {
            // Set timezone for IST
            $this->set_timezone();
        }
    }
    
    /**
     * Set MySQL timezone to IST
     */
    private function set_timezone() {
        try {
            $result = $this->wpdb->query("SET time_zone = '+05:30'");
            if ($result === false) {
                error_log('ETT Database Warning: Could not set timezone to IST');
            }
        } catch (Exception $e) {
            error_log('ETT Database Warning: Failed to set timezone - ' . $e->getMessage());
        }
    }
    
    /**
     * Validate database connection
     */
    public function validate_connection() {
        if (!$this->wpdb || !is_object($this->wpdb)) {
            $this->last_error = 'WordPress database connection not available';
            return false;
        }
        
        // Test connection with a simple query
        $result = $this->wpdb->get_var("SELECT 1");
        
        if ($this->wpdb->last_error) {
            $this->last_error = 'Database connection test failed: ' . $this->wpdb->last_error;
            return false;
        }
        
        if ($result !== '1') {
            $this->last_error = 'Database connection test returned unexpected result';
            return false;
        }
        
        return true;
    }
    
    /**
     * Get last error message
     */
    public function get_last_error() {
        if ($this->last_error) {
            return $this->last_error;
        }
        
        return $this->wpdb->last_error ?: 'Unknown database error';
    }
    
    /**
     * Check if database is connected
     */
    public function is_connected() {
        return $this->is_connected;
    }
    
    /**
     * Create all plugin tables with improved error handling
     */
    public function create_tables() {
        if (!$this->validate_connection()) {
            return false;
        }
        
        $charset_collate = $this->wpdb->get_charset_collate();
        
        // Table creation order is important due to foreign key constraints
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
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        $success = true;
        
        foreach ($tables as $table_name => $sql) {
            try {
                $result = dbDelta($sql);
                
                if (empty($result) || $this->wpdb->last_error) {
                    $this->last_error = "Failed to create table: {$table_name}. Error: " . $this->wpdb->last_error;
                    $success = false;
                    error_log("ETT Database Error: Failed to create {$table_name} table - " . $this->wpdb->last_error);
                } else {
                    error_log("ETT Database: Successfully created/updated {$table_name} table");
                }
            } catch (Exception $e) {
                $this->last_error = "Exception creating table {$table_name}: " . $e->getMessage();
                $success = false;
                error_log("ETT Database Exception: {$table_name} - " . $e->getMessage());
            }
        }
        
        return $success;
    }
    
    /**
     * Drop all plugin tables safely
     */
    public function drop_tables() {
        if (!$this->validate_connection()) {
            return false;
        }
        
        // Drop in reverse order to handle foreign keys
        $tables = array(
            'ett_issues',
            'ett_breaks', 
            'ett_submission_status',
            'ett_warnings',
            'ett_logs',
            'ett_assignments',
            'ett_tags',
            'ett_employees'
        );
        
        $success = true;
        
        foreach ($tables as $table) {
            try {
                $result = $this->wpdb->query("DROP TABLE IF EXISTS {$this->wpdb->prefix}{$table}");
                if ($result === false) {
                    $this->last_error = "Failed to drop table: {$table}";
                    $success = false;
                    error_log("ETT Database Error: Failed to drop {$table}");
                }
            } catch (Exception $e) {
                $this->last_error = "Exception dropping table {$table}: " . $e->getMessage();
                $success = false;
                error_log("ETT Database Exception: Drop {$table} - " . $e->getMessage());
            }
        }
        
        return $success;
    }
    
    /**
     * Get current IST time
     */
    public function get_current_ist_time() {
        try {
            $datetime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            return $datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            error_log('ETT Database: Failed to get IST time - ' . $e->getMessage());
            return current_time('mysql');
        }
    }
    
    /**
     * Employee CRUD operations with improved error handling
     */
    public function create_employee($name, $email, $employee_code) {
        if (!$this->is_connected) {
            $this->last_error = 'Database not connected';
            return false;
        }
        
        try {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_employees',
                array(
                    'name' => sanitize_text_field($name),
                    'email' => sanitize_email($email),
                    'employee_code' => sanitize_text_field($employee_code),
                    'created_at' => $this->get_current_ist_time()
                ),
                array('%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create employee: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
        } catch (Exception $e) {
            $this->last_error = 'Exception creating employee: ' . $e->getMessage();
            return false;
        }
    }
    
    public function get_employee_by_code($employee_code) {
        if (!$this->is_connected) {
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
    
    public function get_employee_by_id($employee_id) {
        if (!$this->is_connected) {
            return false;
        }
        
        try {
            $result = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}ett_employees WHERE id = %d",
                intval($employee_id)
            ));
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return false;
            }
            
            return $result;
        } catch (Exception $e) {
            $this->last_error = 'Exception getting employee by ID: ' . $e->getMessage();
            return false;
        }
    }
    
    public function get_all_employees() {
        if (!$this->is_connected) {
            return array();
        }
        
        try {
            $result = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}ett_employees ORDER BY name");
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return array();
            }
            
            return $result ?: array();
        } catch (Exception $e) {
            $this->last_error = 'Exception getting all employees: ' . $e->getMessage();
            return array();
        }
    }
    
    public function delete_employee($id) {
        if (!$this->is_connected) {
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
    
    /**
     * Tag CRUD operations
     */
    public function create_tag($tag_name, $time_minutes) {
        if (!$this->is_connected) {
            return false;
        }
        
        try {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_tags',
                array(
                    'tag_name' => sanitize_text_field($tag_name),
                    'time_minutes' => intval($time_minutes),
                    'created_at' => $this->get_current_ist_time()
                ),
                array('%s', '%d', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create tag: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
        } catch (Exception $e) {
            $this->last_error = 'Exception creating tag: ' . $e->getMessage();
            return false;
        }
    }
    
    public function get_all_tags() {
        if (!$this->is_connected) {
            return array();
        }
        
        try {
            $result = $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}ett_tags ORDER BY tag_name");
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return array();
            }
            
            return $result ?: array();
        } catch (Exception $e) {
            $this->last_error = 'Exception getting all tags: ' . $e->getMessage();
            return array();
        }
    }
    
    public function delete_tag($id) {
        if (!$this->is_connected) {
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
    
    /**
     * Assignment operations
     */
    public function create_assignment($employee_id, $tag_id, $is_mandatory = 0) {
        if (!$this->is_connected) {
            return false;
        }
        
        try {
            $result = $this->wpdb->replace(
                $this->wpdb->prefix . 'ett_assignments',
                array(
                    'employee_id' => intval($employee_id),
                    'tag_id' => intval($tag_id),
                    'is_mandatory' => intval($is_mandatory),
                    'created_at' => $this->get_current_ist_time()
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
    
    public function get_employee_assignments($employee_id) {
        if (!$this->is_connected) {
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
    
    /**
     * Log operations with transaction support
     */
    public function save_log($employee_id, $tag_id, $count, $log_date) {
        if (!$this->is_connected) {
            return false;
        }
        
        // Start transaction
        $this->wpdb->query('START TRANSACTION');
        
        try {
            // Get tag time
            $tag = $this->wpdb->get_row($this->wpdb->prepare(
                "SELECT time_minutes FROM {$this->wpdb->prefix}ett_tags WHERE id = %d",
                intval($tag_id)
            ));
            
            if (!$tag) {
                throw new Exception('Tag not found');
            }
            
            $total_minutes = intval($count) * intval($tag->time_minutes);
            
            $result = $this->wpdb->replace(
                $this->wpdb->prefix . 'ett_logs',
                array(
                    'employee_id' => intval($employee_id),
                    'tag_id' => intval($tag_id),
                    'count' => intval($count),
                    'total_minutes' => $total_minutes,
                    'log_date' => sanitize_text_field($log_date),
                    'created_at' => $this->get_current_ist_time()
                ),
                array('%d', '%d', '%d', '%d', '%s', '%s')
            );
            
            if ($result === false) {
                throw new Exception('Failed to save log: ' . $this->wpdb->last_error);
            }
            
            // Commit transaction
            $this->wpdb->query('COMMIT');
            return true;
            
        } catch (Exception $e) {
            // Rollback transaction
            $this->wpdb->query('ROLLBACK');
            $this->last_error = $e->getMessage();
            return false;
        }
    }
    
    public function get_logs_by_date($employee_id, $log_date) {
        if (!$this->is_connected) {
            return array();
        }
        
        try {
            $result = $this->wpdb->get_results($this->wpdb->prepare(
                "SELECT * FROM {$this->wpdb->prefix}ett_logs 
                 WHERE employee_id = %d AND log_date = %s",
                intval($employee_id),
                sanitize_text_field($log_date)
            ));
            
            if ($this->wpdb->last_error) {
                $this->last_error = $this->wpdb->last_error;
                return array();
            }
            
            return $result ?: array();
        } catch (Exception $e) {
            $this->last_error = 'Exception getting logs by date: ' . $e->getMessage();
            return array();
        }
    }
    
    /**
     * Break operations with improved validation
     */
    public function start_break($employee_id) {
        if (!$this->is_connected) {
            return false;
        }
        
        $current_date = date('Y-m-d');
        $current_time = $this->get_current_ist_time();
        
        try {
            // Check if already on break
            $active_break = $this->wpdb->get_row($this->wpdb->prepare("
                SELECT * FROM {$this->wpdb->prefix}ett_breaks 
                WHERE employee_id = %d AND is_active = 1
            ", intval($employee_id)));
            
            if ($active_break) {
                $this->last_error = 'Employee is already on break';
                return false;
            }
            
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_breaks',
                array(
                    'employee_id' => intval($employee_id),
                    'break_date' => $current_date,
                    'break_in_time' => $current_time,
                    'is_active' => 1,
                    'created_at' => $current_time
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to start break: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
        } catch (Exception $e) {
            $this->last_error = 'Exception starting break: ' . $e->getMessage();
            return false;
        }
    }
    
    public function end_break($employee_id) {
        if (!$this->is_connected) {
            return false;
        }
        
        try {
            $active_break = $this->wpdb->get_row($this->wpdb->prepare("
                SELECT * FROM {$this->wpdb->prefix}ett_breaks 
                WHERE employee_id = %d AND is_active = 1
            ", intval($employee_id)));
            
            if (!$active_break) {
                $this->last_error = 'No active break found';
                return false;
            }
            
            $break_out_time = $this->get_current_ist_time();
            
            try {
                $break_in = new DateTime($active_break->break_in_time);
                $break_out = new DateTime($break_out_time);
                $interval = $break_out->diff($break_in);
                $duration = ($interval->h * 60) + $interval->i;
            } catch (Exception $e) {
                $this->last_error = 'Failed to calculate break duration';
                return false;
            }
            
            $result = $this->wpdb->update(
                $this->wpdb->prefix . 'ett_breaks',
                array(
                    'break_out_time' => $break_out_time,
                    'break_duration' => $duration,
                    'is_active' => 0
                ),
                array('id' => $active_break->id),
                array('%s', '%d', '%d'),
                array('%d')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to end break: ' . $this->wpdb->last_error;
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            $this->last_error = 'Exception ending break: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Issue operations
     */
    public function create_issue($employee_id, $category, $description) {
        if (!$this->is_connected) {
            return false;
        }
        
        try {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_issues',
                array(
                    'employee_id' => intval($employee_id),
                    'issue_category' => sanitize_text_field($category),
                    'issue_description' => sanitize_textarea_field($description),
                    'issue_status' => 'pending',
                    'raised_date' => $this->get_current_ist_time()
                ),
                array('%d', '%s', '%s', '%s', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create issue: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
        } catch (Exception $e) {
            $this->last_error = 'Exception creating issue: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Warning operations
     */
    public function create_warning($employee_id, $message, $warning_date = null) {
        if (!$this->is_connected) {
            return false;
        }
        
        if (!$warning_date) {
            $warning_date = date('Y-m-d');
        }
        
        try {
            $result = $this->wpdb->insert(
                $this->wpdb->prefix . 'ett_warnings',
                array(
                    'employee_id' => intval($employee_id),
                    'warning_date' => sanitize_text_field($warning_date),
                    'warning_message' => sanitize_text_field($message),
                    'is_active' => 1,
                    'created_at' => $this->get_current_ist_time()
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
            
            if ($result === false) {
                $this->last_error = 'Failed to create warning: ' . $this->wpdb->last_error;
                return false;
            }
            
            return $this->wpdb->insert_id;
        } catch (Exception $e) {
            $this->last_error = 'Exception creating warning: ' . $e->getMessage();
            return false;
        }
    }
    
    /**
     * Table creation methods with proper indexes (no foreign keys for WordPress compatibility)
     */
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
            KEY idx_email (email)
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
}