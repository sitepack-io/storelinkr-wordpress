<?php
if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}
?>

<div class="storelinkr-container">
    <?php
    $page ='danger-zone';
    require_once 'storelinkr_header.php';
    ?>
    <div class="storelinkr-body">
        <div class="wrap">
            <div class="storelinkr-dashboard">
                <div class="storelinkr-block storelinkr-danger-zone-block">
                    <h2>
                        <?php echo esc_html__('Danger zone', 'storelinkr') ?>
                    </h2>
                    <p>
                        <?php echo esc_html__('Once executed, these actions cannot be undone. Back up your data or proceed only if you’re sure.', 'storelinkr')?>
                    </p>

                    <div class="form-row-group row-danger-item">
                        <h3>
                            <?php echo esc_html__('Merge duplicate attributes', 'storelinkr')?>
                        </h3>
                        <p>
                            <?php echo esc_html__('Sometimes duplicate attributes appear in WooCommerce. Use this function to merge them. Note: You may need to recreate your product filters after using this function.', 'storelinkr')?>
                        </p>

                        <button onclick="mergeDuplicateAttributes()" class="button">
                            <?php echo esc_html__('Execute now', 'storelinkr')?>
                        </button>
                        <div id="merge-attributes-message" style="margin-top: 10px;"></div>
                    </div>

                    <div class="form-row-group row-danger-item">
                        <h3>
                            <?php echo esc_html__('Remove unused terms', 'storelinkr')?>
                        </h3>
                        <p>
                            <?php echo esc_html__('Remove attribute terms that are no longer linked to any products. This helps clean up your attributes list by removing empty terms.', 'storelinkr')?>
                        </p>

                        <button onclick="removeUnusedTerms()" class="button">
                            <?php echo esc_html__('Execute now', 'storelinkr')?>
                        </button>
                        <div id="remove-unused-terms-message" style="margin-top: 10px;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function mergeDuplicateAttributes() {
    if (!confirm('<?php echo esc_js(__("Are you sure you want to merge duplicate attributes? This action cannot be undone. You may need to recreate your product filters after this action.", "storelinkr")); ?>')) {
        return;
    }

    const messageDiv = document.getElementById('merge-attributes-message');
    messageDiv.innerHTML = '<span style="color: orange;"><?php echo esc_js(__("Processing...", "storelinkr")); ?></span>';

    const formData = new FormData();
    formData.append('action', 'storelinkr_merge_duplicate_attributes');
    formData.append('nonce', '<?php echo wp_create_nonce('storelinkr_merge_duplicate_attributes'); ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageDiv.innerHTML = '<span style="color: green;">' + (data.data.message || '<?php echo esc_js(__("Action executed successfully!", "storelinkr")); ?>') + '</span>';
        } else {
            messageDiv.innerHTML = '<span style="color: red;">' + (data.data.message || '<?php echo esc_js(__("An error occurred.", "storelinkr")); ?>') + '</span>';
        }
    })
    .catch(error => {
        messageDiv.innerHTML = '<span style="color: red;"><?php echo esc_js(__("Network error occurred.", "storelinkr")); ?></span>';
        console.error('Error:', error);
    });
}

function removeUnusedTerms() {
    if (!confirm('<?php echo esc_js(__("Are you sure you want to remove unused terms? This will delete all attribute terms that are not linked to any products. This action cannot be undone.", "storelinkr")); ?>')) {
        return;
    }

    const messageDiv = document.getElementById('remove-unused-terms-message');
    messageDiv.innerHTML = '<span style="color: orange;"><?php echo esc_js(__("Processing...", "storelinkr")); ?></span>';

    const formData = new FormData();
    formData.append('action', 'storelinkr_remove_unused_terms');
    formData.append('nonce', '<?php echo wp_create_nonce('storelinkr_remove_unused_terms'); ?>');

    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            messageDiv.innerHTML = '<span style="color: green;">' + (data.data.message || '<?php echo esc_js(__("Action executed successfully!", "storelinkr")); ?>') + '</span>';
        } else {
            messageDiv.innerHTML = '<span style="color: red;">' + (data.data.message || '<?php echo esc_js(__("An error occurred.", "storelinkr")); ?>') + '</span>';
        }
    })
    .catch(error => {
        messageDiv.innerHTML = '<span style="color: red;"><?php echo esc_js(__("Network error occurred.", "storelinkr")); ?></span>';
        console.error('Error:', error);
    });
}
</script>
