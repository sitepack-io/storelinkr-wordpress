<div class="storelinkr-heading">
    <a href="https://storelinkr.eu" target="_blank">
        <img srcset="<?php echo esc_url(plugin_dir_url(STORELINKR_PLUGIN_BASENAME)) ?>/images/storelinkr.png 2x,
                <?php echo esc_url(plugin_dir_url(STORELINKR_PLUGIN_BASENAME)) ?>/images/storelinkr-65.png 1x"
             src="<?php echo esc_url(plugin_dir_url(STORELINKR_PLUGIN_BASENAME)) ?>/images/storelinkr-65.png"
             class="storelinkr-icon" align="left" />
    </a>

    <a href="https://portal.storelinkr.com/?utm_source=wordpress&utm_medium=button_right_top&utm_campaign=wp_plugin"
       target="_blank" rel="noopener"
       class="storelinkr-pull-right storelinkr-admin-login button">
        <?php
        echo esc_html__('Open storelinkr', 'storelinkr') ?>
        <span class="dashicons dashicons-external"></span>
    </a>
</div>
<div class="storelinkr-menu">
    <ul>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=storelinkr')) ?>" <?php if($page === 'settings'): ?> class="active"<?php endif; ?>>
                <?php echo esc_html__('Settings', 'storelinkr') ?>
            </a>
        </li>
        <li>
            <a href="<?php echo esc_url(admin_url('admin.php?page=storelinkr&subpage=diagnostic')) ?>" <?php if($page === 'diagnostic'): ?> class="active"<?php endif; ?>>
                <?php echo esc_html__('Diagnostic', 'storelinkr') ?>
            </a>
        </li>
        <li class="text-danger">
            <a href="<?php echo esc_url(admin_url('admin.php?page=storelinkr&subpage=danger-zone')) ?>" <?php if($page === 'danger-zone'): ?> class="active"<?php endif; ?>>
                <?php echo esc_html__('Danger zone', 'storelinkr') ?>
            </a>
        </li>
    </ul>
</div>
