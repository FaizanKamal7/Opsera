<?php

declare(strict_types=1);

namespace App\Utils\Doctrine;

use App\Utils\Domain\ReadModel\FilterExpression;
use App\Utils\Domain\ReadModel\QueryExpression;
use App\Utils\Domain\ReadModel\SortExpression;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\QueryBuilder;
use Webmozart\Assert\Assert;

/**
 * @psalm-import-type DataSourceOptionsWrapper from DataSource
 *
 * @template T of object
 */
class DataSourceBuilder
{
    private string|RawQuery|RawNativeQuery|RawQueryBuilder|NativeQuery|QueryBuilder|null $data = null;
    private ?QueryExpression $queryExpression = null;

    public function expr(): FilterExpression
    {
        return FilterExpression::create();
    }

    /**
     * @return DataSourceBuilder<T>
     */
    public function andWhere(FilterExpression ...$expr): static
    {
        $this->queryExpression ??= QueryExpression::create();
        $this->queryExpression = $this->queryExpression->andWhere(...$expr);

        return $this;
    }

    /**
     * @return DataSourceBuilder<T>
     */
    public function orWhere(FilterExpression ...$expr): static
    {
        $this->queryExpression ??= QueryExpression::create();
        $this->queryExpression = $this->queryExpression->orWhere(...$expr);

        return $this;
    }

    /**
     * @return DataSourceBuilder<T>
     */
    public function sortBy(string $field, string $dir = SortExpression::DIR_ASC): static
    {
        $this->queryExpression ??= QueryExpression::create();
        $this->queryExpression = $this->queryExpression->sortBy($field, $dir);

        return $this;
    }

    /**
     * @return DataSourceBuilder<T>
     */
    public function withData(string|RawQuery|RawNativeQuery|RawQueryBuilder|NativeQuery|QueryBuilder $data): static
    {
        $clone = clone $this;
        $clone->data = $data;

        return $clone;
    }

    /**
     * @return DataSourceBuilder<T>
     */
    public function withQueryExpression(QueryExpression $queryExpression): static
    {
        $clone = clone $this;
        $clone->queryExpression = $queryExpression;

        return $clone;
    }

    /**
     * @psalm-param DataSourceOptionsWrapper $options
     *
     * @return DataSource<T>
     */
    public function create(Connection $connection, array $options = []): DataSource
    {
        Assert::notNull($this->data);
        $options['connection'] = $connection;
        $dataSource = new DataSource($this->data, $options);

        if ($this->queryExpression) {
            $dataSource = $dataSource->withQueryExpression($this->queryExpression);
        }

        return $dataSource;
    }
}
