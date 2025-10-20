<?php

namespace App\Model\AkeneoProductSearch;

use Symfony\Component\Serializer\Annotation\SerializedName;
use Symfony\Component\Validator\Constraints\Type;

class ProductListResponse
{
    #[SerializedName('_links')]
    public array $links = [];

    #[SerializedName('current_page')]
    public int $currentPage;

    /** @var ProductListItem[] */
    #[SerializedName('_embedded.items')]
    public array $productListItems = [];

    // Getters and setters
    public function getLinks(): array
    {
        return $this->links;
    }
    public function setLinks(array $links): void
    {
        $this->links = $links;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }
    public function setCurrentPage(int $currentPage): void
    {
        $this->currentPage = $currentPage;
    }

    public function getProductListItems(): array
    {
        return $this->productListItems;
    }

    public function setProductListItems(array $productListItems): void
    {
        $this->productListItems = $productListItems;
    }
}
