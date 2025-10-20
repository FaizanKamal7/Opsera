<?php

namespace App\Twig\Components\ProductSearch;

use App\Client\AkeneoProductClient;
use App\Client\ManualsApiClient;
use App\Model\AkeneoProductSearch\ProductListItem;
use App\Model\AkeneoProductSearch\ProductListResponse;
use App\Model\Manual\ProductManualCollection;
use Psr\Log\LoggerInterface;
use App\Service\LoadingStateService;
use App\Trait\HasLoadingStateTrait;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveListener;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'SearchResult',
    template: 'components/ProductSearch/SearchResult.html.twig'
)]
final class SearchResult
{
    use DefaultActionTrait;
    use HasLoadingStateTrait;

    private CacheInterface $cache;
    private ManualsApiClient $manualsApiClient;
    private AkeneoProductClient $akeneoProductClient;
    private ProductListResponse $productNamesResponse;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public string $searchBy = 'sku';

    public function __construct(
        CacheInterface          $cache,
        AkeneoProductClient     $akeneoProductClient,
        ManualsApiClient        $manualsApiClient,
        ProductListResponse     $productNamesResponse,
        private LoggerInterface $logger,
        LoadingStateService     $loadingStateService
    )
    {
        $this->cache = $cache;
        $this->akeneoProductClient = $akeneoProductClient;
        $this->manualsApiClient = $manualsApiClient;
        $this->productNamesResponse = $productNamesResponse;
        $this->setLoadingStateService($loadingStateService);
    }

    #[LiveListener('updateSearch')]
    public function onSearchUpdated(
        #[LiveArg('query')] string    $query,
        #[LiveArg('searchBy')] string $searchBy
    ): void
    {
        $this->query = $query;
        $this->searchBy = $searchBy;
        $this->logger->info('Received query: ' . $query . ' and searchBy: ' . $searchBy);
    }

    public function getProducts(): array
    {
        $this->startLoading();
        $this->logger->info('Loading Before: ' . $this->isLoading());

        if ($this->searchBy == 'name') {
            $this->productNamesResponse = $this->akeneoProductClient->extractProductsByNames(searchTerm: $this->query);
            $this->query = $this->getSKUsFromExtractedProductsByName($this->productNamesResponse);
        }
        $manualCollection = $this->manualsApiClient->searchProductManuals(searched_skus: $this->query);
        $filteredProducts = $this->filterProducts($manualCollection);
        $this->stopLoading();

        $this->logger->info('Loading: ' . $this->isLoading());

        return $filteredProducts;
    }

    private function filterProducts(ProductManualCollection $manualCollection): array
    {
        if (empty($this->query)) {
            return [];
        }

        $filtered = [];

        // Create a lookup array for products by identifier
        $productsById = [];

        foreach ($this->productNamesResponse->getProductListItems() as $productItem) {
            $productsById[$productItem->getIdentifier()] = $productItem;
        }

        foreach ($manualCollection->getManuals() as $sku => $manual) {
            $productName = '';

            if (isset($productsById[$sku])) {
                $productName = $productsById[$sku]->getNameForLocaleAndScope('de_DE', 'amazon')
                    ?? 'Name not available';
            }

            $filtered[$sku] = [
                'manual' => $manual,
                'name' => $productName
            ];
        }

        return $filtered;
    }

    private function getSKUsFromExtractedProductsByName(ProductListResponse $productNamesResponse): string
    {
        $extracted_skus = array_map(
            callback: fn(ProductListItem $item) => $item->identifier,
            array: $productNamesResponse->getProductListItems()
        );
        return implode(', ', $extracted_skus);
    }
}
