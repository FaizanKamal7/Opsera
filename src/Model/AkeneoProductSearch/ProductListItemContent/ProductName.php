<?php

namespace App\Model\AkeneoProductSearch\ProductListItemContent;

class ProductName
{
    public ?string $locale;
    public ?string $scope;
    public string $data;

    // Getters and setters
    public function getLocale(): ?string
    {
        return $this->locale;
    }
    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getScope(): ?string
    {
        return $this->scope;
    }
    public function setScope(?string $scope): void
    {
        $this->scope = $scope;
    }

    public function getData(): string
    {
        return $this->data;
    }
    public function setData(string $data): void
    {
        $this->data = $data;
    }
}
