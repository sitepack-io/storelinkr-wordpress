<?php
if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}
?>

<div class="storelinkr-container">
    <?php
    $page ='diagnostic';
    require_once 'storelinkr_header.php';
    ?>
    <div class="storelinkr-body">
        <div class="wrap">
            <div class="storelinkr-dashboard">
                <div class="storelinkr-block">
                    <h3>
                        <?php echo esc_html__('Diagnostic', 'storelinkr') ?>
                    </h3>

                    <div class="form-row-group">
                        <label>
                            <?php echo esc_html__('PHP version', 'storelinkr')?>
                        </label>

                        <input type="text" value="<?php echo esc_attr($phpVersion); ?>" class="regular-text" disabled="disabled" />
                    </div>

                    <div class="form-row-group">
                        <label>
                            <?php echo esc_html__('WP version', 'storelinkr')?>
                        </label>

                        <input type="text" value="<?php echo esc_attr($wpVersion); ?>" class="regular-text" disabled="disabled" />
                    </div>

                    <div class="form-row-group">
                        <label>
                            <?php echo esc_html__('StoreLinkr plugin version', 'storelinkr')?>
                        </label>

                        <input type="text" value="<?php echo esc_attr($pluginVersion); ?>" class="regular-text" disabled="disabled" />
                    </div>

                    <div class="form-row-group">
                        <label>
                            <?php echo esc_html__('Error logs', 'storelinkr')?>
                        </label>

                        <textarea name="error_logs" class="regular-text" rows="20" disabled>
<?php echo esc_attr(implode("\n", $lastLines)); ?>
                        </textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
