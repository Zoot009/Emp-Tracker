<?php
/**
 * Break Tracker Frontend Display
 */

if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;

$current_date = date('Y-m-d');

$breaks = $wpdb->get_results($wpdb->prepare("
    SELECT b.*, e.name as employee_name, e.employee_code
    FROM {$wpdb->prefix}ett_breaks b
    LEFT JOIN {$wpdb->prefix}ett_employees e ON b.employee_id = e.id
    WHERE b.break_date = %s
    ORDER BY b.break_in_time DESC
", $current_date));

// Separate active and completed breaks
$active_breaks = array();
$completed_breaks = array();

foreach ($breaks as $break) {
    if ($break->is_active) {
        $active_breaks[] = $break;
    } else {
        $completed_breaks[] = $break;
    }
}
?>

<div class="ett-break-tracker ett-card">
    <div class="ett-card-header">
        <h2 class="ett-card-title">Break Tracker - <?php echo date('F j, Y'); ?></h2>
        <p>Real-time break monitoring</p>
    </div>
    
    <?php if (!empty($active_breaks)): ?>
        <div class="active-breaks-section">
            <h3 style="color: #ffc107;">Currently on Break</h3>
            <table class="ett-public-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Break Started</th>
                        <th>Duration</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_breaks as $break): ?>
                    <tr class="active-break-row" data-break-start="<?php echo $break->break_in_time; ?>">
                        <td>
                            <strong><?php echo esc_html($break->employee_name); ?></strong><br>
                            <small><?php echo esc_html($break->employee_code); ?></small>
                        </td>
                        <td>
                            <?php 
                            if ($break->break_in_time) {
                                $break_in = new DateTime($break->break_in_time);
                                echo $break_in->format('h:i A');
                            }
                            ?>
                        </td>
                        <td>
                            <span class="live-duration" data-start="<?php echo $break->break_in_time; ?>">
                                Calculating...
                            </span>
                        </td>
                        <td>
                            <span class="break-status" data-start="<?php echo $break->break_in_time; ?>">
                                <span class="ett-badge ett-badge-warning">On Break</span>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
    
    <div class="completed-breaks-section">
        <h3>Today's Break History</h3>
        <?php if (!empty($completed_breaks)): ?>
            <table class="ett-public-table">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Break In</th>
                        <th>Break Out</th>
                        <th>Duration</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($completed_breaks as $break): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($break->employee_name); ?></strong><br>
                            <small><?php echo esc_html($break->employee_code); ?></small>
                        </td>
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
                            }
                            ?>
                        </td>
                        <td>
                            <strong><?php echo $break->break_duration; ?> min</strong>
                        </td>
                        <td>
                            <?php if ($break->break_duration > 20): ?>
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
            <?php if (empty($active_breaks)): ?>
                <div class="ett-alert ett-alert-info">
                    <p>No breaks taken today yet.</p>
                </div>
            <?php else: ?>
                <p>No completed breaks yet today.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <?php if (!empty($breaks)): ?>
        <div class="break-summary">
            <h4>Today's Summary</h4>
            <div class="summary-stats">
                <div class="stat-item">
                    <strong>Total Breaks:</strong> <?php echo count($breaks); ?>
                </div>
                <div class="stat-item">
                    <strong>Currently on Break:</strong> <?php echo count($active_breaks); ?>
                </div>
                <div class="stat-item">
                    <strong>Average Duration:</strong> 
                    <?php 
                    $completed_durations = array_column($completed_breaks, 'break_duration');
                    $avg_duration = !empty($completed_durations) ? round(array_sum($completed_durations) / count($completed_durations), 1) : 0;
                    echo $avg_duration . ' min';
                    ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    function updateLiveDurations() {
        var liveDurations = document.querySelectorAll('.live-duration');
        var breakStatuses = document.querySelectorAll('.break-status');
        
        liveDurations.forEach(function(element, index) {
            var startTime = new Date(element.getAttribute('data-start'));
            var now = new Date();
            var diffMinutes = Math.floor((now - startTime) / 1000 / 60);
            
            element.textContent = diffMinutes + ' minutes';
            
            // Update status
            var statusElement = breakStatuses[index];
            if (statusElement) {
                if (diffMinutes > 20) {
                    statusElement.innerHTML = '<span class="ett-badge ett-badge-danger">Exceeded (' + diffMinutes + ' min)</span>';
                } else if (diffMinutes > 15) {
                    statusElement.innerHTML = '<span class="ett-badge ett-badge-warning">Approaching Limit (' + diffMinutes + ' min)</span>';
                } else {
                    statusElement.innerHTML = '<span class="ett-badge ett-badge-info">On Break (' + diffMinutes + ' min)</span>';
                }
            }
        });
    }
    
    // Update every 30 seconds
    if (document.querySelectorAll('.live-duration').length > 0) {
        updateLiveDurations();
        setInterval(updateLiveDurations, 30000);
    }
});
</script>

<style>
.active-breaks-section {
    margin-bottom: 30px;
    padding: 15px;
    background: #fff9e6;
    border-radius: 8px;
    border-left: 4px solid #ffc107;
}

.completed-breaks-section {
    margin-bottom: 20px;
}

.break-summary {
    margin-top: 20px;
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
    margin-top: 10px;
}

.stat-item {
    text-align: center;
    padding: 10px;
    background: white;
    border-radius: 4px;
    border-left: 3px solid #007cba;
}

.live-duration {
    font-weight: bold;
    color: #ffc107;
}

@media (max-width: 768px) {
    .summary-stats {
        grid-template-columns: 1fr;
    }
}
</style>