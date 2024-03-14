<?php

if (!defined('ABSPATH')) {
    // Exit if accessed directly
    exit;
}

class StoreLinkrStockLocation implements \JsonSerializable
{
    private int $stockQuantity;
    private int $stockQuantityDemo;
    private int $stockQuantityExpected;
    private string $locationName;
    private string $locationId;

    /**
     * @param int $stockQuantity
     * @param int $stockQuantityDemo
     * @param int $stockQuantityExpected
     * @param string $locationName
     * @param string $locationId
     */
    private function __construct(
        int $stockQuantity,
        int $stockQuantityDemo,
        int $stockQuantityExpected,
        string $locationName,
        string $locationId
    ) {
        $this->stockQuantity = $stockQuantity;
        $this->stockQuantityDemo = $stockQuantityDemo;
        $this->stockQuantityExpected = $stockQuantityExpected;
        $this->locationName = $locationName;
        $this->locationId = $locationId;
    }

    /**
     * Create a new Stock object from the storelinkr API endpoint
     *
     * @param array $apiData
     * @return StoreLinkrStockLocation
     * @throws Exception
     */
    public static function fromStoreLinkrData(array $apiData): StoreLinkrStockLocation
    {
        return new StoreLinkrStockLocation(
            (int)$apiData['quantity'],
            (int)$apiData['quantityDemo'],
            (int)$apiData['quantityExpected'],
            (string)$apiData['name'],
            (string)$apiData['locationId']
        );
    }

    /**
     * @return int
     */
    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    /**
     * @param int $stockQuantity
     */
    public function setStockQuantity(int $stockQuantity): void
    {
        $this->stockQuantity = $stockQuantity;
    }

    /**
     * @return int
     */
    public function getStockQuantityDemo(): int
    {
        return $this->stockQuantityDemo;
    }

    /**
     * @param int $stockQuantityDemo
     */
    public function setStockQuantityDemo(int $stockQuantityDemo): void
    {
        $this->stockQuantityDemo = $stockQuantityDemo;
    }

    /**
     * @return int
     */
    public function getStockQuantityExpected(): int
    {
        return $this->stockQuantityExpected;
    }

    /**
     * @param int $stockQuantityExpected
     */
    public function setStockQuantityExpected(int $stockQuantityExpected): void
    {
        $this->stockQuantityExpected = $stockQuantityExpected;
    }

    /**
     * @return string
     */
    public function getLocationName(): string
    {
        return $this->locationName;
    }

    /**
     * @param string $locationName
     */
    public function setLocationName(string $locationName): void
    {
        $this->locationName = $locationName;
    }

    /**
     * @return string
     */
    public function getLocationId(): string
    {
        return $this->locationId;
    }

    /**
     * @param string $locationId
     */
    public function setLocationId(string $locationId): void
    {
        $this->locationId = $locationId;
    }

    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}