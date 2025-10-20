<?php

namespace App\Service;

use App\Model\Manual\Document;
use App\Model\Manual\ProductManual;
use App\Model\Manual\ProductManualCollection;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Service for parsing JSON manual data into Models
 *
 * Responsibilities:
 * - Deserialize JSON from external API
 * - Transform raw data into structured Models
 * - Handle date formatting and type conversion
 * - Extends AbstractController to use getParameter()
 */
class ManualParser extends AbstractController
{
    public function __construct(
        private SerializerInterface $serializer,
        private DenormalizerInterface&NormalizerInterface $normalizer,
    ) {}

    public function parse(string $json, string $searched_skus): ProductManualCollection
    {
        $data = json_decode($json);
        $collection = new ProductManualCollection();
        foreach ($data as $sku => $documents) {
            if (str_contains($sku, $searched_skus) || str_contains($searched_skus, $sku)) {
                $manual = new ProductManual($sku);
                foreach ($documents as $type => $documentData) {
                    $document = $this->normalizer->denormalize(
                        [
                            'file' =>  $this->getParameter('manuals.domain') . $documentData->file,
                            'date' => $documentData->date,
                        ],
                        Document::class
                    );
                    $manual->addDocument($type, $document);
                }
                $collection->addManual($sku, $manual);
            }
        }
        return $collection;
    }
}
