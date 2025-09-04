<?php
/**
 * Autoloader for Employee Tag Tracker
 */

class ETT_Autoloader {
    
    /**
     * Initialize autoloader
     */
    public static function init() {
        spl_autoload_register(array(__CLASS__, 'autoload'));
    }
    
    /**
     * Autoload classes
     */
    public static function autoload($class_name) {
        // Only autoload our classes
        if (strpos($class_name, 'ETT_') !== 0) {
            return;
        }
        
        $class_file = str_replace('ETT_', '', $class_name);
        $class_file = str_replace('_', '-', strtolower($class_file));
        $file_path = ETT_PLUGIN_PATH . 'includes/class-' . $class_file . '.php';
        
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
}