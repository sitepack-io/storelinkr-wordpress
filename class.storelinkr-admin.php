<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

class StoreLinkrAdmin
{

    public const STORELINKR_API_KEY = 'storelinkr_api_key';
    public const STORELINKR_API_SECRET = 'storelinkr_api_secret';

    public function init(): void
    {
        add_action('admin_init', [$this, 'adminInit']);
        add_action('admin_menu', [$this, 'adminPages']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles']);

        add_filter('woocommerce_product_data_tabs', [$this, 'storeLinkrProductCustomTab'], 99, 1);
        add_action('woocommerce_product_data_panels', [$this, 'productAttachmentTabContent']);
        add_action('woocommerce_product_data_panels', [$this, 'productStockLocationsTabContent']);
        add_action('edit_form_after_title', [$this, 'storeLinkrProductMessage']);
        add_action('admin_head', function () {
            $screen = get_current_screen();
            if ($screen && $screen->post_type === 'product_stock_location') {
                echo '<style>.page-title-action { display: none; }</style>';
            }
        });
        add_action('add_meta_boxes', function () {
            add_meta_box(
                'stock_location_meta',
                __('Stock location address', 'storelinkr'),
                [$this, 'renderStockLocationEditPostMetaBox'],
                'sl_stock_location',
                'normal'
            );
        });
//        add_action('admin_init', [$this, 'registerSettings']);
    }

    public function adminInit(): void
    {
        add_option(self::STORELINKR_API_KEY, '', '', false);
        add_option(self::STORELINKR_API_SECRET, '', '', false);

        if (empty(get_option(self::STORELINKR_API_KEY, null)) || empty(get_option(self::STORELINKR_API_SECRET, null))) {
            $this->initApiKeys();
        }

        add_filter('plugin_action_links_storelinkr/storelinkr.php', [$this, 'settingsLink']);
        add_filter('product_cat_row_actions', [$this, 'removeCategoryTrashLink'], 10, 2);
        add_filter('post_row_actions', [$this, 'removeProductTrashLink'], 10, 2);
    }

    /**
     * Link STORELINKR menu pages
     */
    public function adminPages(): void
    {
        remove_menu_page('storelinkr-settings');
        remove_menu_page('options-general.php?page=storelinkr-settings');

        add_menu_page(
            esc_html__('StoreLinkr dashboard', 'storelinkr'),
            'Storelinkr',
            'manage_options',
            'storelinkr',
            [$this, 'renderDashboardPage'],
            self::getSvgIcon(),
            56.9843751
        );

        add_submenu_page(
            'edit.php?post_type=product',
            __('Stock locations', 'storelinkr'),
            __('Stock locations', 'storelinkr'),
            'manage_woocommerce',
            'edit.php?post_type=sl_stock_location'
        );
    }

    /**
     * Add admin styles
     */
    public function addAdminStyles(): void
    {
        wp_enqueue_style(
            'admin-storelinkr-styles',
            plugin_dir_url(STORELINKR_PLUGIN_BASENAME) . '/assets/storelinkr_admin.css'
        );
    }

    public function renderDashboardPage(): void
    {
        if (isset($_GET['subpage']) && esc_attr($_GET['subpage']) == 'diagnostic') {
            $phpVersion = phpversion();
            $wpVersion = get_bloginfo('version');
            $pluginData = get_plugin_data(STORELINKR_PLUGIN_FILE);
            $pluginVersion = $pluginData['Version'];
            $log_file = ini_get('error_log');
            $lastLines = [];
            if (file_exists($log_file)) {
                $lines = file($log_file);
                $lastLines = array_slice($lines, -600);

                foreach ($lastLines as $key => $line) {
                    if (
                        str_contains($line, 'PHP Warning') === true
                        || str_contains($line, 'PHP Fatal error') === true
                    ) {
                        continue;
                    }

                    unset($lastLines[$key]);
                }
            }

            foreach ($lastLines as $key => $line) {
                if (str_contains($line, 'plugins/storelinkr') === false) {
                    unset($lastLines[$key]);
                }
            }

            $lastLines = array_reverse($lastLines);

            require_once(STORELINKR_PLUGIN_DIR . 'views/storelinkr_diagnostic.php');
            return;
        }

        require_once(STORELINKR_PLUGIN_DIR . 'views/storelinkr_dashboard.php');
    }

    public function settingsLink($links): array
    {
        $url = esc_url(
            add_query_arg(
                'page',
                'storelinkr',
                get_admin_url() . 'admin.php'
            )
        );

        $settings_link = "<a href='" . esc_url($url) . "'>StoreLinkr</a>";
        array_push($links, $settings_link);

        return $links;
    }

    public function removeCategoryTrashLink($actions, $term): array
    {
        if ($term->taxonomy !== 'product_cat') {
            return $actions;
        }

        $meta = get_term_meta($term->term_id, 'import_provider');
        if ($meta === false) {
            return $actions;
        }

        if (in_array('STORELINKR', $meta) === true) {
            $actions = array_merge([
                'id' => esc_attr('ID: ' . $term->term_id),
                'label' => esc_attr('StoreLinkr'),
            ], $actions);

            unset($actions['delete']);

            $actions['storelinkr'] = '<a href="https://portal.storelinkr.com" target="_blank">' .
                esc_attr(__('Open in StoreLinkr', 'storelinkr')) . '</a>';
        }

        return $actions;
    }

    public function storeLinkrProductMessage($post)
    {
        if ($post->post_type === 'sl_stock_location') {
            echo '<div class="notice notice-info inline notice-storelinkr" style="margin: 15px 0;">';
            echo '<img src="' . esc_attr(
                    STORELINKR_PLUGIN_URL . 'images/icon_storelinkr_64.png'
                ) . '" alt="StoreLinkr">';
            echo '<p><strong>' . __('Please note', 'storelinkr') . ':</strong> ';
            echo __(
                    'This stock location is automatically updated by StoreLinkr. You can manage this location in the StoreLinkr portal.',
                    'storelinkr'
                ) . '</p>';
            echo '</div>';
            return;
        }

        // Alleen tonen bij WooCommerce producten
        if ($post->post_type !== 'product') {
            return;
        }

        $meta = get_post_meta($post->ID, 'import_provider');
        if ($meta === false) {
            return;
        }

        if (in_array('STORELINKR', $meta) === true) {
            echo '<div class="notice notice-info inline notice-storelinkr" style="margin: 15px 0;">';
            echo '<img src="' . esc_attr(
                    STORELINKR_PLUGIN_URL . 'images/icon_storelinkr_64.png'
                ) . '" alt="StoreLinkr">';
            echo '<p><strong>' . __('Please note', 'storelinkr') . ':</strong> ';
            echo __(
                    'This product is automatically updated by StoreLinkr. Basic fields of this product can be edited in the StoreLinkr portal.',
                    'storelinkr'
                ) . '</p>';
            echo '</div>';
        }
    }

    public function removeProductTrashLink($actions, $post): array
    {
        if ($post->post_type !== 'product') {
            return $actions;
        }

        $meta = get_post_meta($post->ID, 'import_provider');
        if ($meta === false) {
            return $actions;
        }

        if (in_array('STORELINKR', $meta) === true) {
            $actions = array_merge(['label' => esc_attr('StoreLinkr')], $actions);

            unset($actions['trash']);

            $actions['storelinkr'] = '<a href="https://portal.storelinkr.com" target="_blank">' .
                esc_attr(__('Open in StoreLinkr', 'storelinkr')) . '</a>';
        }

        return $actions;
    }

    public function storeLinkrProductCustomTab($tabs): array
    {
        $tabs['storelinkr_stock_locations'] = [
            'label' => __('Stock locations', 'storelinkr'),
            'target' => 'storelinkr_stock_locations',
            'class' => ['show_if_simple', 'show_if_variable', 'show_if_grouped'],
            'priority' => 24,
        ];
        $tabs['storelinkr_attachments'] = [
            'label' => __('Attachments', 'storelinkr'),
            'target' => 'storelinkr_attachments',
            'class' => ['show_if_simple', 'show_if_variable', 'show_if_grouped'],
            'priority' => 60,
        ];

        return $tabs;
    }

    public function productAttachmentTabContent()
    {
        global $post;

        echo '<div id="storelinkr_attachments" class="panel woocommerce_options_panel storelinkr-product-page-content">';
        echo '<div class="options_group">';

        if ($post && $post->post_type === 'product') {
            $product = wc_get_product($post->ID);

            if ($product) {
                $attachments = $product->get_meta('_product_attachments', true);

                if (!empty($attachments) && $attachments !== "[]") {
                    $attachments = json_decode($attachments, true);

                    echo '<table class="wp-list-table widefat striped">';

                    if (is_iterable($attachments) && count($attachments) >= 1) {
                        foreach ($attachments as $attachment) {
                            echo '<tr>';
                            echo '<td>' . esc_attr($attachment['title']) . '</td>';
                            echo '<td>' . esc_attr($attachment['name']) . '</td>';
                            echo '<td><a href="' . esc_attr($attachment['cdn_url']) . '" target="_blank">';
                            echo __('View attachment', 'storelinkr');
                            echo '</a></td>';
                            echo '</tr>';
                        }
                    }

                    echo '</table>';
                } else {
                    echo '<table class="wp-list-table widefat striped">';
                    echo '<tr>';
                    echo '<td><i>' . __('No attachments found for this product.', 'storelinkr') . '</i><br /><br />';
                    echo '<a href="https://portal.storelinkr.com" target="_blank">';
                    echo __('Manage attachments', 'storelinkr');
                    echo '<span class="dashicons dashicons-external"></span>';
                    echo '</a>';
                    echo '</td>';
                    echo '</tr>';
                    echo '</table>';
                }
            }
        }

        echo '</div>';
        echo '</div>';
    }

    public function productStockLocationsTabContent()
    {
        global $post;

        echo '<div id="storelinkr_stock_locations" class="panel woocommerce_options_panel storelinkr-product-page-content">';
        echo '<div class="options_group">';
        if ($post && $post->post_type === 'product') {
            $product = wc_get_product($post->ID);
            if ($product) {
                $stockLocations = $product->get_meta('stock_locations', true);

                if (!empty($stockLocations)) {
                    if (is_string($stockLocations)) {
                        $stockLocations = json_decode($stockLocations, true);
                    }

                    echo '<table class="wp-list-table widefat striped">';

                    if (is_iterable($stockLocations) && count($stockLocations) >= 1) {
                        foreach ($stockLocations as $stockLocation) {
                            if (empty($stockLocation['post_id'])) {
                                continue;
                            }

                            $locationPost = get_post($stockLocation['post_id']);
                            $address = get_post_meta($locationPost->ID, '_address', true);
                            echo '<tr>';
                            echo '<td><strong>' . esc_attr($locationPost->post_title) . '</strong><br />';
                            if (!empty($address)) {
                                echo esc_attr($address) . ', ';
                                echo esc_attr(get_post_meta($locationPost->ID, '_city', true));
                            }
                            echo '</td>';
                            echo '<td>' . esc_attr($stockLocation['quantity']) . ' ' . __('pieces', 'storelinkr') . '</td>';
                            echo '</tr>';
                        }
                    }

                    echo '</table>';
                } else {
                    echo '<table class="wp-list-table widefat striped">';
                    echo '<tr>';
                    echo '<td><i>' . __(
                            'No stock locations found for this product.',
                            'storelinkr'
                        ) . '</i><br /><br />';
                    echo '<a href="https://portal.storelinkr.com" target="_blank">';
                    echo __('Manage stock locations', 'storelinkr');
                    echo '<span class="dashicons dashicons-external"></span>';
                    echo '</a>';
                    echo '</td>';
                    echo '</tr>';
                    echo '</table>';
                }
            }
        }

        echo '</div>';
        echo '</div>';
    }

    public function renderStockLocationEditPostMetaBox($post)
    {
        echo '<table class="form-table">';
        echo '<tr><th><label for="sl_city">' . esc_attr(__('Phone number', 'storelinkr')) . '</label></th>';
        echo '<td><input type="text" name="sl_phone_number" id="sl_phone_number" value="' . esc_attr(
                get_post_meta($post->ID, '_phone_number', true)
            ) . '" class="regular-text" disabled /></td></tr>';
        echo '<tr><th><label for="sl_location_code">' . esc_attr(__('Address', 'storelinkr')) . '</label></th>';
        echo '<td><input type="text" name="sl_address" id="sl_address" value="' . esc_attr(
                get_post_meta($post->ID, '_address', true)
            ) . '" class="regular-text" disabled /></td></tr>';
        echo '<tr><th><label for="sl_location_code">' . esc_attr(__('Postal code', 'storelinkr')) . '</label></th>';
        echo '<td><input type="text" name="sl_postal_code" id="sl_postal_code" value="' . esc_attr(
                get_post_meta($post->ID, '_postal_code', true)
            ) . '" class="regular-text" disabled /></td></tr>';
        echo '<tr><th><label for="sl_city">' . esc_attr(__('City', 'storelinkr')) . '</label></th>';
        echo '<td><input type="text" name="sl_city" id="sl_city" value="' . esc_attr(
                get_post_meta($post->ID, '_city', true)
            ) . '" class="regular-text" disabled /></td></tr>';
        echo '<tr><th><label for="sl_city">' . esc_attr(__('Country', 'storelinkr')) . '</label></th>';
        echo '<td><input type="text" name="sl_country_code" id="sl_country_code" value="' . esc_attr(
                get_post_meta($post->ID, '_country_code', true)
            ) . '" class="regular-text" disabled /></td></tr>';
        echo '</table>';

        // Optional nonce for security
        wp_nonce_field('save_stock_location_meta', 'stock_location_nonce');
    }


    private function getSvgIcon(bool $baseEncode = true): string
    {
        $svg = '<svg version="1.1" xmlns="http://www.w3.org/2000/svg" width="128" height="128">
<path d="M0 0 C4.28355078 1.84185543 7.3047911 5.62506955 10.55029297 8.8815918 C11.30955078 9.61249023 12.06880859 10.34338867 12.85107422 11.09643555 C18.36534404 16.59139094 22.1626289 21.89809195 23.54638672 29.66674805 C23.31756157 38.5604187 21.39500484 43.66425727 15.33935547 50.18237305 C14.86045364 50.69785202 14.38155182 51.21333099 13.88813782 51.74443054 C12.28299993 53.46402426 10.66844534 55.17424115 9.05029297 56.8815918 C8.47723419 57.48632324 7.90417542 58.09105469 7.31375122 58.71411133 C3.62990492 62.58737514 -0.09022744 66.4225744 -3.84008789 70.23193359 C-5.13753766 71.55641014 -6.42776444 72.88799563 -7.71118164 74.22607422 C-9.5932124 76.18674994 -11.49685356 78.12386838 -13.40673828 80.05737305 C-13.97560257 80.65922928 -14.54446686 81.26108551 -15.13056946 81.88117981 C-16.78100586 83.5234375 -16.78100586 83.5234375 -19.94970703 85.8815918 C-23.10864258 85.74560547 -23.10864258 85.74560547 -25.94970703 84.8815918 C-27.26970703 84.8815918 -28.58970703 84.8815918 -29.94970703 84.8815918 C-30.60970703 83.5615918 -31.26970703 82.2415918 -31.94970703 80.8815918 C-30.76220703 79.3190918 -30.76220703 79.3190918 -28.94970703 77.8815918 C-28.22783203 78.1497168 -27.50595703 78.4178418 -26.76220703 78.6940918 C-24.00617691 79.17192329 -24.00617691 79.17192329 -22.32543945 77.75097656 C-20.51937974 76.04251379 -18.82042 74.27367111 -17.13720703 72.4440918 C-16.53972656 71.81954102 -15.94224609 71.19499023 -15.32666016 70.55151367 C-13.85381961 69.00797677 -12.39989232 67.44643302 -10.94970703 65.8815918 C-11.60970703 65.8815918 -12.26970703 65.8815918 -12.94970703 65.8815918 C-15.460829 68.45142705 -17.85143182 71.113735 -20.24658203 73.79174805 C-22.94970703 75.8815918 -22.94970703 75.8815918 -26.09033203 75.56518555 C-27.03392578 75.33959961 -27.97751953 75.11401367 -28.94970703 74.8815918 C-30.43470703 75.8715918 -30.43470703 75.8715918 -31.94970703 76.8815918 C-33.51220703 75.6315918 -33.51220703 75.6315918 -34.94970703 73.8815918 C-34.88720703 71.8190918 -34.88720703 71.8190918 -33.94970703 69.8815918 C-31.88720703 68.6315918 -31.88720703 68.6315918 -29.94970703 67.8815918 C-28.95970703 68.5415918 -27.96970703 69.2015918 -26.94970703 69.8815918 C-21.66970703 64.6015918 -16.38970703 59.3215918 -10.94970703 53.8815918 C-13.91348543 53.54306353 -13.91348543 53.54306353 -15.41455078 55.22924805 C-16.39650631 56.37269228 -17.37567772 57.51853333 -18.35205078 58.66674805 C-18.87927734 59.06764648 -19.40650391 59.46854492 -19.94970703 59.8815918 C-22.14111328 59.56518555 -22.14111328 59.56518555 -23.94970703 58.8815918 C-24.74438477 57.20678711 -24.74438477 57.20678711 -24.94970703 54.8815918 C-23.04370117 52.40795898 -23.04370117 52.40795898 -20.23486328 49.6784668 C-19.74513535 49.1958136 -19.25540741 48.7131604 -18.75083923 48.21588135 C-17.18262476 46.67567544 -15.59793673 45.15373875 -14.01220703 43.6315918 C-12.9466229 42.59108214 -11.88215457 41.54942846 -10.81884766 40.5065918 C-8.20862192 37.95151954 -5.58333852 35.41255683 -2.94970703 32.8815918 C-4.23145422 30.06281168 -5.55978318 28.02917684 -7.77001953 25.8659668 C-8.57246094 25.07319336 -8.57246094 25.07319336 -9.39111328 24.2644043 C-9.94669922 23.72557617 -10.50228516 23.18674805 -11.07470703 22.6315918 C-11.63802734 22.07729492 -12.20134766 21.52299805 -12.78173828 20.9519043 C-14.16656731 19.59054695 -15.55753907 18.23544321 -16.94970703 16.8815918 C-14.92669757 12.44532626 -11.97102945 9.4990782 -8.57470703 6.0690918 C-7.99720703 5.4587207 -7.41970703 4.84834961 -6.82470703 4.21948242 C-6.26009766 3.6477832 -5.69548828 3.07608398 -5.11376953 2.48706055 C-4.60281738 1.96265381 -4.09186523 1.43824707 -3.56542969 0.89794922 C-1.94970703 -0.1184082 -1.94970703 -0.1184082 0 0 Z " fill="#FFFFFF" transform="translate(81.94970703125,40.118408203125)"/>
<path d="M0 0 C0.83371124 0.22441269 1.66742249 0.44882538 2.52639771 0.68003845 C3.84639771 0.68003845 5.16639771 0.68003845 6.52639771 0.68003845 C7.18639771 1.34003845 7.84639771 2.00003845 8.52639771 2.68003845 C8.15139771 4.80503845 8.15139771 4.80503845 7.52639771 6.68003845 C4.63405455 7.50642221 2.6389734 7.68003845 -0.47360229 7.68003845 C-2.70875246 9.62566139 -4.7029278 11.53367607 -6.72360229 13.68003845 C-7.27789917 14.2523822 -7.83219604 14.82472595 -8.40328979 15.41441345 C-9.76924351 16.82746902 -11.1226584 18.25262604 -12.47360229 19.68003845 C-9.29986252 20.02712881 -9.29986252 20.02712881 -7.16891479 17.4573822 C-5.45641869 15.56530824 -3.74807202 13.6694703 -2.04391479 11.7698822 C0.52639771 9.68003845 0.52639771 9.68003845 3.20217896 9.89878845 C3.96917114 10.15660095 4.73616333 10.41441345 5.52639771 10.68003845 C7.75806956 10.31146982 7.75806956 10.31146982 9.52639771 9.68003845 C10.51639771 11.00003845 11.50639771 12.32003845 12.52639771 13.68003845 C10.87639771 15.00003845 9.22639771 16.32003845 7.52639771 17.68003845 C6.53639771 17.02003845 5.54639771 16.36003845 4.52639771 15.68003845 C1.87651505 18.30097767 -0.76780372 20.92746043 -3.41110229 23.55503845 C-4.16584839 24.30140564 -4.92059448 25.04777283 -5.69821167 25.8167572 C-6.41686401 26.53218689 -7.13551636 27.24761658 -7.87594604 27.98472595 C-8.54102173 28.64456482 -9.20609741 29.30440369 -9.8913269 29.98423767 C-11.5549734 31.60677777 -11.5549734 31.60677777 -12.47360229 33.68003845 C-11.67696167 32.93947205 -11.67696167 32.93947205 -10.86422729 32.1839447 C-10.15782104 31.54328064 -9.45141479 30.90261658 -8.72360229 30.24253845 C-8.02750854 29.60445251 -7.33141479 28.96636658 -6.61422729 28.3089447 C-4.46930577 26.67676901 -3.14036873 25.95123504 -0.47360229 25.68003845 C1.27639771 27.11753845 1.27639771 27.11753845 2.52639771 28.68003845 C0.9872176 32.3475585 -1.22845681 34.66584418 -4.04391479 37.44566345 C-4.93981323 38.33511658 -5.83571167 39.2245697 -6.75875854 40.14097595 C-7.69590698 41.06136658 -8.63305542 41.9817572 -9.59860229 42.93003845 C-10.55348866 43.87488702 -11.50792088 44.82019479 -12.46188354 45.76597595 C-14.79469316 48.07522183 -17.13204316 50.37967048 -19.47360229 52.68003845 C-17.36489687 57.3104375 -13.91367907 60.25920038 -10.19821167 63.6136322 C-8.71188354 65.02378845 -8.71188354 65.02378845 -6.47360229 67.68003845 C-6.91617656 72.7103804 -10.90068172 75.62072761 -14.28610229 78.99253845 C-14.91580933 79.65060486 -15.54551636 80.30867126 -16.19430542 80.98667908 C-16.8085437 81.60349548 -17.42278198 82.22031189 -18.05563354 82.8558197 C-18.61484497 83.42212097 -19.1740564 83.98842224 -19.75021362 84.57188416 C-21.47360229 85.68003845 -21.47360229 85.68003845 -23.45285034 85.56382751 C-27.537261 83.77748384 -30.37886898 80.24310697 -33.47360229 77.11753845 C-34.18516479 76.42595642 -34.89672729 75.73437439 -35.62985229 75.02183533 C-42.06864594 68.57703534 -46.0121114 62.42307825 -46.56344604 53.1917572 C-46.04225084 38.62095199 -31.19418067 28.09444563 -21.69381714 18.495224 C-20.12050691 16.90013048 -18.55350385 15.29879303 -16.99264526 13.69151306 C-14.72707988 11.35939699 -12.44275545 9.04725954 -10.15328979 6.7386322 C-9.45734711 6.01485886 -8.76140442 5.29108551 -8.04437256 4.54537964 C-7.38663849 3.89042007 -6.72890442 3.23546051 -6.05123901 2.56065369 C-5.47944916 1.97855637 -4.9076593 1.39645905 -4.31854248 0.79672241 C-2.47360229 -0.31996155 -2.47360229 -0.31996155 0 0 Z " fill="#FFFFFF" transform="translate(64.47360229492188,3.3199615478515625)"/>
<path d="M0 0 C2.87652865 1.29868921 4.88246882 2.65481133 7.05859375 4.93359375 C7.60966797 5.50013672 8.16074219 6.06667969 8.72851562 6.65039062 C9.86402149 7.83780533 10.99943877 9.02530476 12.13476562 10.21289062 C15.2543462 13.41983401 18.1250075 15.72488667 22 18 C24.23243973 19.87362293 26.30377545 21.88873451 28.375 23.9375 C28.93316406 24.46279297 29.49132813 24.98808594 30.06640625 25.52929688 C31.6484375 27.08984375 31.6484375 27.08984375 34 30 C33.57966314 35.02405598 29.56469709 37.94887958 26.1875 41.3125 C25.55779297 41.97056641 24.92808594 42.62863281 24.27929688 43.30664062 C23.66505859 43.92345703 23.05082031 44.54027344 22.41796875 45.17578125 C21.85875732 45.74208252 21.2995459 46.30838379 20.72338867 46.8918457 C19 48 19 48 17.02075195 47.88378906 C12.93634129 46.09744539 10.09473331 42.56306852 7 39.4375 C6.2884375 38.74591797 5.576875 38.05433594 4.84375 37.34179688 C-1.60660219 30.88542757 -5.49394696 24.75705637 -6.10546875 15.52734375 C-5.86522261 9.7703343 -3.91534005 4.28822958 0 0 Z " fill="#FFFFFF" transform="translate(24,41)"/>
<path d="M0 0 C2.36748004 1.00931523 3.67842428 2.1779416 5.47119141 4.02050781 C6.08994141 4.64892578 6.70869141 5.27734375 7.34619141 5.92480469 C7.98041016 6.58416016 8.61462891 7.24351563 9.26806641 7.92285156 C9.91775391 8.58607422 10.56744141 9.24929687 11.23681641 9.93261719 C12.83753401 11.56864473 14.43082833 13.21126069 16.01806641 14.86035156 C12.38875068 19.1891899 8.66839857 23.32691578 4.64306641 27.29785156 C3.76908203 28.1628125 2.89509766 29.02777344 1.99462891 29.91894531 C1.34236328 30.55960938 0.69009766 31.20027344 0.01806641 31.86035156 C-2.91423552 30.53914328 -4.96858259 29.11396959 -7.21630859 26.82128906 C-7.78994141 26.24121094 -8.36357422 25.66113281 -8.95458984 25.06347656 C-9.54111328 24.46019531 -10.12763672 23.85691406 -10.73193359 23.23535156 C-11.33392578 22.62433594 -11.93591797 22.01332031 -12.55615234 21.38378906 C-14.03598665 19.88046532 -15.50958036 18.371003 -16.98193359 16.86035156 C-14.95892413 12.42408603 -12.00325601 9.47783797 -8.60693359 6.04785156 C-7.74068359 5.13229492 -7.74068359 5.13229492 -6.85693359 4.19824219 C-6.29232422 3.62654297 -5.72771484 3.05484375 -5.14599609 2.46582031 C-4.63504395 1.94141357 -4.1240918 1.41700684 -3.59765625 0.87670898 C-1.98193359 -0.13964844 -1.98193359 -0.13964844 0 0 Z " fill="#FFFFFF" transform="translate(81.98193359375,40.1396484375)"/>
<path d="M0 0 C3.43370999 1.38502588 4.73405974 2.63124387 6.25 6 C9.19144259 13.24315706 8.81460702 19.98408167 6.046875 27.2109375 C5 29 5 29 1 33 C-4.28 27.72 -9.56 22.44 -15 17 C-13.35412625 13.7082525 -12.4470074 12.20294843 -9.9609375 9.765625 C-9.38085938 9.19199219 -8.80078125 8.61835937 -8.203125 8.02734375 C-7.59984375 7.44082031 -6.9965625 6.85429688 -6.375 6.25 C-5.76398438 5.64800781 -5.15296875 5.04601563 -4.5234375 4.42578125 C-3.02011376 2.94594695 -1.51065144 1.47235324 0 0 Z " fill="#FFFFFF" transform="translate(97,56)"/>
<path d="M0 0 C2.93230193 1.32120828 4.98664899 2.74638197 7.234375 5.0390625 C8.09482422 5.90917969 8.09482422 5.90917969 8.97265625 6.796875 C9.55917969 7.40015625 10.14570312 8.0034375 10.75 8.625 C11.35199219 9.23601563 11.95398438 9.84703125 12.57421875 10.4765625 C14.05405305 11.97988624 15.52764676 13.48934856 17 15 C15.67879172 17.93230193 14.25361803 19.98664899 11.9609375 22.234375 C11.38085938 22.80800781 10.80078125 23.38164063 10.203125 23.97265625 C9.59984375 24.55917969 8.9965625 25.14570312 8.375 25.75 C7.76398437 26.35199219 7.15296875 26.95398438 6.5234375 27.57421875 C5.02011376 29.05405305 3.51065144 30.52764676 2 32 C-1.37673632 30.87442123 -1.59052027 30.56500561 -3.125 27.5625 C-6.09426693 21.1424634 -6.85940859 14.92198323 -4.75 8.125 C-3.56600871 5.04225769 -2.2498136 2.46408156 0 0 Z " fill="#FFFFFF" transform="translate(24,41)"/>
<path d="M0 0 C-1.65270998 4.27638706 -4.77743463 7.0012397 -8 10.125 C-8.5465625 10.66253906 -9.093125 11.20007813 -9.65625 11.75390625 C-11.10031738 13.17319797 -12.54984801 14.58692576 -14 16 C-14.66 16.66 -15.32 17.32 -16 18 C-16 14 -16 14 -14.74658203 12.26074219 C-13.88589111 11.41237793 -13.88589111 11.41237793 -13.0078125 10.546875 C-12.07388672 9.61875 -12.07388672 9.61875 -11.12109375 8.671875 C-10.46238281 8.03765625 -9.80367188 7.4034375 -9.125 6.75 C-8.47402344 6.1003125 -7.82304688 5.450625 -7.15234375 4.78125 C-2.28069196 0 -2.28069196 0 0 0 Z " fill="#FFFFFF" transform="translate(99,56)"/>
<path d="M0 0 C1.32 0.99 2.64 1.98 4 3 C3.75 4.875 3.75 4.875 3 7 C0.9375 8.25 0.9375 8.25 -1 9 C-2.5625 7.75 -2.5625 7.75 -4 6 C-3.875 3.9375 -3.875 3.9375 -3 2 C-2.01 1.34 -1.02 0.68 0 0 Z " fill="#FFFFFF" transform="translate(69,22)"/>
<path d="M0 0 C1.65 1.65 3.3 3.3 5 5 C3.68 6.32 2.36 7.64 1 9 C-0.32 8.34 -1.64 7.68 -3 7 C-3 3 -3 3 -1.5 1.25 C-1.005 0.8375 -0.51 0.425 0 0 Z " fill="#FFFFFF" transform="translate(54,98)"/>
</svg>
';
        if ($baseEncode === true) {
            return 'data:image/svg+xml;base64,' . base64_encode($svg);
        }

        return $svg;
    }

    private function initApiKeys(): void
    {
        update_option(
            self::STORELINKR_API_KEY,
            $this->generateKey(self::STORELINKR_API_KEY, 2500)
        );
        update_option(
            self::STORELINKR_API_SECRET,
            $this->generateKey(self::STORELINKR_API_SECRET, 5000)
        );
    }

    private function generateKey(string $input, int $rangeEnd): string
    {
        return md5($input . wp_rand(1, $rangeEnd) . time() . wp_rand(1, $rangeEnd));
    }

}
