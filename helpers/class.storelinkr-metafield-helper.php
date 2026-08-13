<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

/**
 * Stores the StoreLinkr product metafields (OE numbers, manufacturer codes, marketing content, ...)
 * as WordPress post meta on the WooCommerce product.
 */
class StoreLinkrMetafieldHelper
{

    public const META_KEY_PREFIX = 'storelinkr_';

    /**
     * Holds the definitions (name, type and meta key) of the metafields currently known for a
     * product. Needed to clean up meta of metafields that were removed inside StoreLinkr.
     */
    public const INDEX_META_KEY = '_storelinkr_metafields';

    /**
     * All metafield definitions seen on this shop, used to register the meta keys in WordPress.
     */
    public const REGISTRY_OPTION = 'storelinkr_metafield_definitions';

    /**
     * These types hold a JSON encoded value, the raw JSON is stored as post meta.
     */
    public const JSON_TYPES = [
        'json',
        'datatable',
    ];

    public const DEFAULT_TYPE = 'single_text';

    /**
     * The meta_key column of WordPress is a varchar(255), a longer key is refused or truncated by
     * the database. Note that the index on the column only covers the first 191 characters.
     */
    public const MAX_META_KEY_LENGTH = 255;

    private const KEY_HASH_LENGTH = 8;

    /**
     * Meta keys used by the plugin itself, a metafield may never overwrite one of these.
     */
    private const RESERVED_META_KEYS = [
        'storelinkr_variant_ids',
    ];

    /**
     * Write all metafields of a product, and remove the ones that no longer exist in StoreLinkr.
     *
     * @param WC_Product|WC_Product_Variable|WC_Product_Variation $product
     * @param array $metafields
     * @return void
     */
    public static function applyToProduct($product, array $metafields): void
    {
        $definitions = [];
        $metaKeys = [];

        foreach ($metafields as $metafield) {
            $metafield = (array)$metafield;

            if (empty($metafield['name'])) {
                continue;
            }

            $metaKey = self::buildMetaKey((string)$metafield['name']);
            if ($metaKey === null) {
                continue;
            }

            $type = (!empty($metafield['type'])) ? (string)$metafield['type'] : self::DEFAULT_TYPE;
            $value = self::formatValue($type, $metafield['value'] ?? null);

            if ($value === null) {
                continue;
            }

            $product->update_meta_data($metaKey, $value);

            $definitions[] = [
                'name' => (string)$metafield['name'],
                'type' => $type,
                'key' => $metaKey,
            ];
            $metaKeys[] = $metaKey;
        }

        foreach (self::readIndex($product) as $previousDefinition) {
            if (empty($previousDefinition['key'])) {
                continue;
            }

            if (in_array($previousDefinition['key'], $metaKeys, true)) {
                continue;
            }

            $product->delete_meta_data($previousDefinition['key']);
        }

        $product->update_meta_data(self::INDEX_META_KEY, json_encode($definitions));

        self::rememberDefinitions($definitions);
    }

    /**
     * All metafields of a product, including the decoded value for the JSON based types.
     *
     * @param WC_Product|WC_Product_Variable|WC_Product_Variation $product
     * @return array
     */
    public static function getProductMetafields($product): array
    {
        $metafields = [];

        foreach (self::readIndex($product) as $definition) {
            if (empty($definition['key']) || empty($definition['name'])) {
                continue;
            }

            $type = (!empty($definition['type'])) ? (string)$definition['type'] : self::DEFAULT_TYPE;
            $value = $product->get_meta($definition['key'], true);

            if ($value === '' || $value === null) {
                continue;
            }

            $metafields[] = [
                'name' => (string)$definition['name'],
                'type' => $type,
                'key' => (string)$definition['key'],
                'value' => $value,
                'decoded' => self::decodeValue($type, $value),
            ];
        }

        return $metafields;
    }

