<?php

/** @noinspection PhpUnused */
/* @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace App\Utils\Doctrine\DataSource;

use App\Utils\Doctrine\AbstractRawQuery;
use App\Utils\Doctrine\DataSource\Builder\PaginationFeatureInterface;
use App\Utils\Doctrine\Pagination\DoctrinePaginator;
use App\Utils\Doctrine\Pagination\RawSqlPaginator;
use App\Utils\Doctrine\RawNativeQuery;
use App\Utils\Doctrine\Tools\QueryParts;
use App\Utils\Domain\Paginator\PaginatorInterface;
use App\Utils\Domain\ReadModel\QueryExpression;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Webmozart\Assert\Assert;

/**
 * @psalm-type BuilderFeatureDefinition = class-string<Builder\FeatureInterface>|array{
 *     class: class-string<Builder\FeatureInterface>,
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
 * @psalm-type PaginatorOptions = array{
 *      fetchJoinCollection: bool,
 *      useOutputWalkers: bool,
 *      defaultPageSize: int|null,
 *      options: array,
 *  }|array<never, never>
 */
class Builder
{
    private QueryBuilder|AbstractRawQuery $dataSet;
    private QueryBuilder|AbstractRawQuery|null $preparedDataSet = null;
    private AbstractQuery|AbstractRawQuery|null $query = null;

    /**
     * @var BuilderOptions|array<never, never>
     */
    private array $options;

    /**
     * @var Builder\FeaturePayload[]
     */
    private array $features;
    /** @psalm-var array<string, Builder\FeaturePayload> */
    private array $disabledFeaturePayloads = [];
    private ?int $page = null;
    private ?int $itemsPerPage = null;

    /**
     * @var PaginatorOptions
     */
    private array $paginatorOptions = [];
    private ?QueryExpression $queryExpression = null;
    private QueryParts $queryParts;
    private array $params = [];
    private array $paramTypes = [];

    private ?PaginatorInterface $paginator = null;

