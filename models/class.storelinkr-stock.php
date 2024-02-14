<?php

class StoreLinkrStock implements \JsonSerializable
{
    private bool $hasStock = false;
    private int $stockQuantity = 0;
    private int $stockQuantitySupplier = 0;
    private int $stockQuantityReserved = 0;
    private bool $allowBackorder = false;
    private array $stockLocations = [];
    private ?\DateTimeImmutable $deliveryDate;
    private ?string $errorReason = null;

    /**
     * @param bool $hasStock
     * @param int $stockQuantity
     * @param int $stockQuantitySupplier
     * @param int $stockQuantityReserved
     * @param bool $allowBackorder
     * @param array $stockLocations
     * @param DateTimeImmutable|null $deliveryDate
     * @param string|null $errorReason
     */
    public function __construct(
        bool $hasStock,
        int $stockQuantity,
        int $stockQuantitySupplier,
        int $stockQuantityReserved,
        bool $allowBackorder,
        array $stockLocations,
        ?DateTimeImmutable $deliveryDate = null,
        ?string $errorReason = null
    ) {
        $this->hasStock = $hasStock;
        $this->stockQuantity = $stockQuantity;
        $this->stockQuantitySupplier = $stockQuantitySupplier;
        $this->stockQuantityReserved = $stockQuantityReserved;
        $this->allowBackorder = $allowBackorder;
        $this->stockLocations = $stockLocations;
        $this->deliveryDate = $deliveryDate;
        $this->errorReason = $errorReason;
    }

    /**
     * Create a new Stock object from the storelinkr API endpoint
     *
     * @param array $apiData
     * @return StoreLinkrStock
     * @throws Exception
     */
    public static function fromSitePackConnectData(array $apiData): StoreLinkrStock
    {
        $locations = [];

        if (is_array($apiData['stock_locations'])) {
            foreach ($apiData['stock_locations'] as $stockLocation) {
                $locations[] = StoreLinkrStockLocation::fromStoreLinkrData($stockLocation);
            }
        }

        return new StoreLinkrStock(
            (bool)$apiData['inStock'],
            (int)$apiData['quantityAvailable'],
            (int)$apiData['quantitySupplier'],
            0, // TODO: implement in storelinkr API
            (bool)$apiData['allowBackorder'],
            $locations,
            (!empty($apiData['deliveryDate'])) ? new DateTimeImmutable($apiData['deliveryDate']) : null,
            $apiData['errorReason']
        );
    }

    /**
     * @return bool
     */
    public function isHasStock(): bool
    {
        return $this->hasStock;
    }

    /**
     * @return int
     */
    public function getStockQuantity(): int
    {
        return $this->stockQuantity;
    }

    /**
     * @return int
     */
    public function getStockQuantitySupplier(): int
    {
        return $this->stockQuantitySupplier;
    }

    /**
     * @return int
     */
    public function getStockQuantityReserved(): int
    {
        return $this->stockQuantityReserved;
    }

    /**
     * @return bool
     */
    public function isAllowBackorder(): bool
    {
        return $this->allowBackorder;
    }

    /**
     * @return StoreLinkrStockLocation[]
     */
    public function getStockLocations(): array
    {
        return $this->stockLocations;
    }

    /**
     * @return DateTimeImmutable|null
     */
    public function getDeliveryDate(): ?DateTimeImmutable
    {
        return $this->deliveryDate;
    }

    /**
     * @return string|null
     */
    public function getErrorReason(): ?string
    {
        return $this->errorReason;
    }

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return get_object_vars($this);
    }
}