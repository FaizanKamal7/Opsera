<?php

// src/Client/ManualsApiClient.php
namespace App\Client;

use App\Model\Manual\ProductManualCollection;
use App\Service\ManualParser;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Service client for fetching product manuals from an external API.
 * 
 * This class retrieves a list of product manuals using an HTTP client and caches the response 
 * to reduce redundant API requests. The data is parsed into a structured `ProductManualCollection` object.
 */
class ManualsApiClient
{
    /**
     * Constructor to initialize dependencies.
     * 
     * @param HttpClientInterface $client HTTP client for making API requests.
     *        Injected via 'wiltec_manuals.client', which must match a configured scoped client.
     * @param ManualParser $parser Parser service to transform API responses into domain objects.
     * @param CacheInterface $cache Cache service for storing API responses to minimize calls.
     *        Injected via 'manuals.cache'.
     */
    public function __construct(
        #[Autowire(service: 'wiltec_manuals.client')]
        private HttpClientInterface $client,
        private ManualParser $parser,
        #[Autowire(service: 'manuals.cache')]
        private CacheInterface $cache
    ) {}


    /**
     * Retrieves and parses the product manuals data.
     *
     * The response is cached to avoid unnecessary API requests. If the data is not in the cache,
     * it will fetch from the API and store it with a 1-hour expiration time.
     *
     * @return ProductManualCollection Parsed collection of product manuals.
     *
     * @throws \RuntimeException If the API request fails or data retrieval is unsuccessful.
     */
    public function searchProductManuals($searched_skus): ProductManualCollection
    {
        try {
            $responseData = $this->cache->get('manuals_data', function (ItemInterface $item) {
                $item->expiresAfter(3600);

                // Use the scoped client
                $response = $this->client->request(
                    'GET',
                    '/manuallist.json'
                );

                if (200 !== $response->getStatusCode()) {
                    throw new \RuntimeException('API request failed with status code: ' . $response->getStatusCode());
                }

                return $response->getContent();
            });

            return $this->parser->parse($responseData, $searched_skus);
        } catch (\Exception $e) {
            throw new \RuntimeException('Failed to fetch manuals data: ' . $e->getMessage(), 0, $e);
        }
    }
}
