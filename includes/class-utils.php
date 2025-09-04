<?php
/**
 * Utility functions
 */

class ETT_Utils {
    
    /**
     * Convert minutes to hours and minutes format
     */
    public static function minutes_to_hours_format($minutes) {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;
        return sprintf('%dh %dm', $hours, $mins);
    }
    
    /**
     * Convert to IST for display
     */
    public static function convert_to_ist_display($utc_time) {
        $datetime = new DateTime($utc_time, new DateTimeZone('UTC'));
        $datetime->setTimezone(new DateTimeZone('Asia/Kolkata'));
        return $datetime->format('h:i A') . ' IST';
    }
    
    /**
     * Get date range array
     */
    public static function get_date_range($start_date, $end_date) {
        $period = new DatePeriod(
            new DateTime($start_date),
            new DateInterval('P1D'),
            (new DateTime($end_date))->modify('+1 day')
        );
        
        $dates = array();
        foreach ($period as $date) {
            if ($date->format('N') < 6) { // Exclude weekends
                $dates[] = $date->format('Y-m-d');
            }
        }
        
        return $dates;
    }
    
    /**
     * Calculate working days between dates
     */
    public static function get_working_days($start_date, $end_date) {
        return count(self::get_date_range($start_date, $end_date));
    }
    
    /**
     * Format database date for display
     */
    public static function format_date_for_display($date, $format = 'F j, Y') {
        return date($format, strtotime($date));
    }
    
    /**
     * Get status color class
     */
    public static function get_status_color($status) {
        $colors = array(
            'pending' => 'red',
            'in_progress' => 'orange', 
            'resolved' => 'green',
            'active' => 'orange',
            'completed' => 'green'
        );
        
        return isset($colors[$status]) ? $colors[$status] : 'gray';
    }
    
    /**
     * Validate date format
     */
    public static function is_valid_date($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
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
}