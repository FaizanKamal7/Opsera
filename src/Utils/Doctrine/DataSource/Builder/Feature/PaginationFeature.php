<?php

declare(strict_types=1);

namespace App\Utils\Doctrine\DataSource\Builder\Feature;

use App\Utils\Doctrine\DataSource\Builder\AbstractFeature;
use App\Utils\Doctrine\DataSource\Builder\PaginationFeatureInterface;

class PaginationFeature extends AbstractFeature implements PaginationFeatureInterface
{
    private ?int $pageSize = null;
    private ?int $page = null;

    public function getDefaultOptions(): array
    {
        return [
            'useOutputWalkers' => false,
            'fetchJoinCollection' => true,
            'defaultPageSize' => null,
            'options' => [],
        ];
    }

    protected function doPrepare(array $params): bool
    {
        $opt = $this->getOptions();

        $pageSize = $opt['defaultPageSize'];
        $itemsPerPage = $opt['defaultPageSize'];
        $page = null;
        $skip = null;
        $take = null;

        $defaults = compact([
            'pageSize',
            'itemsPerPage',
            'page',
            'skip',
            'take',
        ]);

        $params = array_replace($defaults, array_intersect_key($params, $defaults));
        extract($params);

        if (null === $pageSize) {
            $pageSize = $itemsPerPage;
        }

        if (null === $pageSize) {
            $pageSize = $take;
        }

        $this->pageSize = null;
        $this->page = null;

        if (null !== $pageSize && (int) $pageSize > 0) {
            $this->pageSize = $pageSize;
        }

        if (null !== $page && (int) $page > 0) {
            $this->page = $page;
        }

        if (null !== $skip && null === $this->page && $this->pageSize > 0) {
            $this->page = (int) ceil($skip / $this->pageSize);
        }

        return null !== $this->pageSize || null !== $this->page;
    }

    protected function doUnprepare(): void
    {
        $this->pageSize = null;
        $this->page = null;
    }

    protected function doApply(): void
    {
        $this->getBuilder()
            ->setPage($this->page)
            ->setItemsPerPage($this->pageSize)
            ->setPaginatorOptions($this->getOptions());
    }

    protected function doUnApply(): void
    {
        $this->getBuilder()
            ->setPage(null)
            ->setItemsPerPage(null)
            ->setPaginatorOptions([]);
    }

    public function setPage(?int $page): void
    {
        $this->page = $page;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function setItemsPerPage(?int $itemsPerPage): void
    {
        $this->pageSize = $itemsPerPage;
    }

    public function getItemsPerPage(): ?int
    {
        return $this->pageSize;
    }
}
