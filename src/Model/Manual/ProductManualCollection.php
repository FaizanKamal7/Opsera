<?php

namespace App\Model\Manual;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use App\Client\ManualsApiClient;

/**
 * Represents a collection of product manuals keyed by SKU
 * 
 * This Model is used to organize and access multiple product manuals
 * fetched from an external API. It provides methods to:
 * - Add manuals to the collection
 * - Retrieve manuals by SKU
 * - Get all manuals in the collection
 */
#[ApiResource(
    operations: [
        new GetCollection(
            provider: ManualsApiClient::class
        )
    ]
)]
class ProductManualCollection
{
    /** @var ProductManual[] */
    private array $manuals = [];

    public function addManual(string $productId, ProductManual $manual): void
    {
        $this->manuals[$productId] = $manual;
    }

    public function getManual(string $productId): ?ProductManual
    {
        return $this->manuals[$productId] ?? null;
    }

    public function getManuals(): array
    {
        return $this->manuals;
    }

    public function provide(): array
    {
        return $this->manuals;
    }
}
