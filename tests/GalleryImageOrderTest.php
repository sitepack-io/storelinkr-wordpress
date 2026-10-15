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

    public function testVariationWithoutOwnImageGetsItsImageWrittenWhenItEqualsTheParentImage(): void
    {
        // In 'view' context the variation would report the parent's image 10, in 'edit' context it has none.
        $variation = M::mock('WC_Product_Variation');
        $variation->shouldReceive('get_gallery_image_ids')->with('edit')->andReturn([]);
        $variation->shouldReceive('get_image_id')->with('edit')->andReturn(0);
        $variation->shouldReceive('get_image_id')->with('view')->andReturn(10);
        $variation->shouldReceive('set_gallery_image_ids')->once()->with([11]);
        $variation->shouldReceive('set_image_id')->once()->with(10);

        $service = new StoreLinkrWooCommerceService();
        $service->linkProductGalleryImages($variation, [10, 11]);

        $this->assertSame([], $service->getWarnings());
    }

    public function testMissingFeaturedImageIsReportedAndNotLinked(): void
    {
        unset($GLOBALS['mockPosts'][10]);

        $variation = M::mock('WC_Product_Variation');
        $variation->shouldReceive('get_gallery_image_ids')->with('edit')->andReturn([]);
        $variation->shouldReceive('get_image_id')->with('edit')->andReturn(0);
        $variation->shouldReceive('set_gallery_image_ids')->once()->with([11]);
        // The second image (a dimension drawing) must not become the main image either.
        $variation->shouldNotReceive('set_image_id');

        $service = new StoreLinkrWooCommerceService();
        $service->linkProductGalleryImages($variation, [10, 11]);

        $this->assertSame(['Image 10 does not exist anymore!'], $service->getWarnings());
    }

    public function testMissingFeaturedImageKeepsTheCurrentOwnImage(): void
    {
        unset($GLOBALS['mockPosts'][10]);

        $product = M::mock('WC_Product_Simple');
        $product->shouldReceive('get_gallery_image_ids')->with('edit')->andReturn([11]);
        $product->shouldReceive('get_image_id')->with('edit')->andReturn(12);
        $product->shouldNotReceive('set_gallery_image_ids');
        $product->shouldNotReceive('set_image_id');

        $service = new StoreLinkrWooCommerceService();
        $service->linkProductGalleryImages($product, [10, 11]);

        $this->assertSame(['Image 10 does not exist anymore!'], $service->getWarnings());
    }

    public function testMissingFeaturedImageClearsACurrentImageThatIsGoneAsWell(): void
    {
        unset($GLOBALS['mockPosts'][10], $GLOBALS['mockPosts'][12]);

        $product = M::mock('WC_Product_Simple');
        $product->shouldReceive('get_gallery_image_ids')->with('edit')->andReturn([11]);
        $product->shouldReceive('get_image_id')->with('edit')->andReturn(12);
        $product->shouldNotReceive('set_gallery_image_ids');
        $product->shouldReceive('set_image_id')->once()->with('');

        (new StoreLinkrWooCommerceService())->linkProductGalleryImages($product, [10, 11]);
    }

    public function testKeptCurrentImageIsDroppedFromTheGallery(): void
    {
        unset($GLOBALS['mockPosts'][10]);

        $product = M::mock('WC_Product_Simple');
        $product->shouldReceive('get_gallery_image_ids')->with('edit')->andReturn([11, 12]);
        $product->shouldReceive('get_image_id')->with('edit')->andReturn('11');
        $product->shouldReceive('set_gallery_image_ids')->once()->with([12]);
        $product->shouldNotReceive('set_image_id');

        $service = new StoreLinkrWooCommerceService();
        $service->linkProductGalleryImages($product, [10, 11, 12]);

        $this->assertSame(['Image 10 does not exist anymore!'], $service->getWarnings());
    }
}
