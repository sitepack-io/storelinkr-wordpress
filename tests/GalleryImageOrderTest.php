<?php

use Mockery as M;
use PHPUnit\Framework\TestCase;

/**
 * The image order chosen in StoreLinkr has to reach the WooCommerce gallery, also when only the
 * order changed and the set of images stayed the same.
 */
class GalleryImageOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';

        $GLOBALS['mockPosts'] = [];
        foreach ([10, 11, 12, 13] as $id) {
            $GLOBALS['mockPosts'][$id] = (object)['ID' => $id, 'post_type' => 'attachment'];
        }
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mockPosts']);
        $this->addToAssertionCount(M::getContainer()->mockery_getExpectationCount());
        M::close();
        parent::tearDown();
    }

    public function testReorderedGalleryImagesAreSaved(): void
    {
        $product = M::mock('WC_Product_Simple');
        $product->shouldReceive('get_gallery_image_ids')->andReturn([11, 12, 13]);
        $product->shouldReceive('get_image_id')->andReturn(10);
        $product->shouldReceive('set_gallery_image_ids')->once()->with([13, 11, 12]);
        $product->shouldNotReceive('set_image_id');

        (new StoreLinkrWooCommerceService())->linkProductGalleryImages($product, [10, 13, 11, 12]);
    }

    public function testUnchangedGalleryIsNotSavedAgain(): void
    {
        $product = M::mock('WC_Product_Simple');
        $product->shouldReceive('get_gallery_image_ids')->andReturn(['11', '12']);
        $product->shouldReceive('get_image_id')->andReturn('10');
        $product->shouldNotReceive('set_gallery_image_ids');
        $product->shouldNotReceive('set_image_id');

        (new StoreLinkrWooCommerceService())->linkProductGalleryImages($product, [10, 11, 12]);
    }
}
