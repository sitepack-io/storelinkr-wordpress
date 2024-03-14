<?php

include STORELINKR_PLUGIN_DIR . 'models/class.storelinkr-stock.php';
include STORELINKR_PLUGIN_DIR . 'models/class.storelinkr-stock-location.php';

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

class StoreLinkr
{

    private static ?StoreLinkr $instance = null;

    public static function getInstance(): StoreLinkr
    {
        if (!isset(self::$instance)) {
            self::$instance = new StoreLinkr();
            self::$instance->init();
        }

        return self::$instance;
    }

    public static function init()
    {
    }

    public function fetchLiveStock(string $siteUuid, string $importSource, string $ean): StoreLinkrStock
    {
        $storelinkrApiHost = apply_filters('storelinkr_api_hostname', 'https://api.storelinkr.com');
        $url = $storelinkrApiHost . '/api/public/products/%s/%s/%s/connect/stock';
        $url = sprintf(
            $url,
            $siteUuid,
            $importSource,
            $ean
        );

        $response = wp_remote_post($url);

        if (is_wp_error($response)) {
            $errorMessage = $response->get_error_message();

            return new StoreLinkrStock(
                false,
                0,
                0,
                0,
                false,
                [],
                null,
                $errorMessage
            );
        }


        if (empty($response['body'])) {
            return new StoreLinkrStock(
                false,
                0,
                0,
                0,
                false,
                [],
                null,
                'Empty body response'
            );
        }

        $data = json_decode($response['body'], true);

        $locations = [];
        if (is_array($data['stock']['locations'])) {
            foreach ($data['stock']['locations'] as $dataLocation) {
                $locations[] = StoreLinkrStockLocation::fromStoreLinkrData($dataLocation);
            }
        }

        return new StoreLinkrStock(
            $data['stock']['inStock'],
            $data['stock']['quantityAvailable'],
            $data['stock']['quantitySupplier'],
            0, // TODO in SP API
            $data['stock']['allowBackorder'],
            $locations,
            ($data['stock']['deliveryDate'] !== null) ? new DateTimeImmutable($data['stock']['deliveryDate']) : null,
            $data['stock']['errorReason'],
        );
    }

}