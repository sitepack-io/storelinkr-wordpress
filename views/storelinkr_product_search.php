<?php
if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}
?>

<div class="storelinkr-container">
    <?php
    $page ='product_search';
    require_once 'storelinkr_header.php';
    ?>
    <div class="storelinkr-body">
        <div class="wrap">
            <div class="storelinkr-dashboard">
                <div class="storelinkr-block storelinkr-product-search-block">
                    <div class="form-row-group">
                        <h3>
                            <?php echo esc_html__('Search by EAN / GTIN / ISBN', 'storelinkr')?>
                        </h3>
                        
                        <form id="ean-search-form" style="margin-top: 15px;">
                            <table class="form-table">
                                <tr>
                                    <th scope="row">
                                        <label for="ean_search_input"><?php echo esc_html__('EAN Number', 'storelinkr')?></label>
                                    </th>
                                    <td>
                                        <input type="text" id="ean_search_input" name="ean" class="regular-text" placeholder="<?php echo esc_attr__('Enter EAN/GTIN/ISBN number', 'storelinkr')?>" />
                                    </td>
                                </tr>
                            </table>
                            
                            <p class="submit">
                                <button type="submit" id="search-ean-btn" class="button button-primary">
                                    <?php echo esc_html__('Search Product', 'storelinkr')?>
                                </button>
                                <span id="search-spinner" class="spinner" style="margin-left: 10px;"></span>
                            </p>
                        </form>
                        
                        <div id="search-message" style="margin-top: 10px;"></div>
                    </div>
                </div>


                <div class="storelinkr-block storelinkr-product-search-block" id="productResults" style="display: none;">
                    <h2>
                        <?php echo esc_html__('Search results', 'storelinkr') ?>
                    </h2>

                    <div id="results-table-container">
                        <!-- Results table will be populated here via AJAX -->
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#ean-search-form').on('submit', function(e) {
        e.preventDefault();
        
        var ean = $('#ean_search_input').val().trim();
        if (!ean) {
            $('#search-message').html('<div class="notice notice-error"><p><?php echo esc_js(__('Please enter an EAN number', 'storelinkr')); ?></p></div>');
            return;
        }
        
        // Show spinner and disable button
        $('#search-spinner').addClass('is-active');
        $('#search-ean-btn').prop('disabled', true);
        $('#search-message').empty();
        $('#productResults').hide();
        
        // AJAX call
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'storelinkr_search_product_by_ean',
                ean: ean,
                nonce: '<?php echo wp_create_nonce('storelinkr_search_product_by_ean'); ?>'
            },
            success: function(response) {
                $('#search-spinner').removeClass('is-active');
                $('#search-ean-btn').prop('disabled', false);
                
                if (response.success && response.data.success) {
                    if (response.data.product) {
                        displaySearchResults(response.data.product);
                        $('#productResults').show();
                        $('#search-message').html('<div class="notice notice-success"><p><?php echo esc_js(__('Product found!', 'storelinkr')); ?></p></div>');
                    } else {
                        $('#search-message').html('<div class="notice notice-warning"><p><?php echo esc_js(__('No product found with this EAN number', 'storelinkr')); ?></p></div>');
                        $('#productResults').hide();
                    }
                } else {
                    var message = response.data && response.data.message ? response.data.message : '<?php echo esc_js(__('Search failed', 'storelinkr')); ?>';
                    $('#search-message').html('<div class="notice notice-error"><p>' + message + '</p></div>');
                    $('#productResults').hide();
                }
            },
            error: function() {
                $('#search-spinner').removeClass('is-active');
                $('#search-ean-btn').prop('disabled', false);
                $('#search-message').html('<div class="notice notice-error"><p><?php echo esc_js(__('An error occurred during the search', 'storelinkr')); ?></p></div>');
                $('#productResults').hide();
            }
        });
    });
    
    function displaySearchResults(product) {
        var tableHtml = '<table class="wp-list-table widefat fixed striped">';
        tableHtml += '<thead><tr>';
        tableHtml += '<th><?php echo esc_js(__('ID', 'storelinkr')); ?></th>';
        tableHtml += '<th><?php echo esc_js(__('Name', 'storelinkr')); ?></th>';
        tableHtml += '<th><?php echo esc_js(__('SKU', 'storelinkr')); ?></th>';
        tableHtml += '<th><?php echo esc_js(__('EAN', 'storelinkr')); ?></th>';
        tableHtml += '<th><?php echo esc_js(__('Type', 'storelinkr')); ?></th>';
        tableHtml += '<th><?php echo esc_js(__('Price', 'storelinkr')); ?></th>';
        tableHtml += '<th><?php echo esc_js(__('Stock Status', 'storelinkr')); ?></th>';
        tableHtml += '<th><?php echo esc_js(__('Actions', 'storelinkr')); ?></th>';
        tableHtml += '</tr></thead>';
        tableHtml += '<tbody>';
        
        tableHtml += '<tr>';
        tableHtml += '<td>' + product.id + '</td>';
        tableHtml += '<td><strong>' + product.name + '</strong></td>';
        tableHtml += '<td>' + (product.sku || '-') + '</td>';
        tableHtml += '<td>' + (product.ean || '-') + '</td>';
        tableHtml += '<td>' + (product.product_type || '-') + '</td>';
        tableHtml += '<td>' + (product.price || '-') + '</td>';
        tableHtml += '<td>' + product.stock_status + '</td>';
        
        // Determine edit button text based on product type
        var editButtonText = product.is_variation ? 
            '<?php echo esc_js(__('Edit Parent Product', 'storelinkr')); ?>' : 
            '<?php echo esc_js(__('Edit Product', 'storelinkr')); ?>';
        
        tableHtml += '<td><a href="' + product.edit_url + '" class="button button-small">' + editButtonText + '</a></td>';
        tableHtml += '</tr>';
        
        tableHtml += '</tbody></table>';
        
        $('#results-table-container').html(tableHtml);
    }
});
</script>