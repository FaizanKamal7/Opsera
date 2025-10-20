<?php

declare(strict_types=1);

namespace App\Utils\Doctrine\Pagination;

use App\Utils\Doctrine\AbstractRawQuery;
use App\Utils\Doctrine\RawQueryBuilder;
use App\Utils\Domain\Paginator\PaginatorInterface;

/**
 * @template T of object
 *
 * @implements PaginatorInterface<T>
 */
class RawSqlPaginator implements PaginatorInterface
{
    private AbstractRawQuery $query;
    private int $firstResult;
    private int $maxResults;

    public function __construct(RawQueryBuilder|AbstractRawQuery $query)
    {
        if ($query instanceof RawQueryBuilder) {
            $query = $query->getQuery();
        }

        //        if (!$query instanceof AbstractRawQuery) {
        //            throw new \RuntimeException(sprintf(
        //                'Invalid pagination query. Expected instance of "%s" or "%s", but got "%s"',
        //                AbstractRawQuery::class, RawQueryBuilder::class, get_class($query)
        //            ));
        //        }

        $firstResult = $query->getFirstResult();
        if (null === $firstResult) {
            throw new \InvalidArgumentException('Missing "firstResult" from the query.');
        }
        $maxResults = $query->getMaxResults();
        if (null === $maxResults) {
            throw new \InvalidArgumentException('Missing "maxResults" from the query.');
        }
        $this->firstResult = $firstResult;
        $this->maxResults = $maxResults;

        $this->query = $query;
    }

    public function getItemsPerPage(): int
    {
        return $this->maxResults;
    }

    public function getCurrentPage(): int
    {
        if (0 >= $this->maxResults) {
            return 1;
        }

        return 1 + (int) floor($this->firstResult / $this->maxResults);
    }

    public function getLastPage(): int
    {
        if (0 >= $this->maxResults) {
            return 1;
        }

        return (int) (ceil($this->getTotalItems() / $this->maxResults) ?: 1);
    }

    public function getTotalItems(): int
    {
        return $this->query->getCount();
    }

    public function count(): int
    {
        return iterator_count($this->getIterator());
    }

    /**
     * @return \Traversable<array-key, T>
     */
    #[\ReturnTypeWillChange]
    public function getIterator(): \Traversable
    {
        return $this->query->toIterable();
    }
}
