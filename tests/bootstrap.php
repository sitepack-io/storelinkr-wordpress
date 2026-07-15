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

// Per-test failure-simulation switches (reset to a known state on bootstrap):
//   - storelinkr_test_unresolvable_terms:      option values get_term_by()/term creation can never resolve
//   - storelinkr_test_force_unpersisted_price: when true, WC_Product_Variation::get_regular_price() returns ''
$GLOBALS['storelinkr_test_unresolvable_terms'] = [];
$GLOBALS['storelinkr_test_force_unpersisted_price'] = false;

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
    function apply_filters($hook, $value) {
        return $value;
    }
}

// Tests can push option values into $GLOBALS['storelinkr_test_unresolvable_terms'] to simulate a
// WordPress environment where a term cannot be created/found (the root cause of empty variants).
if (!function_exists('term_exists')) {
    function term_exists($term, $taxonomy) {
        if (in_array($term, $GLOBALS['storelinkr_test_unresolvable_terms'] ?? [], true)) {
            return false;
        }

        return false;
    }
}

if (!function_exists('wp_insert_term')) {
    function wp_insert_term($term, $taxonomy) {
        if (in_array($term, $GLOBALS['storelinkr_test_unresolvable_terms'] ?? [], true)) {
            return class_exists('WP_Error') ? new WP_Error('boom', 'cannot insert') : ['term_id' => 0];
        }

        return ['term_id' => rand(1, 1000)];
    }
}

if (!function_exists('get_term_by')) {
    function get_term_by($field, $value, $taxonomy) {
        if (in_array($value, $GLOBALS['storelinkr_test_unresolvable_terms'] ?? [], true)) {
            return false;
        }

        return (object) ['slug' => sanitize_title($value), 'term_id' => rand(1, 1000)];
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

if (!class_exists('WC_Product_Variation')) {
    class WC_Product_Variation {
        private $id;
        private $parent_id;
        private $attributes = [];
        private $regular_price = '';

        public function set_parent_id($id) { $this->parent_id = $id; }
        public function set_attributes($attributes) { $this->attributes = $attributes; }
        public function get_attributes() { return $this->attributes; }
        public function save() { return true; }
        public function get_id() { return $this->id ?: rand(1000, 9999); }
        public function update_meta_data($key, $value, $unique = false) { }
        public function set_regular_price($price) { $this->regular_price = $price; }
        public function get_regular_price() {
            // Simulate WooCommerce failing to persist a price for the affected variation.
            if (!empty($GLOBALS['storelinkr_test_force_unpersisted_price'])) {
                return '';
            }

            return $this->regular_price;
        }

        // Swallow any other WooCommerce setter/getter the mapper touches during tests.
        public function __call($name, $arguments) { return null; }
    }
}

echo "Bootstrap loaded successfully\n";