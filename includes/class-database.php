<?php
/**
 * Database operations handler
 */

class ETT_Database {
    
    private $wpdb;
    
    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }
    
    /**
     * Create all plugin tables
     */
    public function create_tables() {
        $charset_collate = $this->wpdb->get_charset_collate();
        
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
        
        foreach ($tables as $table_name => $sql) {
            dbDelta($sql);
        }
    }
    
    /**
     * Drop all plugin tables
     */
    public function drop_tables() {
        $tables = array(
            'ett_employees',
            'ett_tags', 
            'ett_assignments',
            'ett_logs',
            'ett_warnings',
            'ett_submission_status',
            'ett_breaks',
            'ett_issues'
        );
        
        foreach ($tables as $table) {
            $this->wpdb->query("DROP TABLE IF EXISTS {$this->wpdb->prefix}{$table}");
        }
    }
    
    /**
     * Get current IST time
     */
    public function get_current_ist_time() {
        $datetime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
        return $datetime->format('Y-m-d H:i:s');
    }
    
    /**
     * Employee CRUD operations
     */
    public function create_employee($name, $email, $employee_code) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'ett_employees',
            array(
                'name' => sanitize_text_field($name),
                'email' => sanitize_email($email),
                'employee_code' => sanitize_text_field($employee_code)
            ),
            array('%s', '%s', '%s')
        );
    }
    
    public function get_employee_by_code($employee_code) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->wpdb->prefix}ett_employees WHERE employee_code = %s",
            $employee_code
        ));
    }
    
    public function get_all_employees() {
        return $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}ett_employees ORDER BY name");
    }
    
    public function delete_employee($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'ett_employees',
            array('id' => intval($id)),
            array('%d')
        );
    }
    
    /**
     * Tag CRUD operations
     */
    public function create_tag($tag_name, $time_minutes) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'ett_tags',
            array(
                'tag_name' => sanitize_text_field($tag_name),
                'time_minutes' => intval($time_minutes)
            ),
            array('%s', '%d')
        );
    }
    
    public function get_all_tags() {
        return $this->wpdb->get_results("SELECT * FROM {$this->wpdb->prefix}ett_tags ORDER BY tag_name");
    }
    
    public function delete_tag($id) {
        return $this->wpdb->delete(
            $this->wpdb->prefix . 'ett_tags',
            array('id' => intval($id)),
            array('%d')
        );
    }
    
    /**
     * Assignment operations
     */
    public function create_assignment($employee_id, $tag_id, $is_mandatory = 0) {
        return $this->wpdb->replace(
            $this->wpdb->prefix . 'ett_assignments',
            array(
                'employee_id' => intval($employee_id),
                'tag_id' => intval($tag_id),
                'is_mandatory' => intval($is_mandatory)
            ),
            array('%d', '%d', '%d')
        );
    }
    
    public function get_employee_assignments($employee_id) {
        return $this->wpdb->get_results($this->wpdb->prepare("
            SELECT a.*, t.tag_name, t.time_minutes
            FROM {$this->wpdb->prefix}ett_assignments a
            LEFT JOIN {$this->wpdb->prefix}ett_tags t ON a.tag_id = t.id
            WHERE a.employee_id = %d
            ORDER BY a.is_mandatory DESC, t.tag_name
        ", $employee_id));
    }
    
    /**
     * Log operations
     */
    public function save_log($employee_id, $tag_id, $count, $log_date) {
        $tag = $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT time_minutes FROM {$this->wpdb->prefix}ett_tags WHERE id = %d",
            $tag_id
        ));
        
        if (!$tag) return false;
        
        $total_minutes = intval($count) * intval($tag->time_minutes);
        
        return $this->wpdb->replace(
            $this->wpdb->prefix . 'ett_logs',
            array(
                'employee_id' => intval($employee_id),
                'tag_id' => intval($tag_id),
                'count' => intval($count),
                'total_minutes' => $total_minutes,
                'log_date' => sanitize_text_field($log_date)
            ),
            array('%d', '%d', '%d', '%d', '%s')
        );
    }
    
    public function get_logs_by_date($employee_id, $log_date) {
        return $this->wpdb->get_results($this->wpdb->prepare(
            "SELECT tag_id, count FROM {$this->wpdb->prefix}ett_logs 
             WHERE employee_id = %d AND log_date = %s",
            $employee_id,
            $log_date
        ));
    }
    
    /**
     * Break operations
     */
    public function start_break($employee_id) {
        $current_date = date('Y-m-d');
        $current_time = $this->get_current_ist_time();
        
        // Check if already on break
        $active_break = $this->wpdb->get_row($this->wpdb->prepare("
            SELECT * FROM {$this->wpdb->prefix}ett_breaks 
            WHERE employee_id = %d AND is_active = 1
        ", $employee_id));
        
        if ($active_break) {
            return false;
        }
        
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'ett_breaks',
            array(
                'employee_id' => intval($employee_id),
                'break_date' => $current_date,
                'break_in_time' => $current_time,
                'is_active' => 1
            ),
            array('%d', '%s', '%s', '%d')
        );
    }
    
    public function end_break($employee_id) {
        $active_break = $this->wpdb->get_row($this->wpdb->prepare("
            SELECT * FROM {$this->wpdb->prefix}ett_breaks 
            WHERE employee_id = %d AND is_active = 1
        ", $employee_id));
        
        if (!$active_break) {
            return false;
        }
        
        $break_out_time = $this->get_current_ist_time();
        $break_in = new DateTime($active_break->break_in_time);
        $break_out = new DateTime($break_out_time);
        $interval = $break_out->diff($break_in);
        $duration = ($interval->h * 60) + $interval->i;
        
        return $this->wpdb->update(
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
    }
    
    /**
     * Issue operations
     */
    public function create_issue($employee_id, $category, $description) {
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'ett_issues',
            array(
                'employee_id' => intval($employee_id),
                'issue_category' => sanitize_text_field($category),
                'issue_description' => sanitize_textarea_field($description),
                'issue_status' => 'pending'
            ),
            array('%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * Warning operations
     */
    public function create_warning($employee_id, $message, $warning_date = null) {
        if (!$warning_date) {
            $warning_date = date('Y-m-d');
        }
        
        return $this->wpdb->insert(
            $this->wpdb->prefix . 'ett_warnings',
            array(
                'employee_id' => intval($employee_id),
                'warning_date' => $warning_date,
                'warning_message' => sanitize_text_field($message),
                'is_active' => 1
            ),
            array('%d', '%s', '%s', '%d')
        );
    }
    
    // Table creation methods
    private function get_employees_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_employees';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            employee_code VARCHAR(50) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY email (email),
            UNIQUE KEY employee_code (employee_code)
        ) $charset_collate;";
    }
    
    private function get_tags_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_tags';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            tag_name VARCHAR(255) NOT NULL,
            time_minutes INT(11) NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
    }
    
    private function get_assignments_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_assignments';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            employee_id INT(11) NOT NULL,
            tag_id INT(11) NOT NULL,
            is_mandatory TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_tag (employee_id, tag_id)
        ) $charset_collate;";
    }
    
    private function get_logs_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_logs';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            employee_id INT(11) NOT NULL,
            tag_id INT(11) NOT NULL,
            count INT(11) NOT NULL DEFAULT 0,
            total_minutes INT(11) NOT NULL DEFAULT 0,
            log_date DATE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY employee_tag_date (employee_id, tag_id, log_date)
        ) $charset_collate;";
    }
    
    private function get_warnings_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_warnings';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            employee_id INT(11) NOT NULL,
            warning_date DATE NOT NULL,
            warning_message TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";
    }
    
    private function get_submission_status_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_submission_status';
        return "CREATE TABLE IF NOT EXISTS $table_name (
            id INT(11) NOT NULL AUTO_INCREMENT,
            employee_id INT(11) NOT NULL,
            submission_date DATE NOT NULL,
            submission_time DATETIME DEFAULT CURRENT_TIMESTAMP,
            is_locked TINYINT(1) DEFAULT 1,
            total_minutes INT(11) DEFAULT 0,
            status_message VARCHAR(255) DEFAULT 'Data submitted successfully',
            PRIMARY KEY (id),
            UNIQUE KEY employee_date (employee_id, submission_date)
        ) $charset_collate;";
    }
    
    private function get_breaks_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_breaks';
        return "CREATE TABLE IF NOT EXISTS $table_name (
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
            KEY employee_date (employee_id, break_date)
        ) $charset_collate;";
    }
    
    private function get_issues_table_sql($charset_collate) {
        $table_name = $this->wpdb->prefix . 'ett_issues';
        return "CREATE TABLE IF NOT EXISTS $table_name (
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
            KEY employee_status (employee_id, issue_status)
        ) $charset_collate;";
    }
}