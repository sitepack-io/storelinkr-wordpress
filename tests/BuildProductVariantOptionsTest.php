<?php

use PHPUnit\Framework\TestCase;
use Mockery as M;

/**
 * Test class for StoreLinkrWooCommerceService::buildProductVariantOptions()
 * 
 * This test covers the fix for the issue where 360 products with 4 options each
 * were only creating 8 variants instead of 360 unique variants.
 */
class BuildProductVariantOptionsTest extends TestCase
{
    private $mockWooCommerceService;
    private $mockVariableProduct;
    
    protected function setUp(): void
    {
        parent::setUp();
        
        // Mock the variable product
        $this->mockVariableProduct = M::mock('WC_Product_Variable');
        $this->mockVariableProduct->shouldReceive('get_attributes')->andReturn([]);
        $this->mockVariableProduct->shouldReceive('set_attributes')->andReturnSelf();
        $this->mockVariableProduct->shouldReceive('set_manage_stock')->andReturnSelf();
        $this->mockVariableProduct->shouldReceive('set_stock_quantity')->andReturnSelf();
        $this->mockVariableProduct->shouldReceive('set_stock_status')->andReturnSelf();
        $this->mockVariableProduct->shouldReceive('save')->andReturn(true);
        
        // Set global mock for wc_get_product function
        global $mockVariableProduct;
        $mockVariableProduct = $this->mockVariableProduct;
        
        // Mock wc_get_product function
        if (!function_exists('wc_get_product')) {
            function wc_get_product($id) {
                global $mockVariableProduct;
                return $mockVariableProduct;
            }
        }
        
        // Mock wc_get_attribute_taxonomies
        if (!function_exists('wc_get_attribute_taxonomies')) {
            function wc_get_attribute_taxonomies() {
                return [
                    (object) ['attribute_name' => 'stralingshoek', 'attribute_id' => 1],
                    (object) ['attribute_name' => 'effectieve_lichtstroom', 'attribute_id' => 2],
                    (object) ['attribute_name' => 'kleurtemperatuur', 'attribute_id' => 3],
                    (object) ['attribute_name' => 'kleur_behuizing', 'attribute_id' => 4],
                ];
            }
        }
        
        // Mock wc_delete_product_transients
        if (!function_exists('wc_delete_product_transients')) {
            function wc_delete_product_transients($id) {
                return true;
            }
        }

        // Polyfill is_wp_error for test context
        if (!function_exists('is_wp_error')) {
            function is_wp_error($thing) {
                return class_exists('WP_Error') ? ($thing instanceof WP_Error) : false;
            }
        }

        // Polyfill clean_term_cache for test context (no-op)
        if (!function_exists('clean_term_cache')) {
            function clean_term_cache($ids, $taxonomy = '', $clean_taxonomy = true) {
                return true;
            }
        }
    }
    
    protected function tearDown(): void
    {
        M::close();
        parent::tearDown();
    }
    
    /**
     * Test that buildProductVariantOptions creates correct number of variants
     * for the scenario described in the issue: 360 products should create 360 variants
     */
    public function testBuildProductVariantOptionsCreates360Variants()
    {
        // Load the StoreLinkr WooCommerce service
        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';
        
        // Create a partial mock that only mocks the public methods we need
        $service = M::mock('StoreLinkrWooCommerceService[logWarning]');
        $service->shouldReceive('logWarning')->andReturn(null);
        
        // Generate test data similar to the issue description
        $products = $this->generateTestProducts(360);
        $optionLabels = ['Stralingshoek', 'Effectieve lichtstroom', 'Kleurtemperatuur', 'Kleur behuizing'];
        $settings = ['overwrite_images' => true];
        
        // Mock WC_Product_Variation creation
        $mockVariations = [];
        for ($i = 0; $i < 360; $i++) {
            $mockVariation = M::mock('WC_Product_Variation');
            $mockVariation->shouldReceive('set_parent_id')->andReturnSelf();
            $mockVariation->shouldReceive('set_attributes')->andReturnSelf();
            $mockVariation->shouldReceive('save')->andReturn(true);
            $mockVariation->shouldReceive('get_id')->andReturn(1000 + $i);
            $mockVariations[] = $mockVariation;
        }
        
        // Override WC_Product_Variation constructor for testing
        global $mockVariationIndex;
        $mockVariationIndex = 0;
        
        // Call the method under test
        $result = $service->buildProductVariantOptions(1, $optionLabels, $products, $settings);
        
        // Assertions
        $this->assertIsArray($result, 'Result should be an array');
        $this->assertCount(360, $result, 'Should create exactly 360 variants');
        
        // Verify that each product EAN is mapped to a variation ID
        $expectedEans = [];
        for ($i = 1; $i <= 360; $i++) {
            $expectedEans[] = '213213213' . str_pad($i, 4, '0', STR_PAD_LEFT);
        }
        
        $resultEans = array_keys($result);
        $this->assertEquals($expectedEans, $resultEans, 'All product EANs should be present in result');
    }
    
