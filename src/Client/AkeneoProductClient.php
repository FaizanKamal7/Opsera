<?php

// src/Client/ManualsApiClient.php
namespace App\Client;

use App\Model\AkeneoProductSearch\ProductListItem;
use App\Model\AkeneoProductSearch\ProductListItemContent\ProductName;
use App\Model\AkeneoProductSearch\ProductListResponse;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for interacting with Akeneo PIM API
 * 
 * Handles authentication, product search, and response parsing for Akeneo PIM API.
 * Uses Symfony HttpClient for requests and Cache component for token/product caching.
 */
class AkeneoProductClient
{
    private const TOKEN_CACHE_KEY = 'akeneo_access_token';
    private const TOKEN_EXPIRY = 3600;

    public function __construct(
        private HttpClientInterface $client,
        private CacheInterface $cache,
        #[Autowire('%env(AKENEO_CLIENT_ID)%')]
        private string $clientId,
        #[Autowire('%env(AKENEO_CLIENT_SECRET)%')]
        private string $clientSecret,
        #[Autowire('%env(AKENEO_USERNAME)%')]
        private string $username,
        #[Autowire('%env(AKENEO_PASSWORD)%')]
        private string $password,
        #[Autowire('%env(AKENEO_OAUTH_TOKEN_URL)%')]
        private string $tokenUrl,
        #[Autowire('%env(AKENEO_API_BASE_URL)%')]
        private string $baseUrl,
        private SerializerInterface $serializer,
        private DenormalizerInterface&NormalizerInterface $normalizer,

    ) {}

    /**
     * Search products by name with locale and scope filtering
     * Endpoint docs: https://api.akeneo.com/api-reference.html#get_products
     * 
     * @param string $searchTerm Search term to match against product names
     * @param string $locale Locale for name search (default: 'de_DE')
     * @param string $scope Scope for name search (default: 'amazon')
     * @return ProductListResponse Collection of matching products
     * @throws \RuntimeException When API request fails
     */
    public function extractProductsByNames(string $searchTerm, string $locale = 'de_DE', string $scope = 'amazon'): ProductListResponse
    {
        $cacheKey = sprintf('akeneo_products_%s_%s_%s', $searchTerm, $locale, $scope);

        try {
            return $this->cache->get($cacheKey, function (ItemInterface $item) use ($searchTerm, $locale, $scope) {
                $item->expiresAfter(3600);

                $search_query = [
                    'search' => json_encode([
                        'name' => [[
                            'operator' => 'CONTAINS',
                            'value' => $searchTerm,
                            'locale' => $locale,
                            'scope' => $scope
                        ]]
                    ])
                ];

                $response = $this->client->request('GET', $this->baseUrl . 'rest/v1/products', [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Authorization' => 'Bearer ' . $this->getOrRefreshToken()
                    ],
                    'query' => $search_query
                ]);

                return $this->parseProductResponse($response);
            });
        } catch (\Exception $e) {
            throw new \RuntimeException('API request failed: ' . $e->getMessage());
        }
    }

    private function parseProductResponse(ResponseInterface $response): ProductListResponse
    {
        $data = json_decode($response->getContent(), true);
        $collection = new ProductListResponse();
        $product_list_items = [];

        // Set basic response properties
        if (isset($data['_links'])) {
            $collection->setLinks($data['_links']);
        }
        if (isset($data['current_page'])) {
            $collection->setCurrentPage($data['current_page']);
        }

        foreach ($data["_embedded"]["items"] as $product) {
            if (is_array($product)) {
                $product_list_items[] = $this->createProductListItem($product);
            }
        }

        $collection->setProductListItems($product_list_items);
        return $collection;
    }

    private function createProductListItem(array $product): ProductListItem
    {
        $names = [];
        foreach ($product["values"]["name"] as $nameData) {
            $names[] = $this->normalizer->denormalize($nameData, ProductName::class);
        }

        return $this->normalizer->denormalize(
            [
                'identifier' => $product["identifier"],
                'ean' => $product["values"]["EAN"][0]["data"],
                'name' => $names
            ],
            ProductListItem::class
        );
    }

    /**
     * Gets or refreshes the OAuth access token
     * 
     * @return string Access token
     * @throws \RuntimeException When token request fails
     */
    private function getOrRefreshToken(): string
    {
        return $this->cache->get(self::TOKEN_CACHE_KEY, function (ItemInterface $item) {
            $item->expiresAfter(self::TOKEN_EXPIRY - 300);
            $response = $this->client->request('POST', $this->tokenUrl, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                ],
                'body' => [
                    'grant_type' => 'password',
                    'username' => $this->username,
                    'password' => $this->password,
                    'client_id' => $this->clientId,
                    'client_secret' => $this->clientSecret
                ],
            ]);

            $data = $response->toArray();

            if (!isset($data['access_token'])) {
                throw new \RuntimeException('Token request failed: ' . json_encode($data));
            }

            return $data['access_token'];
        });
    }
}
