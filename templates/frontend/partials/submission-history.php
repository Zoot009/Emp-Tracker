<?php
/**
 * Submission History Section
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="ett-submission-history ett-card">
    <div class="ett-card-header">
        <h3 class="ett-card-title">Your Submission History (Last 7 Days)</h3>
    </div>
    
    <?php if (!empty($submission_history)): ?>
        <div class="ett-table-responsive">
            <table class="ett-public-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Submitted At</th>
                        <th>Total Hours</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submission_history as $history): ?>
                    <tr>
                        <td><?php echo date('M d, Y', strtotime($history->submission_date)); ?></td>
                        <td>
                            <?php 
                            $submission_time = new DateTime($history->submission_time);
                            echo $submission_time->format('h:i A');
                            ?>
                        </td>
                        <td>
                            <strong><?php echo ETT_Utils::minutes_to_hours_format($history->total_minutes); ?></strong>
                        </td>
                        <td>
                            <span class="ett-badge ett-badge-success">Locked</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="ett-alert ett-alert-info">
            <p>No submissions in the last 7 days. Start logging your work to see your history here.</p>
        </div>
    <?php endif; ?>
</div>