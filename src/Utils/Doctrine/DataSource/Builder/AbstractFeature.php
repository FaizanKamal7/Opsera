<?php

declare(strict_types=1);

namespace App\Utils\Doctrine\DataSource\Builder;

use App\Utils\Doctrine\AbstractRawQuery;
use App\Utils\Doctrine\DataSource\Builder;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\QueryBuilder;

abstract class AbstractFeature implements FeatureInterface
{
    private Builder $builder;
    private array $options;
    private bool $prepared = false;
    private bool $applicable = false;
    private bool $applied = false;

    public function __construct(Builder $builder, array $options = [])
    {
        $this->builder = $builder;
        $this->setOptions($options);
    }

    public function getBuilder(): Builder
    {
        return $this->builder;
    }

    public function getDefaultOptions(): array
    {
        return [];
    }

    public function setOptions(array $options): static
    {
        $opt = $this->builder->getOptions();
        $options = array_replace([
            'field_map' => isset($opt['field_map']) && \is_array($opt['field_map']) ? $opt['field_map'] : [],
        ], $options);

        $options = array_replace($this->getDefaultOptions(), $options);
        $this->options = $options;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Determines weather the feature must break the prepare procedure and the following features gets skipped.
     */
    public function isBreaking(): bool
    {
        return false;
    }

    /**
     * Check if the feature is prepared.
     */
    final public function isPrepared(): bool
    {
        return $this->prepared;
    }

    /**
     * Check if the feature is applied.
     */
    final public function isApplied(): bool
    {
        return $this->applied;
    }

    /**
     * Check if the feature can be applied.
     */
    final public function canApply(): bool
    {
        return $this->applicable;
    }

    /** @noinspection PhpUnhandledExceptionInspection */
    final protected function assertPrepared(mixed $state): void
    {
        if ($this->isPrepared() !== $state) {
            throw new Exception\AssertFeaturePreparedStateException(\sprintf('The dataset feature "%s" is in unexpected prepared state "%s"', static::class, $state ? 'FALSE' : 'TRUE'));
        }
    }

    /**
     * Assert applied state.
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    final protected function assertApplied(mixed $state): void
    {
        if ($this->isApplied() !== $state) {
            throw new Exception\AssertFeatureAppliedStateException(\sprintf('The dataset feature "%s" is in unexpected applied state "%s"', static::class, $state ? 'FALSE' : 'TRUE'));
        }
    }

    /**
     * Prepare the feature with given parameters.
     *
     * If this method returns false this means that the feature can not be applied with these parameters
     */
    final public function prepare(array $params): bool
    {
        $this->unprepare();
        $this->applicable = $this->doPrepare($params);
        $this->prepared = true;

        if ($this->applicable) {
            $this->builder->reset();
        }

        return $this->applicable;
    }

    /** @noinspection SpellCheckingInspection */
    final public function unprepare(): static
    {
        if ($this->isApplied()) {
            $this->unapply();
        }

        if ($this->isPrepared()) {
            $this->doUnprepare();
        }

        $this->applicable = false;
        $this->prepared = false;

        return $this;
    }

    /**
     * Apply the feature. Note that the feature must be prepared and not already applied.
     *
     * @noinspection PhpUnhandledExceptionInspection
     */
    final public function apply(): void
    {
        $this->assertPrepared(true);

        if ($this->canApply()) {
            $this->assertApplied(false);
            $this->doApply();
            $this->applied = true;
        }
    }

    final public function unapply(): void
    {
        $this->doUnApply();
        $this->applied = false;
    }

    abstract protected function doPrepare(array $params): bool;

    /** @noinspection SpellCheckingInspection */
    abstract protected function doUnprepare(): void;

    abstract protected function doApply(): void;

    abstract protected function doUnApply(): void;

    protected function getDataSet(): AbstractRawQuery|QueryBuilder
    {
        return $this->builder->getDataSet();
    }

    protected function isQueryBuilder(): bool
    {
        return $this->getDataSet() instanceof QueryBuilder;
    }

    protected function getRootAlias(): string
    {
        $opt = $this->builder->getOptions();
        $rootAlias = $opt['root_alias'] ?? null;
        if (!\is_string($rootAlias)) {
            $rootAlias = null;
        }

        if (empty($rootAlias) && $this->isQueryBuilder()) {
            $rootAlias = $this->getDataSet()->getRootAliases()[0] ?? null;
        }

        return $rootAlias ?? '';
    }

    protected function getRootIdentifier(): string
    {
        $opt = $this->builder->getOptions();
        $rootIdentifier = $opt['root_identifier'] ?? null;
        if (\is_string($rootIdentifier)) {
            $rootIdentifier = [$rootIdentifier];
        }

        if (!\is_array($rootIdentifier) && $this->isQueryBuilder()) {
            $rootEntity = $this->getDataSet()->getRootEntities();
            $rootEntity = reset($rootEntity);
            $rootMetaData = $this->getDataSet()->getEntityManager()->getClassMetadata($rootEntity);
            $rootIdentifier = $rootMetaData->getIdentifierFieldNames();
        }

        if (!\is_array($rootIdentifier)) {
            throw new \RuntimeException('Can not determine the root identifier. You must specify "root_identifier" in the datasource options.');
        }

        if (\count($rootIdentifier) > 1) {
            throw new \RuntimeException('The datasource does not support composite root identifiers.');
        }

        $rootIdentifier = reset($rootIdentifier);

        if (str_contains($rootIdentifier, '.')) {
            throw new \RuntimeException('The "root_identifier" option must not contain "." symbol. Please use "root_alias" to specify the alias of the table which holds the identifier column!');
        }

        return $rootIdentifier;
    }

    protected function quote(string $str): string
    {
        $opt = $this->getOptions();
        $quoteChar = $opt['quoteFieldNamesChar'] ?? '"';
        if (!\is_string($quoteChar) || 1 !== mb_strlen($quoteChar)) {
            return $str;
        }

        return $quoteChar.str_replace($quoteChar, $quoteChar.$quoteChar, $str).$quoteChar;
    }

    protected function unquote(string $str): string
    {
        $opt = $this->getOptions();
        $quoteChar = $opt['quoteFieldNamesChar'] ?? '"';
        if (!\is_string($quoteChar) || 1 !== mb_strlen($quoteChar) || !$this->isQuoted($str)) {
            return $str;
        }

        return str_replace($quoteChar, '', $str);
    }

    protected function isQuoted(string $str): bool
    {
        $opt = $this->getOptions();
        $quoteChar = $opt['quoteFieldNamesChar'] ?? '"';
        if (!\is_string($quoteChar) || 1 !== mb_strlen($quoteChar)) {
            return false;
        }
        $s = mb_trim($str);

        return str_starts_with($s, $quoteChar) && false !== mb_strrpos($s, $quoteChar, -1);
    }

    protected function mapField(string $field): string
    {
        $opt = $this->getOptions();

        $rootAlias = $this->getRootAlias();

        if (isset($opt['field_map']) && \is_array($opt['field_map']) && \array_key_exists($field, $opt['field_map'])) {
            $field = $opt['field_map'][$field];
        }

        $quoteTableAlias = isset($opt['quoteTableAlias']) && $opt['quoteTableAlias'];
        $quoteFieldNames = isset($opt['quoteFieldNames']) && $opt['quoteFieldNames'];
        $quoteFieldNamesAlways = isset($opt['quoteFieldNamesAlways']) && $opt['quoteFieldNamesAlways'];

        if ($quoteFieldNamesAlways || !str_contains($field, '.')) {
            if ($quoteFieldNames) {
                $field = $this->quote($field);
            }
            if (!empty($rootAlias)) {
                $field = ($quoteTableAlias ? $this->quote($rootAlias) : $rootAlias).'.'.$field;
            }
        }

        if ($quoteTableAlias && str_contains($field, '.') && !$this->isQuoted($field)) {
            $fieldParts = explode('.', $field);
            $tableAlias = array_shift($fieldParts);
            if (!$this->isQuoted($tableAlias)) {
                $tableAlias = $this->quote($tableAlias);
            }
            array_unshift($fieldParts, $tableAlias);
            $field = implode('.', $fieldParts);
        }

        return $field;
    }

    protected function getDatabasePlatform(): ?AbstractPlatform
    {
        return $this->builder->getDatabasePlatform();
    }

    public static function load(array $params, Builder $builder, array $options = []): ?FeatureInterface
    {
        $instance = new static($builder, $options);

        return $instance->prepare($params) ? $instance : null;
    }
}
