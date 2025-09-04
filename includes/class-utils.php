<?php
/**
 * Utility functions - COMPLETE VERSION
 * File: includes/class-utils.php
 */

class ETT_Utils {
    
    /**
     * Convert minutes to hours and minutes format
     */
    public static function minutes_to_hours_format($minutes) {
        if (empty($minutes) || !is_numeric($minutes)) {
            return '0h 0m';
        }
        
        $minutes = intval($minutes);
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%dh %dm', $hours, $mins);
    }
    
    /**
     * Convert to IST for display
     */
    public static function convert_to_ist_display($utc_time) {
        try {
            $datetime = new DateTime($utc_time, new DateTimeZone('UTC'));
            $datetime->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $datetime->format('h:i A') . ' IST';
        } catch (Exception $e) {
            return date('h:i A', strtotime($utc_time)) . ' IST';
        }
    }
    
    /**
     * Get date range array (working days only)
     */
    public static function get_date_range($start_date, $end_date, $exclude_weekends = true) {
        try {
            $period = new DatePeriod(
                new DateTime($start_date),
                new DateInterval('P1D'),
                (new DateTime($end_date))->modify('+1 day')
            );
            
            $dates = array();
            $working_days = get_option('ett_working_days', array('monday', 'tuesday', 'wednesday', 'thursday', 'friday'));
            
            foreach ($period as $date) {
                if ($exclude_weekends) {
                    $day_name = strtolower($date->format('l'));
                    if (in_array($day_name, $working_days)) {
                        $dates[] = $date->format('Y-m-d');
                    }
                } else {
                    $dates[] = $date->format('Y-m-d');
                }
            }
            
            return $dates;
        } catch (Exception $e) {
            error_log('ETT Utils Error: ' . $e->getMessage());
            return array();
        }
    }
    
    /**
     * Calculate working days between dates
     */
    public static function get_working_days($start_date, $end_date) {
        return count(self::get_date_range($start_date, $end_date, true));
    }
    
    /**
     * Format database date for display
     */
    public static function format_date_for_display($date, $format = 'F j, Y') {
        if (empty($date)) {
            return '';
        }
        
        try {
            return date($format, strtotime($date));
        } catch (Exception $e) {
            return $date;
        }
    }
    
    /**
     * Format datetime for display with timezone
     */
    public static function format_datetime_for_display($datetime, $format = 'F j, Y g:i A') {
        if (empty($datetime)) {
            return '';
        }
        
        try {
            $dt = new DateTime($datetime);
            $dt->setTimezone(new DateTimeZone('Asia/Kolkata'));
            return $dt->format($format) . ' IST';
        } catch (Exception $e) {
            return date($format, strtotime($datetime));
        }
    }
    
    /**
     * Get status color class
     */
    public static function get_status_color($status) {
        $colors = array(
            'pending' => 'danger',
            'in_progress' => 'warning', 
            'resolved' => 'success',
            'active' => 'warning',
            'completed' => 'success',
            'approved' => 'success',
            'rejected' => 'danger',
            'draft' => 'secondary',
            'published' => 'primary'
        );
        
        return isset($colors[$status]) ? $colors[$status] : 'secondary';
    }
    
    /**
     * Get status badge HTML
     */
    public static function get_status_badge($status, $text = null) {
        $color = self::get_status_color($status);
        $display_text = $text ?: ucfirst(str_replace('_', ' ', $status));
        
        return sprintf(
            '<span class="ett-badge ett-badge-%s">%s</span>',
            esc_attr($color),
            esc_html($display_text)
        );
    }
    
