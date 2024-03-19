<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

require_once(STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php');

class StoreLinkrStock
{

    private StoreLinkr $storeLinkr;

    public function __construct(StoreLinkr $storeLinkr)
    {
        $this->storeLinkr = $storeLinkr;
    }


}