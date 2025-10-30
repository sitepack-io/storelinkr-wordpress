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

if (!class_exists('WC_Product_Variation')) {
    class WC_Product_Variation {
        private $id;
        private $parent_id;
        private $attributes = [];
        
        public function set_parent_id($id) { $this->parent_id = $id; }
        public function set_attributes($attributes) { $this->attributes = $attributes; }
        public function save() { return true; }
        public function get_id() { return $this->id ?: rand(1000, 9999); }
        public function update_meta_data($key, $value, $unique = false) { }
    }
}

echo "Bootstrap loaded successfully\n";