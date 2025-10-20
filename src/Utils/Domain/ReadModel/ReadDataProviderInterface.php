<?php

declare(strict_types=1);

namespace App\Utils\Domain\ReadModel;

use App\Utils\Domain\Paginator\PaginatorInterface;

/**
 * @template-covariant T of object|array<string, mixed>
 *
 * @extends \IteratorAggregate<array-key, T>
 */
interface ReadDataProviderInterface extends \IteratorAggregate, \Countable
{
    public function count(): int;

    public function totalCount(): int;

    public function isPaginated(): bool;

    public function isEmpty(): bool;

    /**
     * @return \Traversable<array-key, T>
     */
    public function getIterator(): \Traversable;

    /**
     * @return T[]
     */
    public function data(): array;

    /**
     * @return T[]|object{data: T[], page: int, total: int}|null
     */
    public function getResult(): array|object|null;

    /**
     * @return PaginatorInterface<T>|null
     */
    public function paginator(): ?PaginatorInterface;

    /**
     * @return static<T>
     */
    public function withPagination(int $page, int $itemsPerPage): static;

    /**
     * @return static<T>
     */
    public function withoutPagination(): static;

    public function queryExpression(): ?QueryExpression;

    /**
     * @return static<T>
     */
    public function withQueryExpression(QueryExpression $queryExpression): static;

    /**
     * @return static<T>
     */
    public function withoutQueryExpression(): static;
}
