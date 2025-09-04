<?php
/**
 * Break Management Section
 */

if (!defined('ABSPATH')) {
    exit;
}

// Get today's breaks
$todays_breaks = $wpdb->get_results($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}ett_breaks 
    WHERE employee_id = %d AND break_date = %s
    ORDER BY break_in_time DESC
", $employee_id, $current_date));

// Check if currently on break
$active_break = $wpdb->get_row($wpdb->prepare("
    SELECT * FROM {$wpdb->prefix}ett_breaks 
    WHERE employee_id = %d AND is_active = 1
", $employee_id));
?>

<div class="ett-break-section ett-card">
    <div class="ett-card-header">
        <h3 class="ett-card-title">Break Management</h3>
    </div>
    
    <div class="ett-break-controls">
        <?php if ($active_break): ?>
            <button id="break-out-btn" class="ett-button ett-button-warning">Break Out</button>
            <p class="break-timer">
                On break for: <span id="break-duration">calculating...</span>
                <script>
                var breakInTime = new Date('<?php echo $active_break->break_in_time; ?>');
                function updateBreakDuration() {
                    var now = new Date();
                    var diff = Math.floor((now - breakInTime) / 1000 / 60);
                    document.getElementById('break-duration').textContent = diff + ' minutes';
                    
                    if (diff > 20) {
                        document.getElementById('break-duration').style.color = 'red';
                        document.getElementById('break-duration').innerHTML = diff + ' minutes <span style="color:red;">⚠️ Exceeded 20 minutes!</span>';
                    }
                }
                setInterval(updateBreakDuration, 1000);
                updateBreakDuration();
                </script>
            </p>
        <?php else: ?>
            <button id="break-in-btn" class="ett-button ett-button-info">Break In</button>
            <p class="break-info">Take a break when needed. Maximum recommended: 20 minutes.</p>
        <?php endif; ?>
    </div>
    
    <div class="ett-todays-breaks">
        <h4>Today's Breaks</h4>
        <?php if (!empty($todays_breaks)): ?>
            <table class="ett-public-table">
                <thead>
                    <tr>
                        <th>Break In</th>
                        <th>Break Out</th>
                        <th>Duration</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($todays_breaks as $break): ?>
                    <tr>
                        <td>
                            <?php 
                            if ($break->break_in_time) {
                                $break_in = new DateTime($break->break_in_time);
                                echo $break_in->format('h:i A');
                            }
                            ?>
                        </td>
                        <td>
                            <?php 
                            if ($break->break_out_time) {
                                $break_out = new DateTime($break->break_out_time);
                                echo $break_out->format('h:i A');
                            } else {
                                echo '<span class="ett-badge ett-badge-warning">Active</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php if ($break->break_duration): ?>
                                <?php echo $break->break_duration; ?> min
                            <?php else: ?>
                                <span id="live-duration-<?php echo $break->id; ?>">Active</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($break->is_active): ?>
                                <span class="ett-badge ett-badge-warning">On Break</span>
                            <?php elseif ($break->break_duration > 20): ?>
                                <span class="ett-badge ett-badge-danger">Exceeded</span>
                            <?php else: ?>
                                <span class="ett-badge ett-badge-success">Normal</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p>No breaks taken today.</p>
        <?php endif; ?>
    </div>
</div>