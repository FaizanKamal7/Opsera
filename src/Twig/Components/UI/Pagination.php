<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent]
final class Pagination
{
    public int $currentPage = 1;
    public int $itemsPerPage = 10;
    public int $totalItems = 0;
    public int $range = 5;
    public bool $showTotalItems = false;

    private ?int $firstPage = null;
    private ?int $lastPage = null;
    private ?int $pageCount = null;
    private ?int $previousPage = null;
    private ?int $nextPage = null;
    private ?int $firstPageInRange = null;
    private ?int $lastPageInRange = null;
    private ?int $currentItemsCount = null;
    private ?int $firstItemNumber = null;
    private ?int $lastItemNumber = null;

    /**
     * @var array<array-key, int>
     */
    private array $pagesInRange = [];

    /**
     * @return array<array-key, mixed>
     *
     * @phpstan-ignore missingType.iterableValue
     */
    #[PreMount]
    public function preMount(array $data): array
    {
        return new OptionsResolver()
            ->setIgnoreUndefined(false)
            ->setDefaults([
                'range' => 5,
                'showTotalItems' => false,
            ])
            ->setRequired('currentPage')
            ->setRequired('itemsPerPage')
            ->setRequired('totalItems')
            ->setAllowedTypes('currentPage', 'int')
            ->setAllowedTypes('itemsPerPage', 'int')
            ->setAllowedTypes('totalItems', 'int')
            ->setAllowedTypes('range', 'int')
            ->setAllowedTypes('showTotalItems', 'bool')
            ->resolve($data)
        ;
    }

    public function mount(int $currentPage, int $itemsPerPage, int $totalItems, ?int $range = null): void
    {
        $this->currentPage = max($currentPage, 1);
        $this->itemsPerPage = max($itemsPerPage, 0);
        $this->totalItems = max($totalItems, 0);
        $this->range = max($range ?? $this->range, 3);

        $pageCount = $this->itemsPerPage > 0 ? (int) ceil($this->totalItems / $this->itemsPerPage) : 0;

        if ($this->range > $pageCount) {
            $this->range = $pageCount;
        }

        if (0 === $this->range) {
            $pages = [1];
        } else {
            $delta = (int) ceil($this->range / 2);
            if ($currentPage - $delta > $pageCount - $this->range) {
                $pages = range($pageCount - $this->range + 1, $pageCount);
            } else {
                if ($currentPage - $delta < 0) {
                    $delta = $currentPage;
                }
                $offset = $currentPage - $delta;
                $pages = range($offset + 1, $offset + $this->range);
            }
        }

        $this->firstPage = $pageCount > 0 ? 1 : null;
        $this->previousPage = $currentPage - 1 > 0 ? $currentPage - 1 : null;
        $this->nextPage = $currentPage + 1 <= $pageCount ? $currentPage + 1 : null;
        $this->lastPage = $pageCount > 0 ? $pageCount : null;
        $this->pageCount = $pageCount;
        $this->pagesInRange = $pages;
        $this->firstPageInRange = min($pages);
        $this->lastPageInRange = max($pages);

        if ($pageCount > 0) {
            $this->currentItemsCount = ($currentPage < $pageCount) ? $this->itemsPerPage : ($this->totalItems - ($this->itemsPerPage * ($pageCount - 1)));
            $this->firstItemNumber = (($currentPage - 1) * $this->itemsPerPage) + 1;
            $this->lastItemNumber = $this->firstItemNumber + $this->currentItemsCount - 1;
        }
    }

    public function getFirstPage(): ?int
    {
        return $this->firstPage;
    }

    public function getLastPage(): ?int
    {
        return $this->lastPage;
    }

    public function getPageCount(): ?int
    {
        return $this->pageCount;
    }

    public function getPreviousPage(): ?int
    {
        return $this->previousPage;
    }

    public function getNextPage(): ?int
    {
        return $this->nextPage;
    }

    public function getFirstPageInRange(): ?int
    {
        return $this->firstPageInRange;
    }

    public function getLastPageInRange(): ?int
    {
        return $this->lastPageInRange;
    }

    public function getCurrentItemsCount(): ?int
    {
        return $this->currentItemsCount;
    }

    public function getFirstItemNumber(): ?int
    {
        return $this->firstItemNumber;
    }

    public function getLastItemNumber(): ?int
    {
        return $this->lastItemNumber;
    }

    /**
     * @return array<array-key, int>
     */
    public function getPagesInRange(): array
    {
        return $this->pagesInRange;
    }
}
