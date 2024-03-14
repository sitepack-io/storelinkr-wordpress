<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

class StoreLinkrCategory implements \JsonSerializable
{
    private $source;
    private $id;
    private $categoryMain;
    private $categorySub;
    private $categorySubSub;
    private $url;
    private ?int $parentId;

    public function __construct(
        string $source,
        int $id,
        string $categoryMain,
        string $categorySub,
        string $categorySubSub,
        string $url,
        ?int $parentId = null
    ) {
        $this->source = $source;
        $this->id = $id;
        $this->categoryMain = $categoryMain;
        $this->categorySub = $categorySub;
        $this->categorySubSub = $categorySubSub;
        $this->url = $url;
        $this->parentId = $parentId;
    }

    public static function fromWooCommerce(array $categories, WP_Term $data)
    {
        $parent = null;
        $parentsParent = null;

        if (!empty($data->parent)) {
            $parent = $categories[$data->parent];
        }
        if (!empty($parent->parent)) {
            $parentsParent = $categories[$parent->parent];
        }

        if (!empty($parentsParent) && !empty($parent)) {
            return new StoreLinkrCategory(
                'WOOCOMMERCE',
                $data->term_id,
                $parentsParent->name,
                $parent->name,
                $data->name,
                get_term_link($data),
                $data->parent
            );
        }

        if (empty($parentsParent) && !empty($parent)) {
            return new StoreLinkrCategory(
                'WOOCOMMERCE',
                $data->term_id,
                $parent->name,
                $data->name,
                '',
                get_term_link($data),
                $data->parent
            );
        }

        return new StoreLinkrCategory(
            'WOOCOMMERCE',
            $data->term_id,
            $data->name,
            '',
            '',
            get_term_link($data),
            $data->parent
        );
    }

    /**
     * @return string
     */
    public function getCategoryMain(): string
    {
        return $this->categoryMain;
    }

    /**
     * @return string
     */
    public function getCategorySub(): string
    {
        return $this->categorySub;
    }

    /**
     * @return string
     */
    public function getCategorySubSub(): string
    {
        return $this->categorySubSub;
    }

    public function jsonSerialize(): array
    {
        return \get_object_vars($this);
    }
}