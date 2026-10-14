<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

class StoreLinkrImageHelper
{
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    /**
     * Returns the downloaded image bytes, or throws when the response is not a usable image. The CDN
     * answers a missing object with a 404 JSON body; saved as an attachment that showed up in
     * WooCommerce as a product image that never renders.
     *
     * @param array|WP_Error $response
     */
    public static function imageContentFromResponse($response, string $url): string
    {
        if (is_wp_error($response)) {
            throw new Exception(
                sprintf('Image download failed: %s for url %s', $response->get_error_message(), $url)
            );
        }

        $statusCode = (int)wp_remote_retrieve_response_code($response);
        if ($statusCode !== 200) {
            throw new Exception(sprintf('Image download failed: HTTP %d for url %s', $statusCode, $url));
        }

        $content = (string)wp_remote_retrieve_body($response);
        if (self::detectImageType($content) === null) {
            throw new Exception(sprintf('Image download failed: no valid image at url %s', $url));
        }

        return $content;
    }

    /**
     * @return array{mime: string, extension: string}|null
     */
    public static function detectImageType(string $content): ?array
    {
        if ($content === '') {
            return null;
        }

        $info = @getimagesizefromstring($content);
        if ($info === false || empty($info['mime']) || !isset(self::EXTENSIONS[$info['mime']])) {
            return null;
        }

        return [
            'mime' => $info['mime'],
            'extension' => self::EXTENSIONS[$info['mime']],
        ];
    }

    public static function isValidImageFile(?string $filePath): bool
    {
        if (empty($filePath) || !file_exists($filePath) || filesize($filePath) === 0) {
            return false;
        }

        return @getimagesize($filePath) !== false;
    }
}
