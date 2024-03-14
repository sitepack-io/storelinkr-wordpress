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
//        add_action('admin_menu', [$this, 'registerSettingsPage']);
        add_action('admin_init', [$this, 'adminInit']);
        add_action('admin_menu', [$this, 'adminPages']);
        add_action('admin_enqueue_scripts', [$this, 'addAdminStyles']);
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

        $settings_link = "<a href='" . esc_url($url) . "'>" . esc_html__('Settings', 'storelinkr') . '</a>';
        array_push($links, $settings_link);

        return $links;
    }

    private function getSvgIcon(bool $baseEncode = true): string
    {
        $svg = '<svg id="svg" xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="400" height="400" viewBox="0, 0, 400,400"><g id="svgg"><path id="path0" d="M55.524 107.388 L 57.123 194.776 82.818 218.324 L 108.514 241.872 57.693 238.611 C 4.934 235.225,-2.859 236.803,5.079 249.263 C 7.673 253.335,10.245 258.167,10.795 260.000 C 19.424 288.797,23.421 293.371,43.195 297.080 C 55.144 299.322,72.063 300.134,80.793 298.884 C 92.920 297.149,99.814 301.878,110.000 318.921 C 130.660 353.487,193.267 384.407,220.096 373.294 C 251.579 360.253,293.365 327.743,299.314 311.661 C 303.831 299.450,309.240 297.433,333.999 298.727 C 369.619 300.588,374.309 297.447,387.591 262.827 L 398.016 235.653 345.473 238.695 L 292.930 241.736 315.939 218.727 C 341.504 193.163,351.591 158.106,341.818 128.787 C 338.017 117.385,337.997 99.044,341.767 81.369 C 345.148 65.516,345.998 44.909,343.655 35.574 C 339.124 17.522,339.052 17.522,291.235 35.184 C 271.770 42.374,257.953 43.025,232.340 37.961 C 209.574 33.460,188.993 33.606,167.963 38.418 C 142.010 44.357,131.673 43.448,103.525 32.749 C 51.523 12.984,53.734 9.547,55.524 107.388 M182.252 104.071 L 199.656 121.474 220.340 104.071 C 248.299 80.544,275.613 81.254,300.513 106.154 C 348.377 154.018,296.102 221.110,230.545 195.953 C 209.822 188.001,220.508 181.695,249.221 184.931 C 300.527 190.714,326.953 143.592,289.101 113.817 C 256.125 87.878,209.096 108.108,214.635 145.849 C 216.844 160.897,214.783 163.333,199.844 163.333 C 186.017 163.333,183.010 160.591,185.063 149.851 C 191.721 115.023,145.371 88.977,114.817 110.378 C 80.538 134.388,104.177 188.969,147.806 186.547 C 179.779 184.773,184.597 186.054,173.333 193.333 C 144.686 211.846,99.274 196.494,86.973 164.138 C 65.531 107.742,139.372 61.190,182.252 104.071 M160.000 146.283 C 160.000 159.028,135.953 169.633,129.763 159.617 C 125.461 152.655,147.870 127.645,155.000 131.451 C 157.750 132.919,160.000 139.593,160.000 146.283 M276.667 147.037 C 276.667 164.378,246.987 170.961,241.605 154.815 C 240.111 150.333,243.639 146.667,249.444 146.667 C 255.250 146.667,260.000 141.917,260.000 136.111 C 260.000 130.306,263.750 126.806,268.333 128.333 C 272.917 129.861,276.667 138.278,276.667 147.037 " stroke="none" fill="#a7aaad" fill-rule="evenodd"></path></g></svg>';

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