<?php

use PHPUnit\Framework\TestCase;

require_once STORELINKR_PLUGIN_DIR . 'helpers/class.storelinkr-image-helper.php';

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(private string $code = '', private string $message = '')
        {
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (!function_exists('is_wp_error')) {
    function is_wp_error($thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code($response)
    {
        return $response['response']['code'] ?? '';
    }
}

if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body($response)
    {
        return $response['body'] ?? '';
    }
}

/**
 * A CDN 404 used to be saved as the product image: the attachment existed but never rendered.
 */
class ImageContentTest extends TestCase
{
    private const CDN_URL = 'https://cdn.storelinkr.com/site/NO_EXPORT/image.jpg';

    public function testA404FromTheCdnIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('HTTP 404');

        StoreLinkrImageHelper::imageContentFromResponse([
            'response' => ['code' => 404],
            'body' => '{"status":404,"code":"NoSuchKey","message":"The image does not exist"}',
        ], self::CDN_URL);
    }

    public function testA200WithoutImageBytesIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('no valid image');

        StoreLinkrImageHelper::imageContentFromResponse([
            'response' => ['code' => 200],
            'body' => '<html>Access denied</html>',
        ], self::CDN_URL);
    }

    public function testATransportErrorIsRejected(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('timed out');

        StoreLinkrImageHelper::imageContentFromResponse(new WP_Error('http', 'timed out'), self::CDN_URL);
    }

    public function testImageBytesAreReturnedWithTheirRealType(): void
    {
        $png = self::png();

        self::assertSame($png, StoreLinkrImageHelper::imageContentFromResponse([
            'response' => ['code' => 200],
            'body' => $png,
        ], self::CDN_URL));
        self::assertSame(
            ['mime' => 'image/png', 'extension' => 'png'],
            StoreLinkrImageHelper::detectImageType($png)
        );
    }

    public function testABrokenAttachmentFileIsNotAValidImage(): void
    {
        $broken = tempnam(sys_get_temp_dir(), 'sl');
        file_put_contents($broken, '{"status":404}');
        $valid = tempnam(sys_get_temp_dir(), 'sl');
        file_put_contents($valid, self::png());

        try {
            self::assertFalse(StoreLinkrImageHelper::isValidImageFile($broken));
            self::assertFalse(StoreLinkrImageHelper::isValidImageFile($broken . '-missing'));
            self::assertFalse(StoreLinkrImageHelper::isValidImageFile(null));
            self::assertTrue(StoreLinkrImageHelper::isValidImageFile($valid));
        } finally {
            unlink($broken);
            unlink($valid);
        }
    }

    private static function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }
}
