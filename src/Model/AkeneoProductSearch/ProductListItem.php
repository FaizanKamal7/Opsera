<?php

namespace App\Model\AkeneoProductSearch;

use App\Model\AkeneoProductSearch\ProductListItemContent\ProductName;
use Symfony\Component\Serializer\Annotation\SerializedName;

class ProductListItem
{
    public string $identifier;

    /** @var ProductName[] */
    public array $name = [];

    #[SerializedName('EAN')]
    public string $ean;

    // Getters and setters
    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function getName(): array
    {
        return $this->name;
    }
    public function setName(array $name): void
    {
        $this->name = $name;
    }

    public function getEan(): string
    {
        return $this->ean;
    }
    public function setEan(string $ean): void
    {
        $this->ean = $ean;
    }

    /**
     * Gets the for desired scope and language
     */
    public function getNameForLocaleAndScope(string $locale, string $scope): ?string
    {
        foreach ($this->name as $nameValue) {
            if ($nameValue->getLocale() === $locale && $nameValue->getScope() === $scope) {
                return $nameValue->getData();
            }
        }
        return null;
    }

    /**
     * Gets the first EAN value
     */
    // public function getFirstEan(): ?string
    // {
    //     return $this->ean[0]->getData() ?? null;
    // }
}