    /**
     * Validate date format
     */
    public static function is_valid_date($date, $format = 'Y-m-d') {
        if (empty($date)) {
            return false;
        }
        
        try {
            $d = DateTime::createFromFormat($format, $date);
            return $d && $d->format($format) === $date;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Get current IST date
     */
    public static function get_current_ist_date() {
        try {
            $datetime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            return $datetime->format('Y-m-d');
        } catch (Exception $e) {
            return date('Y-m-d');
        }
    }
    
    /**
     * Get current IST datetime
     */
    public static function get_current_ist_datetime() {
        try {
            $datetime = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
            return $datetime->format('Y-m-d H:i:s');
        } catch (Exception $e) {
            return date('Y-m-d H:i:s');
        }
    }
    
    /**
     * Calculate time difference in minutes
     */
    public static function get_time_difference_minutes($start_time, $end_time = null) {
        try {
            $start = new DateTime($start_time);
            $end = $end_time ? new DateTime($end_time) : new DateTime();
            
            $interval = $end->diff($start);
            return ($interval->h * 60) + $interval->i;
        } catch (Exception $e) {
            return 0;
        }
    }
    
    /**
     * Generate random employee code
     */
    public static function generate_employee_code($prefix = 'EMP', $length = 3) {
        return $prefix . str_pad(mt_rand(1, pow(10, $length) - 1), $length, '0', STR_PAD_LEFT);
    }
    
    /**
     * Sanitize and validate email
     */
    public static function validate_email($email) {
        $email = sanitize_email($email);
        return is_email($email) ? $email : false;
    }
    
    /**
     * Generate chart colors
     */
    public static function get_chart_colors($count = 10) {
        $colors = array(
            '#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF',
            '#FF9F40', '#FF6384', '#C9CBCF', '#4BC0C0', '#FF6384',
            '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40'
        );
        
        $result = array();
        for ($i = 0; $i < $count; $i++) {
            $result[] = $colors[$i % count($colors)];
        }
        
        return $result;
    }
    
    /**
     * Convert array to CSV string
     */
    public static function array_to_csv($array, $headers = null) {
        if (empty($array)) {
            return '';
        }
        
        $output = fopen('php://temp', 'w');
        
        // Add headers if provided
        if ($headers) {
            fputcsv($output, $headers);
        }
        
        // Add data rows
        foreach ($array as $row) {
            fputcsv($output, (array) $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }
    
    /**
     * Format file size
     */
    public static function format_file_size($bytes) {
        if ($bytes >= 1073741824) {
            $bytes = number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            $bytes = number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            $bytes = number_format($bytes / 1024, 2) . ' KB';
        } elseif ($bytes > 1) {
            $bytes = $bytes . ' bytes';
        } elseif ($bytes == 1) {
            $bytes = $bytes . ' byte';
        } else {
            $bytes = '0 bytes';
        }
        
        return $bytes;
    }
    
    /**
     * Get time ago string
     */
    public static function time_ago($datetime) {
        if (empty($datetime)) {
            return '';
        }
        
        try {
            $time = time() - strtotime($datetime);
            
            if ($time < 60) {
                return 'just now';
            } elseif ($time < 3600) {
                return floor($time / 60) . ' minutes ago';
            } elseif ($time < 86400) {
                return floor($time / 3600) . ' hours ago';
            } elseif ($time < 2592000) {
                return floor($time / 86400) . ' days ago';
            } elseif ($time < 31536000) {
                return floor($time / 2592000) . ' months ago';
            } else {
                return floor($time / 31536000) . ' years ago';
            }
        } catch (Exception $e) {
            return $datetime;
        }
    }
    
    /**
     * Log debug information (only in WP_DEBUG mode)
     */
    public static function debug_log($message, $data = null) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = '[ETT] ' . $message;
            if ($data !== null) {
                $log_message .= ' Data: ' . print_r($data, true);
            }
            error_log($log_message);
        }
    }
    
    /**
     * Check if current user can access admin features
     */
    public static function can_access_admin() {
        return current_user_can('manage_options');
    }
    
    /**
     * Get plugin version
     */
    public static function get_plugin_version() {
        return ETT_PLUGIN_VERSION;
    }
    
    /**
     * Get database version
     */
    public static function get_database_version() {
        return get_option('ett_db_version', '0');
    }
    
    /**
     * Check if plugin is properly installed
     */
    public static function is_plugin_installed() {
        global $wpdb;
        
        $required_tables = array(
            $wpdb->prefix . 'ett_employees',
            $wpdb->prefix . 'ett_tags',
            $wpdb->prefix . 'ett_assignments',
            $wpdb->prefix . 'ett_logs'
        );
        
        foreach ($required_tables as $table) {
            if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Generate export filename
     */
    public static function generate_export_filename($type, $date_range = null) {
        $site_name = sanitize_title(get_bloginfo('name'));
        $timestamp = date('Y-m-d_H-i-s');
        
        $filename = "ett_{$site_name}_{$type}";
        
        if ($date_range) {
            $filename .= "_{$date_range}";
        }
        
        $filename .= "_{$timestamp}.csv";
        
        return $filename;
    }
    
    /**
     * Escape data for CSV export
     */
    public static function escape_csv_data($data) {
        if (is_array($data) || is_object($data)) {
            return json_encode($data);
        }
        
        return str_replace(array("\r", "\n"), ' ', strip_tags($data));
    }
}