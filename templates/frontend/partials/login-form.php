<?php
/**
 * Employee Login Form
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ett-login-container">
    <div class="ett-card">
        <div class="ett-card-header">
            <h2 class="ett-card-title">Employee Login</h2>
        </div>
        
        <form id="ett-login-form">
            <div class="ett-form-group">
                <label for="employee_code">Employee Code:</label>
                <input type="text" 
                       id="employee_code" 
                       name="employee_code" 
                       required 
                       autocomplete="username"
                       placeholder="Enter your employee code" />
            </div>
            
            <div class="ett-form-group">
                <button type="submit" class="ett-button ett-button-primary">
                    Login
                </button>
            </div>
            
            <div id="ett-login-message"></div>
        </form>
    </div>
</div>