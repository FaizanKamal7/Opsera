<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Utils\Doctrine;

use App\Utils\Doctrine\DataSource\Builder\Feature\PaginationFeature;
use App\Utils\Doctrine\DataSource\Builder\Feature\QueryExpressionFeature;
use App\Utils\Doctrine\DataSource\Builder\PaginationFeatureInterface;
use App\Utils\Doctrine\DataSource\Builder\QueryExpressionFeatureInterface;
use App\Utils\Doctrine\DataSource\Builder\ValueFeatureInterface;
use App\Utils\Domain\Paginator\PaginatorInterface;
use App\Utils\Domain\ReadModel\QueryExpression;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\NativeQuery;
use Doctrine\ORM\QueryBuilder;
use Webmozart\Assert\Assert;

/**
 * @psalm-type BuilderFeatureDefinition = class-string<DataSource\Builder\FeatureInterface>|array{
 *     class: class-string<DataSource\Builder\FeatureInterface>,
 *     priority: int,
 *     options?: array<string, mixed>
 * }
 * @psalm-type BuilderOptions = array{
 *     builder: array{
 *         quoteTableAlias: bool,
 *         quoteFieldNames: bool,
 *         quoteFieldNamesChar: string,
 *         quoteFieldNamesAlways: bool,
 *         params: array<array-key, mixed>,
 *         paramTypes: array<array-key, mixed>,
 *     },
 *     features: array<string, BuilderFeatureDefinition>
 * }
 * @psalm-type DataSourceOptions = array{
 *     connection: Connection|null,
 *     hydrator: 1|2|3|4|5|6|string,
 *     root_identifier: string,
 *     root_alias: string,
 *     query: array{
 *         item_normalizer: callable|null,
 *     },
 *     builder: array{
 *         quoteTableAlias: bool,
 *         quoteFieldNames: bool,
 *         quoteFieldNamesChar: string,
 *         quoteFieldNamesAlways: bool,
 *         params: array,
 *         paramTypes: array,
 *     },
 *     features: array<string, BuilderFeatureDefinition>,
 *     normalizer: callable|null,
 *     denormalizer: callable|null,
 * }
 * @psalm-type DataSourceOptionsWrapper = DataSourceOptions|array<never, never>
 *
 * @template T of object
 */
class DataSource
{
    public const int DEFAULT_HYDRATOR = AbstractQuery::HYDRATE_ARRAY;

    /**
     * @psalm-var DataSourceOptions
     */
    private array $options;
    private array $preparedParams = [];
    private QueryBuilder|AbstractRawQuery $dataSet;
    private ?DataSource\Builder $builder = null;

    /**
     * @psalm-param DataSourceOptionsWrapper $options
     */
    public function __construct(string|RawQuery|RawNativeQuery|RawQueryBuilder|NativeQuery|QueryBuilder $data, array $options = [])
    {
        /** @psalm-var DataSourceOptions $options */
        $options = array_replace_recursive([
            'connection' => null,
            'hydrator' => self::DEFAULT_HYDRATOR,
            'root_identifier' => 'id',
            'root_alias' => 'r',
            'query' => [
                'item_normalizer' => null,
            ],
            'builder' => [
                'quoteTableAlias' => false,
                'quoteFieldNames' => false,
                'quoteFieldNamesChar' => '"',
                'quoteFieldNamesAlways' => false,
                'params' => [],
                'paramTypes' => [],
            ],
            'features' => [
            ],
            'normalizer' => null,
            'denormalizer' => null,
        ], $options);

        $this->options = $options;

        if (null !== $this->options['normalizer']) {
            $this->options['query']['item_normalizer'] = $this->options['normalizer'];
        }
        if (null !== $this->options['denormalizer']) {
            $this->options['query']['item_normalizer'] = $this->options['denormalizer'];
        }

        if (\is_string($data)) {
            if (null === ($this->options['connection'] ?? null)) {
                throw new \RuntimeException('You must specify a doctrine "connection" into the DataSource options when using the DataSource with plain (RAW) SQL!');
            }
            if (!$this->options['connection'] instanceof Connection) {
                throw new \RuntimeException(\sprintf('Invalid DataSource connection. Expected instance of "%s", but got "%s"', Connection::class, \is_object($this->options['connection']) ? \get_class($this->options['connection']) : \gettype($this->options['connection'])));
            }
            $query = new RawQuery($this->options['connection'], $this->options['query']);
            $query->setSql($data);
            $data = $query;
        }

        if ($data instanceof NativeQuery) {
            $data = new RawNativeQuery($data, $this->options['query']);
        }

        if ($data instanceof RawQueryBuilder) {
            $data = $data->getQuery();
        }

        if (!$data instanceof QueryBuilder && !$data instanceof AbstractRawQuery) {
            throw new \RuntimeException(\sprintf('Invalid DataSource data. Expected string or instance of one of the following classes: %s, but got "%s"', '"'.implode('", "', [RawQuery::class, RawNativeQuery::class, RawQueryBuilder::class, NativeQuery::class, QueryBuilder::class]).'"', \is_object($data) ? $data::class : \gettype($data)));
        }

        if ($data instanceof AbstractRawQuery) {
            $data->setOptions($this->options['query']);
        }

        $this->dataSet = $data;
    }

    protected function createBuilder(): DataSource\Builder
    {
        /** @psalm-var BuilderOptions $options */
        $options = $this->options;
        if (null === ($this->options['features']['query_expression'] ?? null)) {
            $options['features']['query_expression'] = [
                'class' => QueryExpressionFeature::class,
                'priority' => 200,
            ];
        }
        if (null === ($this->options['features']['pagination'] ?? null)) {
            $options['features']['pagination'] = [
                'class' => PaginationFeature::class,
                'priority' => 0,
            ];
        }

        return new DataSource\Builder(clone $this->dataSet, $options);
    }

