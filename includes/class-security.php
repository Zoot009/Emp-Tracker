<?php
/**
 * Security and validation handler - FIXED VERSION
 */

class ETT_Security {
    
    /**
     * Start session with proper error handling
     */
    public function start_session() {
        // Check if session is already started
        if (session_status() === PHP_SESSION_ACTIVE) {
            return true;
        }
        
        // Check if headers already sent
        if (headers_sent()) {
            error_log('ETT Plugin Warning: Cannot start session - headers already sent');
            return false;
        }
        
        // Start session
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
        
        return true;
    }
    
    /**
     * Check if user is logged in employee
     */
    public function is_employee_logged_in() {
        $this->start_session();
        return isset($_SESSION['ett_employee_id']) && $_SESSION['ett_employee_id'] > 0;
    }
    
    /**
     * Get logged in employee ID
     */
    public function get_logged_in_employee_id() {
        $this->start_session();
        return isset($_SESSION['ett_employee_id']) ? intval($_SESSION['ett_employee_id']) : 0;
    }
    
    /**
     * Set employee login
     */
    public function set_employee_login($employee_id) {
        $this->start_session();
        $_SESSION['ett_employee_id'] = intval($employee_id);
        $_SESSION['ett_login_time'] = time();
        return true;
    }
    
    /**
     * Destroy session
     */
    public function destroy_session() {
        $this->start_session();
        
        // Unset ETT specific session variables
        unset($_SESSION['ett_employee_id']);
        unset($_SESSION['ett_login_time']);
        unset($_SESSION['ett_initialized']);
        
        // If no other session data, destroy completely
        if (empty($_SESSION)) {
            session_destroy();
        }
        
        return true;
    }
    
    // Security methods
    public function verify_nonce($nonce, $action) {
        return wp_verify_nonce($nonce, $action);
    }
    
    public function create_nonce($action) {
        return wp_create_nonce($action);
    }
    
    public function check_admin_capability() {
        return current_user_can('manage_options');
    }
    
    public function sanitize_employee_code($code) {
        return sanitize_text_field($code);
    }
    
    public function validate_email($email) {
        return is_email($email);
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
        return sanitize_text_field($text);
    }
    
    public function sanitize_textarea($text) {
        return sanitize_textarea_field($text);
    }
    
    public function escape_html($text) {
        return esc_html($text);
    }
    
    public function escape_attr($text) {
        return esc_attr($text);
    }
}