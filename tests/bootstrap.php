<?php
/**
 * Bootstrap file for StoreLinkr tests
 */

// Set environment
define('PHPUNIT_RUNNING', true);

// Load Composer autoloader
require_once dirname(__DIR__) . '/vendor/autoload.php';

// Define WordPress constants for testing
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(dirname(dirname(dirname(__DIR__)))) . '/');
}

if (!defined('STORELINKR_PLUGIN_DIR')) {
    define('STORELINKR_PLUGIN_DIR', dirname(__DIR__, 2) . '/storelinkr/');
}

// Mock WordPress functions that might be used in tests
if (!function_exists('wc_sanitize_taxonomy_name')) {
    function wc_sanitize_taxonomy_name($name) {
        return sanitize_title($name);
    }
}

if (!function_exists('sanitize_title')) {
    function sanitize_title($title) {
        return strtolower(str_replace(' ', '_', trim($title)));
    }
}

if (!function_exists('sanitize_key')) {
    function sanitize_key($key) {
        return preg_replace('/[^a-z0-9_\-]/', '', strtolower(trim($key)));
    }
}

if (!function_exists('get_option')) {
    function get_option($option, $default = false) {
        global $storelinkrTestOptions;

        return $storelinkrTestOptions[$option] ?? $default;
    }
}

if (!function_exists('update_option')) {
    function update_option($option, $value, $autoload = null) {
        global $storelinkrTestOptions;

        $storelinkrTestOptions[$option] = $value;

        return true;
    }
}

if (!function_exists('register_post_meta')) {
    function register_post_meta($postType, $metaKey, $args = []) {
        return true;
    }
}

if (!function_exists('current_user_can')) {
    function current_user_can($capability) {
        return true;
    }
}

if (!function_exists('taxonomy_exists')) {
    function taxonomy_exists($taxonomy) {
        return false; // For testing, assume taxonomies don't exist
    }
}

if (!function_exists('wc_create_attribute')) {
    function wc_create_attribute($args) {
        return true;
    }
}

if (!function_exists('delete_transient')) {
    function delete_transient($transient) {
        return true;
    }
}

if (!function_exists('flush_rewrite_rules')) {
    function flush_rewrite_rules() {
        return true;
    }
}

if (!function_exists('wc_attribute_taxonomy_name')) {
    function wc_attribute_taxonomy_name($slug) {
        return 'pa_' . $slug;
    }
}

if (!function_exists('register_taxonomy')) {
    function register_taxonomy($taxonomy, $objectType, $args = []) {
        return true;
    }
}

if (!function_exists('apply_filters')) {
    function apply_filters($hook, $value, ...$args) {
        return $value;
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing) {
        return $thing instanceof WP_Error;
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error {
        private $code;
        private $message;

        public function __construct($code = '', $message = '') {
            $this->code = $code;
            $this->message = $message;
        }

        public function get_error_message() {
            return $this->message;
        }
    }
}

if (!function_exists('term_exists')) {
    function term_exists($term, $taxonomy) {
        return false;
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy) {
        return ['term_id' => rand(1, 1000)];
    }
}

if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy) {
        return (object) ['slug' => sanitize_title($value), 'term_id' => rand(1, 1000)];
    }
}

// Mock WooCommerce classes
if (!class_exists('WC_Product_Attribute')) {
    class WC_Product_Attribute {
        private $id;
        private $name;
        private $options;
        private $visible;
        private $variation;
        
        public function set_id($id) { $this->id = $id; }
        public function set_name($name) { $this->name = $name; }
        public function set_options($options) { $this->options = $options; }
        public function set_visible($visible) { $this->visible = $visible; }
        public function set_variation($variation) { $this->variation = $variation; }
        public function get_variation() { return $this->variation; }
    }
}

if (!class_exists('WC_Data')) {
    /**
     * Keeps pending prop changes and meta like WooCommerce does, so tests can tell whether a save is needed.
     */
    class WC_Data {
        protected $id = 0;
        protected $changes = [];
        protected $meta_data = [];

        public function get_id() { return $this->id; }
        public function get_changes() { return $this->changes; }
        public function get_meta_data() { return $this->meta_data; }

        // Swallow any WooCommerce setter/getter the code under test touches.
        public function __call($name, $arguments) { return null; }
    }
}