    /**
     * Test that the fix correctly handles unique option values
     * The bug was that it only created variants for unique option combinations
     */
    public function testCorrectlyHandlesUniqueOptionValues()
    {
        require_once STORELINKR_PLUGIN_DIR . 'services/class.storelinkr-woocommerce.php';
        
        // Create test data with repeating option values but unique EANs
        $products = [
            [
                'ean' => '1111111111',
                'id' => null,
                'options' => [
                    'Color' => 'Red',
                    'Size' => 'Large'
                ],
                'inStock' => 5,
                'stockSupplier' => 2
            ],
            [
                'ean' => '2222222222',
                'id' => null,
                'options' => [
                    'Color' => 'Red',    // Same as first product
                    'Size' => 'Large'    // Same as first product
                ],
                'inStock' => 3,
                'stockSupplier' => 1
            ],
            [
                'ean' => '3333333333',
                'id' => null,
                'options' => [
                    'Color' => 'Blue',
                    'Size' => 'Small'
                ],
                'inStock' => 8,
                'stockSupplier' => 0
            ]
        ];
        
        $service = M::mock('StoreLinkrWooCommerceService[logWarning]');
        $service->shouldReceive('logWarning')->andReturn(null);
        
        $optionLabels = ['Color', 'Size'];
        $settings = [];
        
        $result = $service->buildProductVariantOptions(1, $optionLabels, $products, $settings);
        
        // Should create 3 variants, not just 2 (for unique combinations)
        $this->assertCount(3, $result, 'Should create 3 variants for 3 products with unique EANs');
        $this->assertArrayHasKey('1111111111', $result);
        $this->assertArrayHasKey('2222222222', $result);
        $this->assertArrayHasKey('3333333333', $result);
    }
    
    /**
     * Generate test products similar to the issue description
     */
    private function generateTestProducts(int $count): array
    {
        $products = [];
        
        $stralingshoekOptions = ['15 Graden', '30 Graden'];
        $lichtstroomOptions = ['2675 Lumen', '3200 Lumen'];
        $kleurtemperatuurOptions = ['2700 - 2700 Kelvin', '3000 - 3000 Kelvin'];
        $kleurBehuzingOptions = ['Grijs', 'Zwart'];
        
        for ($i = 1; $i <= $count; $i++) {
            $products[] = [
                'ean' => '213213213' . str_pad($i, 4, '0', STR_PAD_LEFT),
                'id' => null, // New variation
                'sku' => 'TEST-SKU-' . $i,
                'name' => 'Test Product ' . $i,
                'price' => 100 + $i,
                'inStock' => rand(0, 10),
                'stockSupplier' => rand(0, 5),
                'options' => [
                    'Stralingshoek' => $stralingshoekOptions[($i - 1) % 2],
                    'Effectieve lichtstroom' => $lichtstroomOptions[($i - 1) % 2],
                    'Kleurtemperatuur' => $kleurtemperatuurOptions[($i - 1) % 2],
                    'Kleur behuizing' => $kleurBehuzingOptions[($i - 1) % 2]
                ]
            ];
        }
        
        return $products;
    }
}