<?php

namespace App\Model\Manual;

use ApiPlatform\Metadata\ApiResource;
use Symfony\Component\Serializer\Attribute\SerializedName;

/**
 * Represents a single document with file path and modification date
 *
 * This Model contains:
 * - The file path to the document
 * - The last modified timestamp of the document
 */
class Document
{
    public function __construct(
        private string $file,
        #[SerializedName('date')]
        private \DateTimeInterface $lastModified
    ) {}

    public function getFile(): string
    {
        return $this->file;
    }

    public function getLastModified(): \DateTimeInterface
    {
        return $this->lastModified;
    }
}
