<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Components\UI\DataGrid;

use App\Utils\Domain\ReadModel\ReadDataProviderInterface;

/**
 * @psalm-type GridBatchAction = array{
 *     id: string,
 *     label: string,
 *     url: string
 * }
 * @psalm-type GridBatchActions = array<array-key, GridBatchAction>
 * @psalm-type GridRowAction = array{
 *     url: string,
 *     label?: string,
 *     title?: string,
 *     type?: string,
 *     icon?: string,
 *     visible?: bool,
 *     confirm?: string,
 * }
 * @psalm-type GridRowActions = array<array-key, GridRowAction>
 */
class Grid
{
    /**
     * @var Column[]
     */
    private array $visibleColumns;
    /**
     * @var callable|null
     */
    private mixed $rowAttributesCallback;

    /**
     * @param Column[]                                  $columns
     * @param array<array-key, GridRowActions|callable> $rowActions
     * @param GridBatchActions                          $batchActions
     */
    public function __construct(
        private readonly array $columns,
        private ?ReadDataProviderInterface $dataSource,
        private readonly string $theme,
        private readonly array $rowActions = [],
        private readonly array $batchActions = [],
        private readonly string $batchMethod = 'POST',
        ?callable $rowAttributesCallback = null,
    ) {
        $this->visibleColumns = array_filter($columns, fn (Column $column) => $column->visible);
        $this->rowAttributesCallback = $rowAttributesCallback;
    }

    /**
     * @psalm-suppress MixedReturnStatement
     */
    public function column(string $field): ?Column
    {
        return array_find($this->columns, fn (Column $col) => $col->field === $field);
    }

    /**
     * @return Column[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * @return Column[]
     */
    public function getVisibleColumns(): array
    {
        return $this->visibleColumns;
    }

    public function setDataSource(?ReadDataProviderInterface $dataSource): void
    {
        $this->dataSource = $dataSource;
    }

    public function getDataSource(): ?ReadDataProviderInterface
    {
        return $this->dataSource;
    }

    public function isFilterEnabled(): bool
    {
        return array_any($this->visibleColumns, fn ($column) => $column->filterable);
    }

    /**
     * @return GridBatchActions
     */
    public function getBatchActions(): array
    {
        return $this->batchActions;
    }

    public function hasBatchActions(): bool
    {
        return \count($this->batchActions) > 0;
    }

    public function getBatchMethod(): string
    {
        return $this->batchMethod;
    }

    public function getBatchActionsTokenId(): string
    {
        $json = json_encode($this->getBatchActions());

        return false === $json ? '' : $json;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * @return array<array-key, mixed>|string|null
     *
     * @phpstan-ignore missingType.iterableValue
     */
    public function getRowAttributes(array|object $item, bool $keepAsArray = false): array|string|null
    {
        if (!\is_callable($this->rowAttributesCallback)) {
            return null;
        }

        $callback = $this->rowAttributesCallback;
        $attributes = $callback($item);

        if (!\is_array($attributes)) {
            return null;
        }

        if ($keepAsArray) {
            return $attributes;
        }

        return $this->attributesToHtml($attributes);
    }

    public function getRowActionsGroupCount(): int
    {
        return \count($this->rowActions);
    }

    /**
     * @return GridRowActions
     *
     * @psalm-suppress InvalidReturnType,InvalidReturnStatement
     *
     * @phpstan-ignore missingType.iterableValue
     */
    public function getRowActions(object|array $row): array
    {
        return array_map(fn ($item) => \is_callable($item) ? \call_user_func($item, $row) : $item, $this->rowActions);
    }

    /**
     * @psalm-suppress MixedReturnStatement
     *
     * @phpstan-ignore missingType.iterableValue
     */
    private function attributesToHtml(array $attributes): string
    {
        return array_reduce(
            array_keys($attributes),
            function (string $carry, $key) use ($attributes) {
                $key = \is_string($key) ? $key : (string) $key;
                $value = $attributes[$key];

                if (!\is_scalar($value) && null !== $value) {
                    throw new \LogicException(\sprintf('The value of "%s" is expected to ba a scalar, but %s was given.', $key, get_debug_type($value)));
                }

                if (null === $value) {
                    throw new \LogicException('Passing "null" as an attribute value is forbidden');
                }

                return match ($value) {
                    true => "$carry $key",
                    false => $carry,
                    default => \sprintf('%s %s="%s"', $carry, $key, (string) $value),
                };
            },
            ''
        );
    }
}
