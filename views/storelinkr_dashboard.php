<?php
if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}
?>

<div class="storelinkr-container">
    <?php
    $page ='settings';
    require_once 'storelinkr_header.php';
    ?>

    <div class="storelinkr-body">
        <div class="wrap">
            <div class="storelinkr-dashboard">
                <div class="storelinkr-block">
                    <h3>
                        <?php echo esc_html__('API Keys', 'storelinkr') ?>
                        <a href="https://portal.storelinkr.com" target="_blank" rel="noopener"
                           class="button storelinkr-button-right">
                            <?php echo esc_html__('Configure workflow(s)', 'storelinkr') ?>
                            <span class="dashicons dashicons-external"></span>
                        </a>
                    </h3>

                    <div class="form-row-group">
                        <label>
                            <?php echo esc_html__('Domain', 'storelinkr')?>
                        </label>

                        <input type="text" name="api_key" value="<?php echo esc_url(get_option('siteurl')); ?>" class="regular-text" />
                    </div>
                    <div class="form-row-group">
                        <label>
                            <?php echo esc_html__('API Key', 'storelinkr')?>
                        </label>

                        <input type="text" name="api_key" value="<?php echo esc_attr(get_option(StoreLinkrAdmin::STORELINKR_API_KEY)); ?>" class="regular-text" />
                    </div>
                    <div class="form-row-group">
                        <label>
                            <?php echo esc_html__('API Secret', 'storelinkr') ?>
                        </label>

                        <div class="storelinkr-hide" id="storelinkr-secret">
                            <button class="button" onclick="jQuery('#storelinkr-secret input').toggle();">
                                <?php echo esc_html__('Display', 'storelinkr')?>
                            </button>
                            <input type="text" name="api_secret" value="<?php echo esc_attr(get_option(StoreLinkrAdmin::STORELINKR_API_SECRET)); ?>" class="regular-text" />
                        </div>
                        <div class="storelinkr-hide-toggle">
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
