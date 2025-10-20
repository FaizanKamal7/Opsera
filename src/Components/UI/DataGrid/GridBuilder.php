<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Components\UI\DataGrid;

use App\Utils\Domain\ReadModel\ReadDataProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @psalm-import-type GridBatchActions from Grid
 * @psalm-import-type GridRowActions from Grid
 * @psalm-import-type GridRowAction from Grid
 * @psalm-import-type ColumnParams from Column
 * @psalm-import-type ColumnValues from Column
 */
#[Autoconfigure(shared: false)]
class GridBuilder
{
    private TranslatorInterface $translator;
    private ?ReadDataProviderInterface $dataSource = null;

    /**
     * @var callable|null
     */
    private mixed $rowAttributesCallback = null;

    /**
     * @var Column[]
     */
    private array $columns = [];

    /**
     * @var array<array-key, GridRowActions|callable>
     */
    private array $rowActions = [];

    /**
     * @var GridBatchActions
     */
    private array $batchActions = [];
    private string $batchMethod = 'POST';
    private string $theme = 'components/UI/DataGrid/theme/bootstrap5';

    public function withData(ReadDataProviderInterface $dataSource): self
    {
        $clone = clone $this;
        $clone->dataSource = $dataSource;

        return $clone;
    }

    public function withTheme(string $theme): self
    {
        $clone = clone $this;
        $clone->theme = $theme;

        return $clone;
    }

    public function withBatchMethod(string $method): self
    {
        $clone = clone $this;
        $clone->batchMethod = $method;

        return $clone;
    }

    public function withRowAttributesCallback(callable $callback): self
    {
        $clone = clone $this;
        $clone->rowAttributesCallback = $callback;

        return $clone;
    }

    /**
     * @param ColumnParams $params
     * @param ColumnValues $values
     */
    public function withColumn(
        string $name,
        string $field,
        string $type,
        ?int $width = null,
        mixed $template = ColumnTemplate::AUTO,
        bool $sortable = true,
        bool $filterable = true,
        bool $visible = true,
        array $params = [],
        array $values = [],
    ): self {
        if (0 === \count($values)) {
            if (ColumnType::BOOLEAN === $type) {
                $values = [
                    0 => $this->trans('ui.datagrid.column.boolean.false'),
                    1 => $this->trans('ui.datagrid.column.boolean.true'),
                ];
            }
        }

        if (\is_callable($template)) {
            $params['escape'] ??= false; // will be handled by Column template wrapper
        }

        if (\count($values) > 0) {
            $params['hide_column_filter_operator'] ??= true;
        }

        $clone = clone $this;
        $clone->columns[] = new Column(
            $name,
            $field,
            $type,
            $width,
            $sortable,
            $filterable,
            $visible,
            $params,
            $values,
            $template,
        );

        return $clone;
    }

    /**
     * @param GridRowActions|callable $actions
     */
    public function withRowActions(array|callable $actions): self
    {
        $clone = clone $this;
        $clone->rowActions[] = $actions;

        return $clone;
    }

    /**
     * @return GridRowAction
     */
    public function editRowAction(string $url): array
    {
        return [
            'url' => $url,
            'label' => $this->trans('app.actions.edit.imp'),
            'icon' => 'fas fa-pencil-alt',
        ];
    }

    /**
     * @return GridRowAction
     */
    public function removeRowAction(string $url): array
    {
        return [
            'url' => $url,
            'icon' => 'far fa-trash-alt',
            'type' => 'danger',
            'confirm' => $this->trans('ui.datagrid.row.actions.remove.confirm'),
        ];
    }

    public function withBatchAction(string $id, string $label, string $url): self
    {
        $clone = clone $this;
        $clone->batchActions[] = [
            'id' => $id,
            'label' => $label,
            'url' => $url,
        ];

        return $clone;
    }

    public function create(): Grid
    {
        return new Grid(
            $this->columns,
            $this->dataSource,
            $this->theme,
            $this->rowActions,
            $this->batchActions,
            $this->batchMethod,
            $this->rowAttributesCallback
        );
    }

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    protected function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }
}
