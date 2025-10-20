<?php

declare(strict_types=1);

namespace App\Twig\Components\UI;

use App\Components\UI\DataGrid\Grid;
use App\Components\UI\DataGrid\GridBuilder;
use App\Utils\Domain\ReadModel\FilterExpression;
use App\Utils\Domain\ReadModel\QueryExpression;
use App\Utils\Domain\ReadModel\ReadDataProviderInterface;
use App\Utils\Domain\ReadModel\SortExpression;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\Service\Attribute\Required;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;
use Symfony\UX\TwigComponent\Attribute\PostMount;
use Webmozart\Assert\Assert;

trait DataGridTrait
{
    use DefaultActionTrait;

    private RouterInterface $router;
    private TranslatorInterface $translator;
    private GridBuilder $gridBuilder;

    #[ExposeInTemplate]
    public ?Grid $grid = null;

    #[LiveProp(writable: true, hydrateWith: 'hydrateQuery', dehydrateWith: 'dehydrateQuery')]
    public ?QueryExpression $query = null;

    /**
     * @var array<string, string>|null
     */
    #[LiveProp(writable: true)]
    public ?array $columnFilterOperator = null;

    /**
     * @var array<string, float|int|string|null>|null
     */
    #[LiveProp(writable: true)]
    public ?array $columnFilterValue = null;

    #[LiveProp(writable: true)]
    public ?int $currentPage = 1;

    #[LiveProp(writable: true)]
    public ?int $itemsPerPage = 25;

    private ?string $uid = null;

    private ?QueryExpression $modifiedQuery = null;

    #[LiveAction]
    public function columnToggleSort(#[LiveArg] string $field, #[LiveArg] bool $append = false): void
    {
        $query = $this->query ?? QueryExpression::create();

        $dir = $query->sortDir($field);
        if (!$append) {
            $query = $query->resetSort();
        }
        $query = match ($dir) {
            'asc', 'ASC' => $query->sortBy($field, SortExpression::DIR_DESC),
            'desc', 'DESC' => $query->resetSort($field),
            default => $query->sortBy($field),
        };

        $this->assignQueryExpression($query);
    }

    #[LiveAction]
    public function columnResetFilter(#[LiveArg] ?string $field = null): void
    {
        if (null !== $field) {
            $this->columnFilterValue ??= [];
            $this->columnFilterValue[$field] = '';
            $operator = $this->columnFilterOperator[$field] ?? '';
            if (!$operator || !FilterExpression::operatorRequiresValue($operator)) {
                $this->columnFilterOperator[$field] = $this->grid?->column($field)?->getDefaultFilterOperator() ?? FilterExpression::OP_EQ;
            }
        }
        if (null !== $this->query) {
            $this->assignQueryExpression($this->query->resetFilter($field));
        }
    }

    #[PostMount]
    #[PostHydrate]
    public function setup(): void
    {
        Assert::isInstanceOf($this, DataGridInterface::class, \sprintf('The "%s" must implement "%s"', __CLASS__, DataGridInterface::class));

        $query = $this->query;

        if (null === $query) {
            $query = $this->setupDefaultQuery(QueryExpression::create());
        }

        $this->modifiedQuery = $this->modifyQuery(QueryExpression::create());

        $this->grid ??= $this->buildGrid($this->gridBuilder);

        $dataSource = $this->getDataSource();
        if ($this->itemsPerPage > 0) {
            $dataSource = $dataSource?->withPagination($this->currentPage ?? 1, $this->itemsPerPage);
        } else {
            $dataSource = $dataSource?->withoutPagination();
        }
        $this->setDataSource($dataSource);

        $columnFilters = [];
        $resetFilters = [];
        $expr = $query->expr();

        $this->columnFilterOperator ??= [];
        $this->columnFilterValue ??= [];
        foreach ($this->grid->getColumns() as $column) {
            $field = $column->field;
            $fieldFilters = $query->fieldFilters($field);
            if (\count($fieldFilters) > 1) {
                continue;
            }
            $fieldOperator = $this->columnFilterOperator[$field] ?? null;
            if (null === $fieldOperator) {
                $fieldOperator = array_column($fieldFilters, 'operator')[0] ?? null;
                if (null !== $fieldOperator) {
                    $this->columnFilterOperator[$field] = $fieldOperator;
                }
            }
            $operator = $this->columnFilterOperator[$field] ??= $column->getDefaultFilterOperator();
            $isValueRequired = FilterExpression::operatorRequiresValue($operator);
            /** @var float|int|string|null $fieldValue */
            $fieldValue = $this->columnFilterValue[$field] ?? null;
            if (null === $fieldValue) {
                /** @var float|int|string|null $fieldValue */
                $fieldValue = array_column($fieldFilters, 'value')[0] ?? null;
                if ($isValueRequired && null !== $fieldValue) {
                    $this->columnFilterValue[$field] = $fieldValue;
                }
            }
            $value = $this->columnFilterValue[$field] ??= '';
            if ('' === $value && $isValueRequired) {
                $resetFilters[] = $field;
            }
            if ($field && $operator && (!$isValueRequired || ('' !== $value))) {
                $ignoreCase = (bool) $column->getParam('ignore_case', true);
                $columnFilters[] = $expr->valX($field, $operator, $value, $ignoreCase);
            }
        }

        foreach ($resetFilters as $field) {
            $query = $query->resetFilter($field);
        }

        if (\count($columnFilters) > 0) {
            $query = $query->resetFilter()->andWhere(...$columnFilters)->compactFilter();
        }

        $this->assignQueryExpression($query);
    }

    private function getDataSource(): ?ReadDataProviderInterface
    {
        return $this->grid?->getDataSource();
    }

    private function setDataSource(?ReadDataProviderInterface $dataSource): void
    {
        if ($dataSource !== $this->getDataSource()) {
            $this->grid?->setDataSource($dataSource);
        }
    }

    private function assignQueryExpression(?QueryExpression $queryExpression): void
    {
        if ($this->query !== $queryExpression) {
            $this->query = $queryExpression;
            if (null !== $this->modifiedQuery) {
                if (null === $queryExpression) {
                    $queryExpression = $this->modifiedQuery;
                } else {
                    $queryExpression = $queryExpression->wrap($this->modifiedQuery);
                }
            }
            if (null !== $queryExpression) {
                $this->grid?->setDataSource($this->getDataSource()?->withQueryExpression($queryExpression));
            } else {
                $this->grid?->setDataSource($this->getDataSource()?->withoutQueryExpression());
            }
        }
    }

    public function getUid(): string
    {
        return $this->uid ??= str_replace('.', '', uniqid('datagrid_', true));
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function dehydrateQuery(): ?array
    {
        return $this->query?->toArray();
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function hydrateQuery(string|array|null $data): QueryExpression
    {
        return QueryExpression::create($data);
    }

    #[Required]
    public function setRouter(RouterInterface $router): void
    {
        $this->router = $router;
    }

    #[Required]
    public function setTranslator(TranslatorInterface $translator): void
    {
        $this->translator = $translator;
    }

    #[Required]
    public function setGridBuilder(GridBuilder $gridBuilder): void
    {
        $this->gridBuilder = $gridBuilder;
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    protected function generateUrl(string $route, array $parameters = [], int $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH): string
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    protected function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        return $this->translator->trans($id, $parameters, $domain, $locale);
    }
}
