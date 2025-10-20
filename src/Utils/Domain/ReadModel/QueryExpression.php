<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Utils\Domain\ReadModel;

/**
 * @psalm-import-type FilterItem from FilterExpression
 */
class QueryExpression implements \JsonSerializable, \Stringable
{
    private ?FilterExpression $filter = null;
    private ?SortExpression $sort = null;

    private function __construct(?array $filter, ?array $sort)
    {
        if (null !== $filter) {
            $this->filter = FilterExpression::create($filter);
        }
        if (null !== $sort) {
            $this->sort = SortExpression::create($sort);
        }
    }

    public function expr(): FilterExpression
    {
        return FilterExpression::create();
    }

    public function andWhere(FilterExpression ...$expr): static
    {
        $clone = clone $this;
        $clone->filter = $this->expr()->andX(...$expr);

        return $clone;
    }

    public function orWhere(FilterExpression ...$expr): static
    {
        $clone = clone $this;
        $clone->filter = $this->expr()->orX(...$expr);

        return $clone;
    }

    public function sortBy(string $field, string $dir = SortExpression::DIR_ASC): static
    {
        $clone = clone $this;
        $clone->sort ??= SortExpression::create();
        $clone->sort = match (mb_strtolower($dir)) {
            'asc' => $clone->sort->asc($field),
            'desc' => $clone->sort->desc($field),
            default => throw new \InvalidArgumentException(\sprintf('Invalid sort direction. Expected "ASC" or "DESC", but got "%s".', $dir)),
        };

        return $clone;
    }

    public function compactFilter(): static
    {
        $clone = clone $this;
        $clone->filter = $clone->filter?->compact();

        return $clone;
    }

    public function wrap(self $queryExpression): static
    {
        $filter = [];
        if (null !== $queryExpression->getFilter()) {
            $filter[] = clone $queryExpression->getFilter();
        }
        if (null !== $this->filter) {
            $filter[] = clone $this->filter;
        }

        $sort = [];
        if (null !== $queryExpression->getSort()) {
            $sort = array_merge($sort, $queryExpression->getSort()->items());
        }
        if (null !== $this->sort) {
            $fields = array_column($sort, 'field');
            $items = array_filter($this->sort->items(), fn ($f) => !\in_array($f, $fields, true));
            array_push($sort, ...$items);
        }

        $clone = clone $this;
        $clone->filter = null;
        if (\count($filter) > 0) {
            $clone->filter = $this->expr()->andX(...$filter);
        }
        $clone->sort = null;
        foreach ($sort as ['field' => $field, 'dir' => $dir]) {
            $clone = $clone->sortBy($field, $dir);
        }

        return $clone;
    }

    /**
     * @return FilterItem[]
     */
    public function fieldFilters(string $field): array
    {
        return $this->filter?->fieldFilters($field) ?? [];
    }

    public function fieldExpression(string $field): string
    {
        return $this->filter?->fieldExpression($field) ?? '';
    }

    public function sortDir(string $field): ?string
    {
        return $this->sort?->dir($field);
    }

    public function sortNum(string $field): ?int
    {
        return $this->sort?->num($field);
    }

    public function sortCount(): int
    {
        return $this->sort?->count() ?? 0;
    }

    public function getFilter(): ?FilterExpression
    {
        return $this->filter;
    }

    public function getSort(): ?SortExpression
    {
        return $this->sort;
    }

    public function resetFilter(?string $field = null): static
    {
        $clone = clone $this;
        $clone->filter = $clone->filter?->reset($field);

        return $clone;
    }

    public function resetSort(?string $field = null): static
    {
        $clone = clone $this;
        $clone->sort = $clone->sort?->reset($field);

        return $clone;
    }

    public function toArray(): array
    {
        return [
            'filter' => $this->filter?->toArray() ?: null,
            'sort' => $this->sort?->toArray() ?: null,
        ];
    }

    public function __toString(): string
    {
        $query = [];
        if (null !== $this->filter && ($this->filter->field() || \count($this->filter->filters()) > 0)) {
            $query['filter'] = $this->filter;
        }
        if (null !== $this->sort && \count($this->sort->sort()) > 0) {
            $query['sort'] = $this->sort;
        }

        return json_encode(0 === \count($query) ? null : $query);
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    public static function create(string|array|null $expression = null): static
    {
        if (\is_string($expression)) {
            $expression = json_decode($expression, true);
        }

        $expression = null === $expression ? [] : $expression;

        return new static($expression['filter'] ?? null, $expression['sort'] ?? null);
    }
}
