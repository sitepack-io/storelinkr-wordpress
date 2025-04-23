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
    private $categorySubSubSub;
    private $categorySubSubSubSub;
    private $url;
    private ?int $parentId;

    public function __construct(
        string $source,
        int $id,
        string $categoryMain,
        string $categorySub,
        string $categorySubSub,
        string $categorySubSubSub,
        string $categorySubSubSubSub,
        string $url,
        ?int $parentId = null
    ) {
        $this->source = $source;
        $this->id = $id;
        $this->categoryMain = $categoryMain;
        $this->categorySub = $categorySub;
        $this->categorySubSub = $categorySubSub;
        $this->categorySubSubSub = $categorySubSubSub;
        $this->categorySubSubSubSub = $categorySubSubSubSub;
        $this->url = $url;
        $this->parentId = $parentId;
    }

    public static function fromWooCommerce(array $categories, WP_Term $data)
    {
        $parent = null;
        $grandParent = null;
        $greatGrandParent = null;
        $greatGreatGrandParent = null;

        if (!empty($data->parent) && isset($categories[$data->parent])) {
            $parent = $categories[$data->parent];
        }
        if (!empty($parent->parent) && isset($categories[$parent->parent])) {
            $grandParent = $categories[$parent->parent];
        }
        if (!empty($grandParent->parent) && isset($categories[$grandParent->parent])) {
            $greatGrandParent = $categories[$grandParent->parent];
        }
        if (!empty($greatGrandParent->parent) && isset($categories[$greatGrandParent->parent])) {
            $greatGreatGrandParent = $categories[$greatGrandParent->parent];
        }

        if (!empty($greatGreatGrandParent) && !empty($greatGrandParent) && !empty($grandParent) && !empty($parent)) {
            return new StoreLinkrCategory(
                'WOOCOMMERCE',
                $data->term_id,
                $greatGreatGrandParent->name,
                $greatGrandParent->name,
                $grandParent->name,
                $parent->name,
                $data->name,
                get_term_link($data),
                $data->parent
            );
        }

        if (empty($greatGreatGrandParent) && !empty($greatGrandParent) && !empty($grandParent) && !empty($parent)) {
            return new StoreLinkrCategory(
                'WOOCOMMERCE',
                $data->term_id,
                $greatGrandParent->name,
                $grandParent->name,
                $parent->name,
                $data->name,
                '',
                get_term_link($data),
                $data->parent
            );
        }

        if (empty($greatGrandParent) && !empty($grandParent) && !empty($parent)) {
            return new StoreLinkrCategory(
                'WOOCOMMERCE',
                $data->term_id,
                $grandParent->name,
                $parent->name,
                $data->name,
                '',
                '',
                get_term_link($data),
                $data->parent
            );
        }

        if (empty($grandParent) && !empty($parent)) {
            return new StoreLinkrCategory(
                'WOOCOMMERCE',
                $data->term_id,
                $parent->name,
                $data->name,
                '',
                '',
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

    public function getCategorySubSubSub(): string
    {
        return $this->categorySubSubSub;
    }

    public function getCategorySubSubSubSub(): string
    {
        return $this->categorySubSubSubSub;
    }

    public function jsonSerialize(): array
    {
        return \get_object_vars($this);
    }
}
