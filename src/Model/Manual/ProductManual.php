<?php

namespace App\Model\Manual;

use App\Enum\DocumentManual\LanguageDocument;
use App\Enum\DocumentManual\SpecialDocument;

/**
 * Represents a single product manual containing multiple documents
 *
 * Each product manual can contain:
 * - Language-specific documents (DE, EN, ES, etc.)
 * - Special documents (ORG, CE, etc.)
 * 
 * Provides methods to filter documents by type
 */
class ProductManual
{
    /** @var Document[] */
    private array $documents = [];

    public function addDocument(string $type, Document $document): void
    {
        $this->documents[$type] = $document;
    }

    public function getDocument(string $type): ?Document
    {
        return $this->documents[$type] ?? null;
    }

    public function getDocuments(): array
    {
        return $this->documents;
    }

    public function getLanguageDocuments(): array
    {
        return array_filter($this->documents, fn($k) => in_array($k, LanguageDocument::values()), ARRAY_FILTER_USE_KEY);
    }

    public function getSpecialDocuments(): array
    {
        return array_filter($this->documents, fn($k) => in_array($k, SpecialDocument::values()), ARRAY_FILTER_USE_KEY);
    }
}
