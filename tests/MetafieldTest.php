<?php

use PHPUnit\Framework\TestCase;

require_once STORELINKR_PLUGIN_DIR . 'helpers/class.storelinkr-metafield-helper.php';

/**
 * Covers the StoreLinkr product metafields (OE numbers, manufacturer codes, ...) that are stored
 * as WooCommerce product meta.
 */
class MetafieldTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        global $storelinkrTestOptions;
        $storelinkrTestOptions = [];
    }

    public function testMetafieldsAreStoredAsPrefixedProductMeta(): void
    {
        $product = new StoreLinkrMetafieldTestProduct();

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            [
                'name' => 'oe_numbers',
                'type' => 'datatable',
                'value' => '[{"merk":"Bosch","code":"0 986 452 041"}]',
            ],
            [
                'name' => 'stock_strategy',
                'type' => 'single_text',
                'value' => 'Dropship',
            ],
        ]);

        self::assertSame(
            '[{"merk":"Bosch","code":"0 986 452 041"}]',
            $product->get_meta('storelinkr_oe_numbers', true)
        );
        self::assertSame('Dropship', $product->get_meta('storelinkr_stock_strategy', true));
    }

    public function testMetafieldsAreRegisteredForThisShop(): void
    {
        $product = new StoreLinkrMetafieldTestProduct();

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            [
                'name' => 'manufacturer_codes',
                'type' => 'datatable',
                'value' => '[{"merk":"Febi","type":"OE","code":"12345"}]',
            ],
        ]);

        self::assertSame([
            'storelinkr_manufacturer_codes' => [
                'name' => 'manufacturer_codes',
                'type' => 'datatable',
            ],
        ], StoreLinkrMetafieldHelper::getDefinitions());
    }

    public function testGetProductMetafieldsDecodesTheDatatableValue(): void
    {
        $product = new StoreLinkrMetafieldTestProduct();

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            [
                'name' => 'oe_numbers',
                'type' => 'datatable',
                'value' => '[{"merk":"Bosch","code":"0 986 452 041"}]',
            ],
            [
                'name' => 'is_universal',
                'type' => 'boolean',
                'value' => 'true',
            ],
        ]);

        self::assertSame([
            [
                'name' => 'oe_numbers',
                'type' => 'datatable',
                'key' => 'storelinkr_oe_numbers',
                'value' => '[{"merk":"Bosch","code":"0 986 452 041"}]',
                'decoded' => [
                    ['merk' => 'Bosch', 'code' => '0 986 452 041'],
                ],
            ],
            [
                'name' => 'is_universal',
                'type' => 'boolean',
                'key' => 'storelinkr_is_universal',
                'value' => '1',
                'decoded' => null,
            ],
        ], StoreLinkrMetafieldHelper::getProductMetafields($product));
    }

    public function testRemovedMetafieldsAreDeletedOnTheNextSync(): void
    {
        $product = new StoreLinkrMetafieldTestProduct();

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            ['name' => 'oe_numbers', 'type' => 'datatable', 'value' => '[{"merk":"Bosch","code":"1"}]'],
            ['name' => 'stock_strategy', 'type' => 'single_text', 'value' => 'Dropship'],
        ]);

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            ['name' => 'oe_numbers', 'type' => 'datatable', 'value' => '[{"merk":"Bosch","code":"2"}]'],
        ]);

        self::assertSame('[{"merk":"Bosch","code":"2"}]', $product->get_meta('storelinkr_oe_numbers', true));
        self::assertNull($product->get_meta('storelinkr_stock_strategy', true));
        self::assertCount(1, StoreLinkrMetafieldHelper::getProductMetafields($product));
    }

    public function testProductMetaIsUntouchedWhenNoMetafieldsAreSent(): void
    {
        $product = new StoreLinkrMetafieldTestProduct();

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            ['name' => 'oe_numbers', 'type' => 'datatable', 'value' => '[{"merk":"Bosch","code":"1"}]'],
        ]);

        // The mapper only calls the helper when StoreLinkr sent the metafields key, so a payload
        // without metafields may never clear the meta of the previous sync.
        $data = ['name' => 'Product without metafields'];
        self::assertFalse(isset($data['metafields']));
        self::assertSame('[{"merk":"Bosch","code":"1"}]', $product->get_meta('storelinkr_oe_numbers', true));
    }

    /**
     * Only the types with their own handling change the stored value: a boolean becomes 1 or 0 and
     * a datatable or JSON value is decoded when it is read back. All other types are stored as the
     * text StoreLinkr sent.
     *
     * @dataProvider metafieldTypeProvider
     */
    public function testEveryMetafieldTypeIsStoredAndReadBack(
        string $type,
        $value,
        string $expectedStoredValue,
        ?array $expectedDecoded
    ): void {
        $product = new StoreLinkrMetafieldTestProduct();

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            ['name' => 'test_field', 'type' => $type, 'value' => $value],
        ]);

        self::assertSame($expectedStoredValue, $product->get_meta('storelinkr_test_field', true));
        self::assertSame([
            [
                'name' => 'test_field',
                'type' => $type,
                'key' => 'storelinkr_test_field',
                'value' => $expectedStoredValue,
                'decoded' => $expectedDecoded,
            ],
        ], StoreLinkrMetafieldHelper::getProductMetafields($product));
    }

    public function metafieldTypeProvider(): array
    {
        return [
            'datatable' => [
                'datatable',
                '[{"merk":"Bosch","code":"0 986 452 041"}]',
                '[{"merk":"Bosch","code":"0 986 452 041"}]',
                [['merk' => 'Bosch', 'code' => '0 986 452 041']],
            ],
            'json' => [
                'json',
                '{"overlay_label":"Nieuw"}',
                '{"overlay_label":"Nieuw"}',
                ['overlay_label' => 'Nieuw'],
            ],
            'broken json stays text' => ['json', '{not json', '{not json', null],
            'single_text' => ['single_text', 'Dropship', 'Dropship', null],
            'multiple_text' => ['multiple_text', "Regel een\nRegel twee", "Regel een\nRegel twee", null],
            'html_text' => [
                'html_text',
                '<p>Draai aan met <strong>45 Nm</strong></p>',
                '<p>Draai aan met <strong>45 Nm</strong></p>',
                null,
            ],
            'boolean true' => ['boolean', 'true', '1', null],
            'boolean one' => ['boolean', '1', '1', null],
            'boolean false' => ['boolean', 'false', '0', null],
            'boolean zero' => ['boolean', '0', '0', null],
            'number' => ['number', '45.5', '45.5', null],
            'date' => ['date', '2026-09-01', '2026-09-01', null],
            'color' => ['color', '#1e3346', '#1e3346', null],
            'email' => ['email', 'support@example.com', 'support@example.com', null],
            'url' => ['url', 'https://example.com/datasheet.pdf', 'https://example.com/datasheet.pdf', null],
        ];
    }

    public function testALongMetafieldNameFitsInTheWordPressMetaKeyColumn(): void
    {
        $product = new StoreLinkrMetafieldTestProduct();
        $name = str_repeat('oe_number_', 30);

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            ['name' => $name, 'type' => 'single_text', 'value' => 'A very long metafield name'],
        ]);

        $metafields = StoreLinkrMetafieldHelper::getProductMetafields($product);

        self::assertCount(1, $metafields);
        self::assertSame(
            StoreLinkrMetafieldHelper::MAX_META_KEY_LENGTH,
            strlen($metafields[0]['key'])
        );
        self::assertSame($name, $metafields[0]['name']);
        self::assertSame('A very long metafield name', $metafields[0]['value']);
    }

    public function testTwoLongMetafieldNamesDoNotShareOneMetaKey(): void
    {
        $product = new StoreLinkrMetafieldTestProduct();
        $prefix = str_repeat('oe_number_', 30);

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            ['name' => $prefix . 'first', 'type' => 'single_text', 'value' => 'First'],
            ['name' => $prefix . 'second', 'type' => 'single_text', 'value' => 'Second'],
        ]);

        $metafields = StoreLinkrMetafieldHelper::getProductMetafields($product);

        self::assertCount(2, $metafields);
        self::assertNotSame($metafields[0]['key'], $metafields[1]['key']);
        self::assertSame('First', $metafields[0]['value']);
        self::assertSame('Second', $metafields[1]['value']);
    }

    public function testMetafieldsWithoutAUsableNameAreSkipped(): void
    {
        $product = new StoreLinkrMetafieldTestProduct();

        StoreLinkrMetafieldHelper::applyToProduct($product, [
            ['name' => '///', 'type' => 'single_text', 'value' => 'Skipped'],
            ['name' => 'variant_ids', 'type' => 'single_text', 'value' => 'Reserved meta key'],
            ['name' => 'empty_value', 'type' => 'single_text', 'value' => null],
        ]);

        self::assertSame([], StoreLinkrMetafieldHelper::getProductMetafields($product));
        self::assertNull($product->get_meta('storelinkr_variant_ids', true));
    }
}

/**
 * Minimal WC_Data stand-in that stores the meta in memory.
 */
class StoreLinkrMetafieldTestProduct
{
    private array $meta = [];

    public function update_meta_data($key, $value, $meta_id = 0)
    {
        $this->meta[$key] = $value;
    }

    public function delete_meta_data($key)
    {
        unset($this->meta[$key]);
    }

    public function get_meta($key, $single = false)
    {
        return $this->meta[$key] ?? null;
    }
}
