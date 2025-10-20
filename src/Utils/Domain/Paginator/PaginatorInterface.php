<?php

declare(strict_types=1);

namespace App\Utils\Domain\Paginator;

/**
 * @template-covariant T
 *
 * @extends \IteratorAggregate<array-key, T>
 */
interface PaginatorInterface extends \IteratorAggregate, \Countable
{
    public function getCurrentPage(): int;

    public function getItemsPerPage(): int;

    public function getLastPage(): int;

    public function getTotalItems(): int;

    /**
     * @return \Traversable<array-key, T>
     */
    public function getIterator(): \Traversable;
}
