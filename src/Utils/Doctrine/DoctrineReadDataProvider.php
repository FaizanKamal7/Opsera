<?php

declare(strict_types=1);

namespace App\Utils\Doctrine;

use App\Utils\Domain\Paginator\PaginatorInterface;
use App\Utils\Domain\ReadModel\FilterExpression;
use App\Utils\Domain\ReadModel\QueryExpression;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

trait DoctrineReadDataProvider
{
    private ?DataSource $dataSource = null;

    private function dataSource(): DataSource
    {
        return $this->dataSource ??= $this->createDataSource();
    }

    private function reset(): static
    {
        $this->dataSource = $this->createDataSource();

        return $this;
    }

    #[\Override]
    public function count(): int
    {
        return $this->dataSource()->autoprepare()->count();
    }

    #[\Override]
    public function totalCount(): int
    {
        return $this->dataSource()->autoprepare()->totalCount();
    }

    #[\Override]
    public function isPaginated(): bool
    {
        return $this->dataSource()->isPaginated();
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return $this->dataSource()->autoprepare()->isEmpty();
    }

    #[\Override]
    public function getIterator(): \Traversable
    {
        return $this->dataSource()->autoprepare()->getIterator();
    }

    #[\Override]
    public function data(): array
    {
        return $this->dataSource()->autoprepare()->data();
    }

    #[\Override]
    public function getResult(): array|object|null
    {
        return $this->dataSource()->autoprepare()->getResult();
    }

    #[\Override]
    public function paginator(): ?PaginatorInterface
    {
        return $this->dataSource()->autoprepare()->paginator();
    }

    #[\Override]
    public function withPagination(int $page, int $itemsPerPage): static
    {
        $clone = clone $this;
        $clone->dataSource = $clone->dataSource()->withPagination($page, $itemsPerPage);

        return $clone;
    }

    #[\Override]
    public function withoutPagination(): static
    {
        $clone = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutPagination();

        return $clone;
    }

    #[\Override]
    public function queryExpression(): ?QueryExpression
    {
        return $this->dataSource()->queryExpression();
    }

    #[\Override]
    public function withQueryExpression(QueryExpression $queryExpression): static
    {
        $clone = clone $this;
        $clone->dataSource = $clone->dataSource()->withQueryExpression($queryExpression);

        return $clone;
    }

    #[\Override]
    public function withoutQueryExpression(): static
    {
        $clone = clone $this;
        $clone->dataSource = $clone->dataSource()->withoutQueryExpression();

        return $clone;
    }

    public function handleRequest(Request $request, array $fieldsOperator = [], array $fieldsIgnoreCase = []): static
    {
        $clone = clone $this;

        $filters = [];

        // Load query

        $query = null;
        $queryBase = $request->query->get('query');
        if (null !== $queryBase) {
            $queryJson = base64_decode($queryBase, true);
            Assert::notFalse($queryJson, 'Invalid query expression parameter!');
            Assert::stringNotEmpty($queryJson, 'The query expression is empty!');
            $query = QueryExpression::create($queryJson);
            if (null !== $query->getFilter()) {
                $filters[] = $query->getFilter();
            }
        }

        // Load filters

        $ref = new \ReflectionObject($this);
        $fields = $ref->getConstants(\ReflectionClassConstant::IS_PUBLIC);
        $fields = array_filter($fields, fn ($c) => str_starts_with($c, 'FIELD_'), \ARRAY_FILTER_USE_KEY);
        $fields = array_values($fields);
        foreach ($fields as $field) {
            $value = $request->query->get($field);
            if (null === $value) {
                continue;
            }
            $operator = $fieldsOperator[$field] ?? FilterExpression::OP_EQ;
            $ignoreCase = $fieldsIgnoreCase[$field] ?? true;
            $filters[] = FilterExpression::create()->valX($field, $operator, $value, $ignoreCase);
        }

        // Load order by

        $sort = $request->query->all('order');
        $sort = array_intersect_key($sort, array_flip($fields));

        // Load pagination
        $page = (int) $request->query->get('page');
        $pageSize = (int) $request->get('pageSize');

        // Apply

        $query ??= QueryExpression::create();
        $query = $query->andWhere(...$filters);
        foreach ($sort as $field => $dir) {
            $query = $query->sortBy($field, $dir);
        }

        if ($page > 0 && $pageSize > 0) {
            $clone = $clone->withPagination($page, $pageSize);
        } else {
            $clone = $clone->withoutPagination();
        }

        return $clone->withQueryExpression($query);
    }
}