    /**
     * @psalm-param BuilderOptions|array<never, never> $options
     */
    public function __construct(QueryBuilder|AbstractRawQuery $dataSet, array $options = [])
    {
        $this->dataSet = $dataSet;
        $this->queryParts = new QueryParts();

        if (!isset($options['features']) || !\is_array($options['features'])) {
            $options['features'] = [];
        }

        $this->options = $options;

        $this->loadFeatures($options['features']);
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function getDataSet(): AbstractRawQuery|QueryBuilder
    {
        return $this->dataSet;
    }

    public function parts(): QueryParts
    {
        return $this->queryParts;
    }

    protected function sortFeatures(): void
    {
        /** @psalm-var \SplPriorityQueue<int, Builder\FeaturePayload> $list */
        $list = new \SplPriorityQueue();
        foreach ($this->features as $feature) {
            $list->insert($feature, $feature->getPriority());
        }
        $features = [];
        foreach ($list as $feature) {
            $features[] = $feature;
        }
        $this->features = $features;
    }

    /**
     * @psalm-param array<string, BuilderFeatureDefinition> $features
     */
    protected function loadFeatures(array $features): static
    {
        $this->features = [];
        foreach ($features as $ident => $feature) {
            $featureClass = $feature;
            $featureOptions = [];
            $featurePriority = 0;
            if (\is_array($feature)) {
                if (!\array_key_exists('class', $feature)) {
                    throw new \RuntimeException('Invalid dataset build feature declaration. Missing the feature class.');
                }
                $featureClass = $feature['class'];
                if (\array_key_exists('options', $feature)) {
                    $featureOptions = $feature['options'];
                }
                if (\array_key_exists('priority', $feature)) {
                    $featurePriority = $feature['priority'];
                }
            }
            Assert::string($featureClass);
            if (!\array_key_exists($ident, $this->disabledFeaturePayloads)) {
                $this->registerFeature($ident, $featureClass, $featureOptions, $featurePriority);
            }
        }

        return $this;
    }

    /**
     * @psalm-param class-string<Builder\FeatureInterface> $class
     */
    public function registerFeature(string $name, string $class, array $options = [], int $priority = 0): static
    {
        if ($this->getFeaturePayload($name)) {
            throw new \RuntimeException(\sprintf('Dataset build feature "%s" is already registered.', $name));
        }
        if ($this->getFeaturePayloadOf($class)) {
            throw new \RuntimeException(\sprintf('There is already a loaded feature of class "%s".', $class));
        }

        $opt = $this->getOptions();
        if (\array_key_exists($name, $opt) && \is_array($opt[$name])) {
            $options = array_replace_recursive($options, $opt[$name]);
        }

        $options = array_replace_recursive([
            'quoteTableAlias' => $opt['builder']['quoteTableAlias'] ?? false,
            'quoteFieldNames' => $opt['builder']['quoteFieldNames'] ?? false,
            'quoteFieldNamesChar' => $opt['builder']['quoteFieldNamesChar'] ?? '"',
            'quoteFieldNamesAlways' => $opt['builder']['quoteFieldNamesAlways'] ?? false,
        ], $options);

        $this->features[] = new Builder\FeaturePayload($name, $class, $options, $priority);

        $this->sortFeatures();

        return $this;
    }

    protected function getFeaturePayload(string $name): ?Builder\FeaturePayload
    {
        return array_find($this->features, fn ($feature) => $feature->getName() === $name);
    }

    /**
     * @psalm-param class-string<Builder\FeatureInterface> $class
     */
    protected function getFeaturePayloadOf(string $class): ?Builder\FeaturePayload
    {
        return array_find($this->features, fn ($feature) => $feature::class === $class || is_subclass_of($feature, $class));
    }

    public function getFeature(string $name): ?Builder\FeatureInterface
    {
        $payload = $this->getFeaturePayload($name);
        if ($payload && $payload->isLoaded()) {
            return $payload->getFeature();
        }

        return null;
    }

    /**
     * @template V of Builder\FeatureInterface
     *
     * @param class-string<V> $class
     *
     * @psalm-return V|null
     */
    public function getFeatureOf(string $class): ?Builder\FeatureInterface
    {
        $payload = $this->getFeaturePayloadOf($class);
        if ($payload && $payload->isLoaded()) {
            return $payload->getFeature();
        }

        return null;
    }

    /**
     * @psalm-param Builder\FeaturePayload|class-string<Builder\FeatureInterface> $payload
     */
    public function disableFeature(Builder\FeaturePayload|string $payload): static
    {
        if (\is_string($payload)) {
            $item = $this->getFeaturePayload($payload) ?: $this->getFeaturePayloadOf($payload);
            if (!$item) {
                return $this;
            }
            $payload = $item;
        }
        $newFeatures = [];
        foreach ($this->features as $feature) {
            if ($feature === $payload) {
                $this->disabledFeaturePayloads[$payload->getName()] = $payload;
                $this->preparedDataSet = null;
                $this->paginator = null;
            } else {
                $newFeatures[] = $feature;
            }
        }
        $this->features = $newFeatures;
        $this->sortFeatures();

        return $this;
    }

    /**
     * @psalm-param Builder\FeaturePayload|class-string<Builder\FeatureInterface> $payload
     */
    public function enableFeature(Builder\FeaturePayload|string $payload): static
    {
        if (\is_string($payload)) {
            $item = $this->disabledFeaturePayloads[$payload] ?? null;
            if (!$item) {
                foreach ($this->disabledFeaturePayloads as $item) {
                    if ($item::class === $payload || is_subclass_of($item, $payload)) {
                        $payload = $item;
                        break;
                    }
                }
            } else {
                $payload = $item;
            }
        }
        if (!$payload instanceof Builder\FeaturePayload) {
            return $this;
        }
        foreach ($this->disabledFeaturePayloads as $item) {
            if ($item === $payload) {
                $this->registerFeature($payload->getName(), $payload->getClass(), $payload->getOptions(), $payload->getPriority());
                $this->preparedDataSet = null;
                $this->paginator = null;
            }
        }

        return $this;
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        if ($this->dataSet instanceof QueryBuilder) {
            return $this->dataSet->getEntityManager()->getConnection()->getDatabasePlatform();
        }

        return $this->dataSet->getConnection()->getDatabasePlatform();
    }

    public function load(): static
    {
        foreach ($this->features as $payload) {
            $payload->load($this);
        }

        return $this;
    }

    public function unload(): static
    {
        foreach ($this->features as $payload) {
            if ($payload->isLoaded()) {
                $payload->unload();
            }
        }

        return $this;
    }

    /**
     * @psalm-param array{
     *     queryExpression: QueryExpression|array|string|null,
     *     page: int|null,
     *     pageSize: int|null,
     *     ...
     * }|array<never, never> $params
     */
    public function prepare(array $params = []): static
    {
        if ($this->queryExpression) {
            $params['queryExpression'] = $params['queryExpression'] ?? $this->queryExpression;
        }

        if (null !== $this->page && null !== $this->itemsPerPage) {
            $params['page'] = $params['page'] ?? $this->page;
            $params['pageSize'] = $params['pageSize'] ?? $this->itemsPerPage;
        }

        foreach ($this->features as $payload) {
            $payload->assertLoaded(true);
            $feature = $payload->getFeature();
            if ($feature instanceof PaginationFeatureInterface && \count($this->paginatorOptions) > 0) {
                $feature->setOptions(array_replace($feature->getOptions(), $this->paginatorOptions));
            }
            if ($feature && $feature->prepare($params) && $feature->isBreaking()) {
                break;
            }
        }

        return $this;
    }

    public function apply(): static
    {
        foreach ($this->features as $payload) {
            $payload->assertLoaded(true);
            $feature = $payload->getFeature();
            if ($feature && !$feature->isApplied() && $feature->isPrepared()) {
                $feature->apply();
            }
        }

        $opt = $this->getOptions();
        $this->params = array_replace($this->params, $opt['builder']['params'] ?? []);
        $this->paramTypes = array_replace($this->paramTypes, $opt['builder']['paramTypes'] ?? []);

        return $this;
    }

    public function unapply(): static
    {
        foreach ($this->features as $payload) {
            $payload->assertLoaded(true);
            $payload->getFeature()?->unapply();
        }

        return $this;
    }

    public function getPage(): ?int
    {
        return $this->page;
    }

    public function setPage(?int $page): static
    {
        if ($this->page !== $page) {
            $this->page = $page;
            $this->paginator = null;
        }

        return $this;
    }

    public function getItemsPerPage(): ?int
    {
        return $this->itemsPerPage;
    }

    public function setItemsPerPage(?int $itemsPerPage): static
    {
        if ($this->itemsPerPage !== $itemsPerPage) {
            $this->itemsPerPage = $itemsPerPage;
            $this->paginator = null;
        }

        return $this;
    }

    /**
     * @psalm-return PaginatorOptions $paginatorOptions
     */
    public function getPaginatorOptions(): array
    {
        return $this->paginatorOptions;
    }

    /**
     * @psalm-param PaginatorOptions $paginatorOptions
     */
    public function setPaginatorOptions(array $paginatorOptions): static
    {
        $this->paginatorOptions = $paginatorOptions;

        return $this;
    }

    public function getPaginator(): ?PaginatorInterface
    {
        if ($this->paginator) {
            return $this->paginator;
        }

        if (null === $this->page || null === $this->itemsPerPage) {
            return null;
        }

        $query = $this->getQuery();

        $paginator = null;
        if ($query instanceof QueryBuilder || $query instanceof AbstractQuery) {
            $paginator = new Paginator($query, $this->paginatorOptions['fetchJoinCollection'] ?? true);
            if (\array_key_exists('useOutputWalkers', $this->paginatorOptions)) {
                $paginator->setUseOutputWalkers($this->paginatorOptions['useOutputWalkers']);
            }
            $paginator = new DoctrinePaginator($paginator);
        }
        if ($query instanceof AbstractRawQuery) {
            $paginator = new RawSqlPaginator($query);
        }
        if (!$paginator instanceof PaginatorInterface) {
            throw new \RuntimeException(\sprintf('Invalid paginator. Expected instance of "%s", but got "%s"', PaginatorInterface::class, \is_object($paginator) ? $paginator::class : \gettype($paginator)));
        }

        $this->paginator = $paginator;

        return $this->paginator;
    }

    public function getQueryExpression(): ?QueryExpression
    {
        return $this->queryExpression;
    }

    public function setQueryExpression(?QueryExpression $queryExpression): void
    {
        $this->queryExpression = $queryExpression;
    }

    public function build(): static
    {
        $this->preparedDataSet = clone $this->dataSet;

        $itemNormalizer = $this->options['query']['item_normalizer'] ?? null;

        if ($this->preparedDataSet instanceof AbstractRawQuery) {
            $this->queryParts->addTo($this->preparedDataSet->sql());
            if ($itemNormalizer) {
                $this->preparedDataSet->setOptions(array_replace($this->preparedDataSet->getOptions(), [
                    'item_normalizer' => $itemNormalizer,
                ]));
            }
        }
        if ($this->preparedDataSet instanceof QueryBuilder) {
            $this->queryParts->addTo($this->preparedDataSet);
        }

        if (null !== $this->page && null !== $this->itemsPerPage) {
            $firstResult = ($this->page - 1) * $this->itemsPerPage;
            $maxResults = $this->itemsPerPage;
        } else {
            $firstResult = null;
            $maxResults = null;
        }

        $this->preparedDataSet->setFirstResult($firstResult);
        $this->preparedDataSet->setMaxResults($maxResults);

        return $this;
    }

    public function reset(): static
    {
        $this->preparedDataSet = null;
        $this->query = null;
        $this->paginator = null;
        $this->queryParts->resetQueryParts();
        $this->params = [];
        $this->paramTypes = [];

        return $this;
    }

    public function isBuilt(): bool
    {
        return null !== $this->preparedDataSet;
    }

    public function isLoaded(): bool
    {
        $loaded = true;
        foreach ($this->features as $payload) {
            $loaded = $payload->isLoaded();
            if (!$loaded) {
                break;
            }
        }

        return $loaded;
    }

    public function getQuery(): AbstractRawQuery|AbstractQuery|QueryBuilder
    {
        if ($this->query) {
            return $this->query;
        }

        $this->apply();

        if (!$this->isBuilt()) {
            $this->build();
        }

        if ($this->preparedDataSet instanceof QueryBuilder) {
            $this->query = $this->preparedDataSet->getQuery();
        }

        if ($this->preparedDataSet instanceof AbstractRawQuery) {
            $this->query = $this->preparedDataSet;
        }

        $params = $this->getParameters();
        $types = $this->getParameterTypes();

        foreach ($params as $paramName => $paramValue) {
            $paramType = \array_key_exists($paramName, $types) ? $types[$paramName] : null;
            $this->query->setParameter($paramName, $paramValue, $paramType);
        }

        if ($this->query instanceof AbstractQuery) {
            $this->query->setHydrationMode($this->options['hydrator']);
        }
        if ($this->query instanceof RawNativeQuery) {
            $this->query->getNativeQuery()->setHydrationMode($this->options['hydrator']);
        }

        return $this->query;
    }

    public function setParameter(int|string $key, mixed $value, ?string $type = null): static
    {
        if (null !== $type) {
            $this->paramTypes[$key] = $type;
        }

        $this->params[$key] = $value;

        return $this;
    }

    public function setParameters(array $params, array $types = []): static
    {
        $this->paramTypes = $types;
        $this->params = $params;

        return $this;
    }

    public function getParameters(): array
    {
        return $this->params;
    }

    public function getParameter(mixed $key): mixed
    {
        return $this->params[$key] ?? null;
    }

    public function getParameterTypes(): array
    {
        return $this->paramTypes;
    }

    public function getParameterType(mixed $key): mixed
    {
        return $this->paramTypes[$key] ?? null;
    }

    public function __clone(): void
    {
        $this->reset();
        $this->loadFeatures($this->options['features']);
    }
}
