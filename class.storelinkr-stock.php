<?php

require_once(SITEPACK_CONNECT_PLUGIN_DIR . 'services/class.sitepack-woocommerce.php');

class StoreLinkrStock
{

    private StoreLinkr $storeLinkr;

    public function __construct(StoreLinkr $storeLinkr)
    {
        $this->storeLinkr = $storeLinkr;
    }


}