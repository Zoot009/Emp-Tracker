<?php
/**
 * Security and validation handler - COMPLETELY FIXED VERSION
 */

class ETT_Security {
    
    private $session_started = false;
    
    /**
     * Start session with proper error handling
     */
    public function start_session() {
        // Return if already started
        if ($this->session_started || session_status() === PHP_SESSION_ACTIVE) {
            $this->session_started = true;
            return true;
        }
        
        // Check if headers already sent
        if (headers_sent($filename, $line)) {
            error_log("ETT Plugin Warning: Cannot start session - headers already sent at {$filename}:{$line}");
            return false;
        }
        
        // Try to start session
        try {
            $session_started = @session_start();
            
            if (!$session_started) {
                error_log('ETT Plugin Error: Failed to start PHP session');
                return false;
            }
            
            // Initialize session if needed
            if (!isset($_SESSION['ett_initialized'])) {
                $_SESSION['ett_initialized'] = true;
                $_SESSION['ett_start_time'] = time();
            }
            
            $this->session_started = true;
            return true;
        } catch (Exception $e) {
            error_log('ETT Plugin Session Exception: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if user is logged in employee
     */
    public function is_employee_logged_in() {
        if (!$this->start_session()) {
            return false;
        }
        
        return isset($_SESSION['ett_employee_id']) && 
               is_numeric($_SESSION['ett_employee_id']) && 
               $_SESSION['ett_employee_id'] > 0;
    }
    
    /**
     * Get logged in employee ID
     */
    public function get_logged_in_employee_id() {
        if (!$this->start_session()) {
            return 0;
        }
        
        if (isset($_SESSION['ett_employee_id']) && is_numeric($_SESSION['ett_employee_id'])) {
            return intval($_SESSION['ett_employee_id']);
        }
        
        return 0;
    }
    
    /**
     * Set employee login
     */
    public function set_employee_login($employee_id) {
        if (!$this->start_session()) {
            return false;
        }
        
        if (!is_numeric($employee_id) || $employee_id <= 0) {
            error_log('ETT Plugin Error: Invalid employee ID for login: ' . $employee_id);
            return false;
        }
        
        $_SESSION['ett_employee_id'] = intval($employee_id);
        $_SESSION['ett_login_time'] = time();
        
        return true;
    }
    
    /**
     * Destroy session safely
     */
    public function destroy_session() {
        if (!$this->start_session()) {
            return true; // Consider it destroyed if we can't start it
        }
        
        try {
            // Unset ETT specific session variables
            unset($_SESSION['ett_employee_id']);
            unset($_SESSION['ett_login_time']);
            unset($_SESSION['ett_initialized']);
            unset($_SESSION['ett_start_time']);
            
            // If no other session data, destroy completely
            if (empty($_SESSION)) {
                session_destroy();
            }
            
            $this->session_started = false;
            return true;
        } catch (Exception $e) {
            error_log('ETT Plugin Error destroying session: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check session validity (timeout after 8 hours)
     */
    public function is_session_valid() {
        if (!$this->start_session()) {
            return false;
        }
        
        if (!isset($_SESSION['ett_login_time'])) {
            return false;
        }
        
        $session_timeout = 8 * 60 * 60; // 8 hours
        return (time() - $_SESSION['ett_login_time']) < $session_timeout;
    }
    
    /**
     * Refresh session timestamp
     */
    public function refresh_session() {
        if ($this->start_session()) {
            $_SESSION['ett_login_time'] = time();
            return true;
        }
        return false;
    }
    
    // Security methods with improved validation
    public function verify_nonce($nonce, $action) {
        if (empty($nonce) || empty($action)) {
            return false;
        }
        return wp_verify_nonce($nonce, $action);
    }
    
    public function create_nonce($action) {
        if (empty($action)) {
            return false;
        }
        return wp_create_nonce($action);
    }
    
    public function check_admin_capability() {
        return current_user_can('manage_options');
    }
    
    public function sanitize_employee_code($code) {
        $code = sanitize_text_field($code);
        // Remove any non-alphanumeric characters except hyphens and underscores
        $code = preg_replace('/[^a-zA-Z0-9\-_]/', '', $code);
        return strtoupper($code);
    }
    
    public function validate_email($email) {
        $email = sanitize_email($email);
        return is_email($email) ? $email : false;
    }
    
    public function sanitize_int($value, $min = 0, $max = null) {
        $value = intval($value);
        if ($value < $min) {
            return $min;
        }
        if ($max !== null && $value > $max) {
            return $max;
        }
        return $value;
    }
    
    public function sanitize_text($text) {
        if (empty($text)) {
            return '';
        }
        return sanitize_text_field(trim($text));
    }
    
    public function sanitize_textarea($text) {
        if (empty($text)) {
            return '';
        }
        return sanitize_textarea_field(trim($text));
    }
    
    public function escape_html($text) {
        if (empty($text)) {
            return '';
        }
        return esc_html($text);
    }
    
    public function escape_attr($text) {
        if (empty($text)) {
            return '';
        }
        return esc_attr($text);
    }
    
    /**
     * Validate date format
     */
    public function validate_date($date, $format = 'Y-m-d') {
        if (empty($date)) {
            return false;
        }
        
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    /**
     * Sanitize and validate date
     */
    public function sanitize_date($date) {
        $date = sanitize_text_field($date);
        if ($this->validate_date($date)) {
            return $date;
        }
        return date('Y-m-d'); // Return today's date as fallback
    }
    
    /**
     * Check if request is AJAX
     */
    public function is_ajax_request() {
        return defined('DOING_AJAX') && DOING_AJAX;
    }
    
    /**
     * Rate limiting for actions (simple implementation)
     */
    public function check_rate_limit($action, $employee_id, $max_attempts = 5, $time_window = 300) {
        if (!$this->start_session()) {
            return true; // Allow if session fails
        }
        
        $key = "ett_rate_limit_{$action}_{$employee_id}";
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = array();
        }
        
        // Clean old attempts
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $time_window) {
            return ($now - $timestamp) < $time_window;
        });
        
        // Check if limit exceeded
        if (count($_SESSION[$key]) >= $max_attempts) {
            return false;
        }
        
        // Add current attempt
        $_SESSION[$key][] = $now;
        return true;
    }
}