if (!class_exists('WC_Product')) {
    class WC_Product extends WC_Data {
    }
}

if (!class_exists('WC_Product_Simple')) {
    class WC_Product_Simple extends WC_Product {
    }
}

if (!class_exists('WC_Product_Variable')) {
    class WC_Product_Variable extends WC_Product {
    }
}

/**
 * wc_get_product() returns $GLOBALS['mockProductsById'][$id] when the id is registered there,
 * otherwise the shared $GLOBALS['mockVariableProduct'].
 */
if (!function_exists('wc_get_product')) {
    function wc_get_product($id = false) {
        if (isset($GLOBALS['mockProductsById']) && array_key_exists($id, $GLOBALS['mockProductsById'])) {
            return $GLOBALS['mockProductsById'][$id];
        }

        return $GLOBALS['mockVariableProduct'] ?? false;
    }
}

/**
 * Records permanently deleted post ids in $GLOBALS['deletedPostIds'].
 */
if (!function_exists('wp_delete_post')) {
    function wp_delete_post($postId = 0, $forceDelete = false) {
        $GLOBALS['deletedPostIds'][] = (int)$postId;

        return true;
    }
}

if (!class_exists('WC_Product_Variation')) {
    class WC_Product_Variation extends WC_Product {
        private static $nextId = 1000;

        public $saveCount = 0;
        private $parent_id;
        private $attributes = [];
        private $regular_price = '';

        public function __construct($id = 0) { $this->id = (int)$id; }
        public function set_parent_id($id) { $this->parent_id = $id; }
        public function set_attributes($attributes) {
            if ($attributes !== $this->attributes) {
                $this->changes['attributes'] = $attributes;
            }
            $this->attributes = $attributes;
        }
        public function get_attributes() { return $this->attributes; }
        public function save() {
            if ($this->id === 0) {
                $this->id = self::$nextId++;
            }
            $this->saveCount++;
            $this->changes = [];

            return $this->id;
        }
        public function update_meta_data($key, $value, $meta_id = 0) { }
        public function set_regular_price($price) { $this->regular_price = $price; }
        public function get_regular_price() { return $this->regular_price; }
    }
}

echo "Bootstrap loaded successfully\n";
/**
 * In memory post meta: $GLOBALS['postMeta'][$postId][$key] is a list of rows.
 */
if (!function_exists('get_post_meta')) {
    function get_post_meta($postId, $key = '', $single = false) {
        $rows = $GLOBALS['postMeta'][$postId][$key] ?? [];

        return $single ? ($rows[0] ?? '') : $rows;
    }
}

if (!function_exists('delete_post_meta')) {
    function delete_post_meta($postId, $key, $value = '') {
        unset($GLOBALS['postMeta'][$postId][$key]);

        return true;
    }
}

if (!function_exists('add_post_meta')) {
    function add_post_meta($postId, $key, $value, $unique = false) {
        $GLOBALS['postMeta'][$postId][$key][] = $value;

        return true;
    }
}

if (!function_exists('wp_slash')) {
    function wp_slash($value) {
        return $value;
    }
}

/**
 * GTIN lookups read $GLOBALS['gtinIndex'][$gtin] and are counted in $GLOBALS['gtinLookups'].
 */
if (!function_exists('wc_get_product_id_by_global_unique_id')) {
    function wc_get_product_id_by_global_unique_id($gtin) {
        $GLOBALS['gtinLookups'] = ($GLOBALS['gtinLookups'] ?? 0) + 1;

        return $GLOBALS['gtinIndex'][$gtin] ?? 0;
    }
}

if (!function_exists('get_posts')) {
    function get_posts($args = []) {
        return [];
    }
}

if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field($text) {
        return trim((string)$text);
    }
}

if (!function_exists('wc_delete_product_transients')) {
    function wc_delete_product_transients($id = 0) {
        return true;
    }
}

if (!function_exists('absint')) {
    function absint($maybeint) {
        return abs((int)$maybeint);
    }
}

if (!function_exists('get_post')) {
    function get_post($post = null) {
        if (isset($GLOBALS['mockPosts']) && array_key_exists($post, $GLOBALS['mockPosts'])) {
            return $GLOBALS['mockPosts'][$post];
        }

        return null;
    }
}
