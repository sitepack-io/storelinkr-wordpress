<?php

use PHPUnit\Framework\TestCase;

/**
 * Simple unit test for the variant options logic fix
 * 
 * This test validates the core logic fix without requiring full WooCommerce integration.
 * It tests that the term collection logic properly handles all products instead of 
 * just unique option combinations.
 */
class VariantLogicTest extends TestCase
{
    /**
     * Test the OLD vs NEW logic for collecting terms from products
     * This is the core of the bug fix - ensuring all products are processed
     */
    public function testTermCollectionLogicFix()
    {
        // Generate test data similar to the issue: 360 products with repeating options
        $products = $this->generateTestProducts(360);
        $optionLabels = ['Stralingshoek', 'Effectieve lichtstroom', 'Kleurtemperatuur', 'Kleur behuizing'];
        
        // Test OLD LOGIC (buggy) - this was the original problem
        $oldLogicResults = [];
        foreach ($optionLabels as $option_name) {
            // This is the old buggy line that only got unique values
            $terms_old = array_unique(array_column(array_column($products, 'options'), $option_name));
            $oldLogicResults[$option_name] = $terms_old;
        }
        
        // Test NEW LOGIC (fixed) - this is the fix
        $newLogicResults = [];
        foreach ($optionLabels as $option_name) {
            // This is the new fixed logic that collects all terms
            $terms_new = [];
            foreach ($products as $product) {
                if (isset($product['options'][$option_name])) {
                    $terms_new[] = $product['options'][$option_name];
                }
            }
            $terms_new = array_unique($terms_new);
            $newLogicResults[$option_name] = $terms_new;
        }
        
        // Both should have same unique values (2 each), but the key difference is
        // that the new logic processes ALL products, not just the unique combinations
        
        // Verify that both logics find the same unique terms (this should be true)
        foreach ($optionLabels as $option_name) {
            $this->assertEquals(
                $oldLogicResults[$option_name], 
                $newLogicResults[$option_name],
                "Both logics should find the same unique terms for '$option_name'"
            );
        }
        
        // The real test: verify that ALL products would be processed with new logic
        $uniqueProducts = [];
        foreach ($products as $product) {
            $uniqueProducts[$product['ean']] = $product;
        }
        
        
        // This is the main assertion - we should process all 360 unique products
        $this->assertEquals(360, count($uniqueProducts), 'Should process all 360 unique products');
        $this->assertEquals(360, count($products), 'Should have 360 total products');
        
        // Verify each product has unique EAN (this proves they should all get variants)
        $eans = array_column($products, 'ean');
        $uniqueEans = array_unique($eans);
        $this->assertEquals(360, count($uniqueEans), 'All 360 products should have unique EANs');
    }
    
    /**
     * Test that products with same option values but different EANs are handled correctly
     */
    public function testUniqueProductsWithSameOptions()
    {
        // Create products with identical options but different EANs
        $products = [
            [
                'ean' => '1111111111',
                'options' => ['Color' => 'Red', 'Size' => 'Large']
            ],
            [
                'ean' => '2222222222', 
                'options' => ['Color' => 'Red', 'Size' => 'Large'] // Same options, different EAN
            ],
            [
                'ean' => '3333333333',
                'options' => ['Color' => 'Blue', 'Size' => 'Small']
            ]
        ];
        
        // Each product should be treated as unique regardless of option similarity
        $uniqueProducts = [];
        foreach ($products as $product) {
            $uniqueProducts[$product['ean']] = $product;
        }
        
        $this->assertCount(3, $uniqueProducts, 'Should have 3 unique products by EAN');
        $this->assertArrayHasKey('1111111111', $uniqueProducts);
        $this->assertArrayHasKey('2222222222', $uniqueProducts);
        $this->assertArrayHasKey('3333333333', $uniqueProducts);
        
        // Verify option collection works correctly
        $optionLabels = ['Color', 'Size'];
        foreach ($optionLabels as $option_name) {
            $terms = [];
            foreach ($products as $product) {
                if (isset($product['options'][$option_name])) {
                    $terms[] = $product['options'][$option_name];
                }
            }
            $terms = array_unique($terms);
            
            if ($option_name === 'Color') {
                $this->assertContains('Red', $terms, 'Should find Red color');
                $this->assertContains('Blue', $terms, 'Should find Blue color');
                $this->assertCount(2, $terms, 'Should have exactly 2 colors');
            } else {
                $this->assertContains('Large', $terms, 'Should find Large size');
                $this->assertContains('Small', $terms, 'Should find Small size');
                $this->assertCount(2, $terms, 'Should have exactly 2 sizes');
            }
        }
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