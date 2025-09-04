<?php
/**
 * Work Log Form Template
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="ett-work-form-container" class="ett-card">
    <div class="ett-card-header">
        <h3 class="ett-card-title">Log Your Work - <?php echo date('F j, Y', strtotime($selected_date)); ?></h3>
    </div>
    
    <form id="ett-work-log-form">
        <input type="hidden" id="employee_id" value="<?php echo esc_attr($employee_id); ?>" />
        <input type="hidden" id="selected_log_date" value="<?php echo esc_attr($selected_date); ?>" />
        
        <div class="ett-table-responsive">
            <table class="ett-work-table">
                <thead>
                    <tr>
                        <th>Tag</th>
                        <th>Type</th>
                        <th>Time/Unit</th>
                        <th>Count</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($assigned_tags as $tag): ?>
                    <tr>
                        <td>
                            <strong><?php echo esc_html($tag->tag_name); ?></strong>
                        </td>
                        <td>
                            <?php if ($tag->is_mandatory): ?>
                                <span class="ett-badge ett-badge-danger">Mandatory</span>
                            <?php else: ?>
                                <span class="ett-badge ett-badge-success">Optional</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($tag->time_minutes); ?> min</td>
                        <td>
                            <input type="number" 
                                   class="ett-count-input" 
                                   data-tag-id="<?php echo esc_attr($tag->tag_id); ?>"
                                   data-time="<?php echo esc_attr($tag->time_minutes); ?>"
                                   data-mandatory="<?php echo esc_attr($tag->is_mandatory); ?>"
                                   value="0"
                                   min="0"
                                   step="1" />
                        </td>
                        <td class="ett-total-time" data-tag-id="<?php echo esc_attr($tag->tag_id); ?>">0 min</td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong>Total Time:</strong></td>
                        <td><strong id="ett-grand-total">0 hours 0 minutes</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        
        <div class="ett-form-actions">
            <div class="ett-alert ett-alert-warning">
                <strong>Important:</strong> Once submitted, this data will be locked and cannot be edited.
            </div>
            <button type="submit" class="ett-button ett-button-primary">
                Submit & Lock Work Log
            </button>
            <div id="ett-message"></div>
        </div>
    </form>
</div>