    protected function destroyBuilder(): void
    {
        $this->builder?->unload();
        $this->builder = null;
    }

    protected function getBuilder(): DataSource\Builder
    {
        return $this->builder ??= $this->createBuilder();
    }

    /**
     * @psalm-param array{
     *     queryExpression: QueryExpression|array|string|null,
     *     page: int|null,
     *     pageSize: int|null,
     *     ...
     * }|array<never, never> $params
     *
     * @return DataSource<T>
     */
    public function prepare(array $params = []): static
    {
        $this->assertPrepared(false);
        $this->getBuilder()->load()->prepare($params);
        $this->preparedParams = $params;

        return $this;
    }

    /**
     * @return DataSource<T>
     */
    public function unprepare(): static
    {
        $this->assertPrepared(true);
        $this->destroyBuilder();
        $this->preparedParams = [];

        return $this;
    }

    /**
     * @return DataSource<T>
     */
    public function autoprepare(): static
    {
        if (!$this->isPrepared()) {
            $this->prepare($this->preparedParams);
        }

        return $this;
    }

    public function isPrepared(): bool
    {
        return $this->builder && $this->builder->isLoaded();
    }

    /**
     * @return AbstractRawQuery<T>|AbstractQuery
     */
    public function getQuery(): AbstractRawQuery|AbstractQuery
    {
        $this->assertPrepared(true);

        return $this->getBuilder()->getQuery();
    }

    /**
     * @return AbstractRawQuery<T>
     */
    public function getRawQuery(): AbstractRawQuery
    {
        if (!$this->isPrepared()) {
            $this->prepare();
        }
        $query = $this->getQuery();
        Assert::isInstanceOf($query, AbstractRawQuery::class);

        return $query;
    }

    /**
     * @return PaginatorInterface<T>|null
     */
    public function paginator(): ?PaginatorInterface
    {
        return $this->builder?->getPaginator();
    }

    /**
     * @return \Traversable<array-key, T>
     *
     * @noinspection PhpUnhandledExceptionInspection
     * @noinspection PhpDocMissingThrowsInspection
     */
    public function getIterator(): \Traversable
    {
        if (null !== $paginator = $this->paginator()) {
            yield from $paginator;

            return;
        }

        yield from $this->getQuery()->toIterable();
    }

    public function isEmpty(): bool
    {
        return 0 === $this->getQuery()->getCount();
    }

    public function count(): int
    {
        if (null !== $paginator = $this->paginator()) {
            return $paginator->count();
        }

        return $this->totalCount();
    }

    public function totalCount(): int
    {
        return $this->getQuery()->getCount();
    }

    /**
     * @return T[]
     */
    public function data(): array
    {
        return iterator_to_array($this->getIterator());
    }

    public function isPaginated(): bool
    {
        $feature = $this->getBuilder()->getFeatureOf(PaginationFeatureInterface::class);

        return $feature && $feature->canApply();
    }

    public function isValue(): bool
    {
        $feature = $this->getBuilder()->getFeatureOf(ValueFeatureInterface::class);

        return $feature && $feature->canApply();
    }

    /**
     * @return T[]|object{data: T[], page: int, total: int}|null
     */
    public function getResult(): array|object|null
    {
        $data = $this->data();

        $isValue = $this->isValue();

        if ($isValue) {
            return $this->getBuilder()->getFeatureOf(ValueFeatureInterface::class)?->sortData($data);
        }

        return [
            'data' => $data,
            'page' => $this->isPaginated() ? ($this->paginator()?->getCurrentPage() ?? 1) : 1,
            'total' => $this->totalCount(),
        ];
    }

    public function queryExpression(): ?QueryExpression
    {
        return $this->builder?->getQueryExpression();
    }

    /**
     * @return DataSource<T>
     */
    public function withQueryExpression(QueryExpression $queryExpression): static
    {
        $this->assertPrepared(false);

        $cloned = clone $this;
        $cloned->getBuilder()
            ->enableFeature(QueryExpressionFeatureInterface::class)
            ->setQueryExpression($queryExpression)
        ;

        return $cloned;
    }

    /**
     * @return DataSource<T>
     */
    public function withoutQueryExpression(): static
    {
        $this->assertPrepared(false);

        $cloned = clone $this;
        $cloned->getBuilder()
            ->disableFeature(QueryExpressionFeatureInterface::class)
            ->setQueryExpression(null)
        ;

        return $cloned;
    }

    /**
     * @return DataSource<T>
     */
    public function withPagination(int $page, int $itemsPerPage): static
    {
        Assert::positiveInteger($page);
        Assert::positiveInteger($itemsPerPage);

        $this->assertPrepared(false);

        $cloned = clone $this;
        $cloned->getBuilder()
            ->enableFeature(PaginationFeatureInterface::class)
            ->setPage($page)
            ->setItemsPerPage($itemsPerPage)
        ;

        return $cloned;
    }

    /**
     * @return DataSource<T>
     */
    public function withoutPagination(): static
    {
        $this->assertPrepared(false);

        $cloned = clone $this;
        $cloned->getBuilder()
            ->disableFeature(PaginationFeatureInterface::class)
            ->setPage(null)
            ->setItemsPerPage(null)
        ;

        return $cloned;
    }

    private function assertPrepared(bool $prepared): void
    {
        match ($prepared) {
            true => Assert::true($this->isPrepared(), 'The dataset is not prepared!'),
            false => Assert::false($this->isPrepared(), 'The dataset is prepared!'),
        };
    }

    public function __clone()
    {
        if ($this->builder) {
            $this->builder = clone $this->builder;
        }
        if ($this->isPrepared()) {
            $this->prepare($this->preparedParams);
        }
    }
}
