<?php
# Silence is golden.

// Make sure we don't expose any info if called directly
if (!function_exists('add_action')) {
    echo 'Hi there!  I\'m just a plugin, please visit our site if you want to: storelinkr.com.';
    exit;
}