    /**
     * Register all known metafields as post meta, so WordPress knows the meta keys of this shop and
     * everything that expects registered meta can work with them.
     *
     * WooCommerce products are readable through the WordPress REST API, and metafields can hold
     * internal data like purchase prices, so they stay out of the REST API unless a shop opts in
     * with the storelinkr_metafield_show_in_rest filter.
     */
    public static function registerPostMeta(): void
    {
        foreach (self::getDefinitions() as $metaKey => $definition) {
            register_post_meta('product', $metaKey, [
                'type' => 'string',
                'description' => (!empty($definition['name'])) ? (string)$definition['name'] : $metaKey,
                'single' => true,
                'show_in_rest' => (bool)apply_filters(
                    'storelinkr_metafield_show_in_rest',
                    false,
                    $metaKey,
                    $definition
                ),
                'auth_callback' => function () {
                    return current_user_can('manage_woocommerce');
                },
            ]);
        }
    }

    /**
     * @return array meta key => ['name' => string, 'type' => string]
     */
    public static function getDefinitions(): array
    {
        $definitions = get_option(self::REGISTRY_OPTION, []);

        if (!is_array($definitions)) {
            return [];
        }

        return $definitions;
    }

    public static function buildMetaKey(string $name): ?string
    {
        $name = sanitize_key($name);

        if (empty($name)) {
            return null;
        }

        $metaKey = self::META_KEY_PREFIX . $name;

        if (strlen($metaKey) > self::MAX_META_KEY_LENGTH) {
            // A StoreLinkr metafield name may be longer than the meta_key column of WordPress, so
            // the key is cut off. Two long names can share their first characters, the hash of the
            // full name keeps the shortened keys apart.
            $metaKey = substr($metaKey, 0, self::MAX_META_KEY_LENGTH - (self::KEY_HASH_LENGTH + 1))
                . '_' . substr(md5($name), 0, self::KEY_HASH_LENGTH);
        }

        if (in_array($metaKey, self::RESERVED_META_KEYS, true)) {
            return null;
        }

        return $metaKey;
    }

    /**
     * StoreLinkr stores every metafield value as text, the JSON based types hold a JSON string.
     *
     * @param string $type
     * @param mixed $value
     * @return string|null
     */
    public static function formatValue(string $type, $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($type === 'boolean') {
            if (is_bool($value)) {
                return ($value === true) ? '1' : '0';
            }

            return in_array(strtolower(trim((string)$value)), ['1', 'true', 'yes', 'on'], true) ? '1' : '0';
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return (string)$value;
    }

    /**
     * @param string $type
     * @param string $value
     * @return array|null the decoded value for the JSON based types, null for all other types
     */
    public static function decodeValue(string $type, string $value): ?array
    {
        if (!in_array($type, self::JSON_TYPES, true)) {
            return null;
        }

        $decoded = json_decode($value, true);

        return (is_array($decoded)) ? $decoded : null;
    }

    /**
     * @param WC_Product|WC_Product_Variable|WC_Product_Variation $product
     * @return array
     */
    private static function readIndex($product): array
    {
        $index = $product->get_meta(self::INDEX_META_KEY, true);

        if (is_string($index)) {
            $index = json_decode($index, true);
        }

        return (is_array($index)) ? $index : [];
    }

    private static function rememberDefinitions(array $definitions): void
    {
        $registry = self::getDefinitions();
        $changed = false;

        foreach ($definitions as $definition) {
            $metaKey = $definition['key'];

            if (
                isset($registry[$metaKey])
                && $registry[$metaKey]['name'] === $definition['name']
                && $registry[$metaKey]['type'] === $definition['type']
            ) {
                continue;
            }

            $registry[$metaKey] = [
                'name' => $definition['name'],
                'type' => $definition['type'],
            ];
            $changed = true;
        }

        if ($changed === false) {
            return;
        }

        update_option(self::REGISTRY_OPTION, $registry, false);
    }

}
