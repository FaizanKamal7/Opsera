<?php

declare(strict_types=1);

namespace App\Utils\Doctrine\DataSource\Builder\Feature;

use App\Utils\Doctrine\DataSource\Builder\AbstractFeature;
use App\Utils\Doctrine\DataSource\Builder\QueryExpressionFeatureInterface;
use App\Utils\Domain\ReadModel\FilterExpression;
use App\Utils\Domain\ReadModel\QueryExpression;
use App\Utils\Domain\ReadModel\SortExpression;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\ORM\Query\Expr\Andx;
use Doctrine\ORM\Query\Expr\Comparison;
use Doctrine\ORM\Query\Expr\Orx;
use Webmozart\Assert\Assert;

class QueryExpressionFeature extends AbstractFeature implements QueryExpressionFeatureInterface
{
    /** @noinspection SpellCheckingInspection */
    private static array $operatorsNoValue = [
        'isnull',
        'isnotnull',
        'isempty',
        'isnullorempty',
        'isnotempty',
        'isnotnullorempty',
    ];

    private ?FilterExpression $filter = null;
    private ?SortExpression $sort = null;

    public function getDefaultOptions(): array
    {
        return [
            'groups' => [],
            'expressions' => [],
        ];
    }

    protected function doPrepare(array $params): bool
    {
        $filter = null;
        $sort = null;
        $queryExpression = null;

        $defaults = compact([
            'filter',
            'sort',
            'queryExpression',
        ]);

        $params = array_replace($defaults, array_intersect_key($params, $defaults));
        extract($params);

        if (\is_string($queryExpression) || \is_array($queryExpression)) {
            $queryExpression = QueryExpression::create($queryExpression);
        }
        if ($queryExpression) {
            Assert::isInstanceOf($queryExpression, QueryExpression::class);
            $this->filter = $queryExpression->getFilter();
            $this->sort = $queryExpression->getSort();
        } else {
            if (\is_string($filter) || \is_array($filter)) {
                $this->filter = FilterExpression::create($filter);
            }
            if (\is_string($sort) || \is_array($sort)) {
                $this->sort = SortExpression::create($sort);
            }
        }

        $opt = $this->getOptions();
        $filterOpt = $this->getBuilder()->getOptions();
        $filterOpt = isset($filterOpt['filter']) && \is_array($filterOpt['filter']) ? $filterOpt['filter'] : [];

        if (isset($filterOpt['expressions']) && \is_array($filterOpt['expressions'])) {
            $opt['expressions'] = array_replace($filterOpt['expressions'], $opt['expressions']);
            $this->setOptions($opt);
        }

        return ($this->filter && !$this->filter->isFilterEmpty())
            || ($this->sort && !$this->sort->isSortEmpty());
    }

    protected function doUnprepare(): void
    {
        $this->filter = null;
        $this->sort = null;
    }

    protected function doApply(): void
    {
        $this->applyFilter();
        $this->applySort();
    }

    private function applyFilter(): void
    {
        if (null === $this->filter) {
            return;
        }

        $params = [];
        $where = $this->filter($this->filter, $params);

        if ($where) {
            $builder = $this->getBuilder();
            $builder->parts()->andWhere($where);
            foreach ($params as $paramName => $paramValue) {
                $builder->setParameter($paramName, $paramValue);
            }
        }
    }

    private function applySort(): void
    {
        if (null === $this->sort) {
            return;
        }

        $parts = $this->getBuilder()->parts();

        $opt = $this->getOptions();
        $sort = $this->sort->sort();
        if (array_values($sort) !== $sort) {
            $sort = [$sort];
        }
        foreach ($sort as $entry) {
            $field = $entry['field'] ?? null;
            if (null === $field || '' === $field) {
                throw new \RuntimeException('The sort rule must specify a field');
            }
            if (\array_key_exists($field, $opt['expressions'])) {
                $field = $opt['expressions'][$field]['exp'] ?? $field;
            } else {
                $field = $this->mapField($field);
            }
            $dir = $entry['dir'] ?? 'ASC';
            if (!\in_array(mb_strtoupper($dir), ['ASC', 'DESC'], true)) {
                throw new \RuntimeException(\sprintf('Invalid sort direction: "%s"', $dir));
            }
            $parts->addOrderBy($field, $dir);
        }
    }

    protected function doUnApply(): void
    {
        $this->getBuilder()->setQueryExpression(null);
    }

    private function createFilterExpression(FilterExpression|array $filter, array &$params): string|Comparison|Andx|Orx
    {
        if ($filter instanceof FilterExpression) {
            $filter = $filter->toArray();
        }

        static $paramId = 0;
        $opt = $this->getOptions();

        $ignoreCaseDefault = !isset($filter['ignoreCase']);
        $ignoreCase = !isset($filter['ignoreCase']) || (bool) $filter['ignoreCase'];

        $expr = $this->getBuilder()->parts()->expr();

        if (!isset($filter['field'])) {
            throw new \RuntimeException('Missing filter filed');
        }

        $field = $this->mapField($filter['field']);

        if (!isset($filter['operator'])) {
            throw new \RuntimeException('Missing filter operator');
        }
        $operator = mb_strtolower($filter['operator']);

        $paramName = 'pvalue'.$paramId++;
        $paramValue = null;
        $paramValueUpper = null;

        if (!\in_array($operator, static::$operatorsNoValue, true)) {
            if (!isset($filter['value'])) {
                throw new \RuntimeException('Missing filter value');
            }
            $paramValue = $filter['value'];
            $paramValueUpper = $ignoreCase ? mb_strtoupper((string) $paramValue, 'UTF-8') : $paramValue;
        }

        $fieldEx = $field;
        if (\array_key_exists($field, $opt['expressions'])) {
            $fieldEx = $opt['expressions'][$field]['exp'] ?? $field;
        }

        switch ($operator) {
            case 'eq':
                if (!$ignoreCaseDefault && $ignoreCase) {
                    $params[$paramName] = $paramValueUpper;

                    return $expr->eq($expr->upper($fieldEx), ':'.$paramName);
                }
                $params[$paramName] = $paramValue;

                return $expr->eq($fieldEx, ':'.$paramName);
            case 'neq':
                if (!$ignoreCaseDefault && $ignoreCase) {
                    $params[$paramName] = $paramValueUpper;

                    return $expr->neq($expr->upper($fieldEx), ':'.$paramName);
                }
                $params[$paramName] = $paramValue;

                return $expr->neq($fieldEx, ':'.$paramName);
            case 'isnull':
                return $expr->isNull($fieldEx);
            case 'isnotnull':
                return $expr->isNotNull($fieldEx);
            case 'lt':
                $params[$paramName] = $paramValue;

                return $expr->lt($fieldEx, ':'.$paramName);
            case 'lte':
                $params[$paramName] = $paramValue;

                return $expr->lte($fieldEx, ':'.$paramName);
            case 'gt':
                $params[$paramName] = $paramValue;

                return $expr->gt($fieldEx, ':'.$paramName);
            case 'gte':
                $params[$paramName] = $paramValue;

                return $expr->gte($fieldEx, ':'.$paramName);
            case 'startswith':
                if ($ignoreCase) {
                    $params[$paramName] = $paramValueUpper.'%';

                    return $expr->like((string) $expr->upper($fieldEx), ':'.$paramName);
                }
                $params[$paramName] = $paramValue.'%';

                return $expr->like($fieldEx, ':'.$paramName);

            case 'notstartswith':
                if ($ignoreCase) {
                    $params[$paramName] = $paramValueUpper.'%';

                    return $expr->notLike((string) $expr->upper($fieldEx), ':'.$paramName);
                }
                $params[$paramName] = $paramValue.'%';

                return $expr->notLike($fieldEx, ':'.$paramName);

            case 'endswith':
                if ($ignoreCase) {
                    $params[$paramName] = '%'.$paramValueUpper;

                    return $expr->like((string) $expr->upper($fieldEx), ':'.$paramName);
                }
                $params[$paramName] = '%'.$paramValue;

                return $expr->like($fieldEx, ':'.$paramName);

            case 'contains':
                if ($ignoreCase) {
                    $params[$paramName] = '%'.$paramValueUpper.'%';

                    return $expr->like((string) $expr->upper($fieldEx), ':'.$paramName);
                }
                $params[$paramName] = '%'.$paramValue.'%';

                return $expr->like($fieldEx, ':'.$paramName);

            case 'doesnotcontain':
                if ($ignoreCase) {
                    $params[$paramName] = '%'.$paramValueUpper.'%';

                    return $expr->notLike((string) $expr->upper($fieldEx), ':'.$paramName);
                }
                $params[$paramName] = '%'.$paramValue.'%';

                return $expr->notLike($fieldEx, ':'.$paramName);

            case 'isempty':
            case 'isnullorempty':
                $databasePlatform = $this->getDatabasePlatform();
                if ($databasePlatform instanceof OraclePlatform) {
                    return $expr->isNull($fieldEx);
                }

                return $expr->orX($expr->isNull($fieldEx), $expr->eq($fieldEx, $expr->literal('')));

            case 'isnotempty':
            case 'isnotnullorempty':
                $databasePlatform = $this->getDatabasePlatform();
                if ($databasePlatform instanceof OraclePlatform) {
                    return $expr->isNotNull($fieldEx);
                }

                return $expr->andX($expr->isNotNull($fieldEx), $expr->neq($fieldEx, $expr->literal('')));

            default:
                throw new \RuntimeException(\sprintf('Unsupported filter operator: "%s"', $operator));
        }
    }

    public function filter(FilterExpression|array $filter, array &$params, $useFilterGroups = true): Orx|Andx|null
    {
        if ($filter instanceof FilterExpression) {
            $filter = $filter->toArray();
        }

        $opt = $this->getOptions();

        $logic = $filter['logic'] ?? 'and';

        $expr = $this->getBuilder()->parts()->expr();

        $where = match ($logic) {
            'and' => $expr->andX(),
            'or' => $expr->orX(),
            default => throw new \RuntimeException(\sprintf('Unsupported filter logic: "%s"', $logic)),
        };

        $filters = isset($filter['filters']) && \is_array($filter['filters']) ? $filter['filters'] : [];

        if (isset($filter['field'])) {
            $filterGroups = [];
            if (!isset($filter['grouping']) || \in_array(mb_strtoupper((string) $filter['grouping']), ['1', 'TRUE'], true)) {
                // Determine if the filter field is part of a filter group
                if (\array_key_exists($filter['field'], $opt['groups'])) {
                    $filterGroups = [$opt['groups'][$filter['field']]];
                } else {
                    $filterGroups = array_filter($opt['groups'], function ($group) use ($filter) {
                        return \is_array($group)
                            && isset($group['fields'])
                            && \is_array($group['fields'])
                            && \in_array($filter['field'], $group['fields'], true)
                        ;
                    });
                }
            }
            if ($useFilterGroups && \count($filterGroups) > 0) {
                // The field is part of filter group, so modify the current filter with new one
                $newFilter = [
                    'logic' => 'or', // When field is part of more than one group - any matching group must return result
                    'filters' => [],
                ];
                foreach ($filterGroups as $group) {
                    $group = array_replace_recursive([
                        'logic' => 'or',    // default logic operator used for the group
                        'fields' => [],      // group fields
                        'filter' => $filter, // default filter config (initially - original as is)
                        'filters' => [],      // filter config by field (overrides default filter config)
                    ], $group);
                    if (\count($group['fields']) < 1) {
                        continue;
                    }
                    $groupFilter = ['logic' => $group['logic'], 'filters' => []];
                    foreach ($group['fields'] as $groupField) {
                        $groupFilter['filters'][] = array_replace_recursive(
                            $group['filter'],
                            \array_key_exists($groupField, $group['filters']) ? $group['filters'][$groupField] : [],
                            ['field' => $groupField]
                        );
                    }
                    $newFilter['filters'][] = $groupFilter;
                }
                if (\count($newFilter['filters']) > 0) {
                    $useFilterGroups = false;
                    // Keep current filters constraints
                    $filters = [
                        [
                            'logic' => 'and',
                            'filters' => [
                                $newFilter,
                                ['logic' => $logic, 'filters' => $filters],
                            ],
                        ],
                    ];
                    $where = $expr->andX();
                }
            } else {
                $filterExpression = $this->createFilterExpression($filter, $params);
                $where->add($filterExpression);
            }
        }

        foreach ($filters as $nestedFilter) {
            $nestedExpr = $this->filter($nestedFilter, $params, $useFilterGroups);
            if ($nestedExpr) {
                $where->add($nestedExpr);
            }
        }

        return $where->count() > 0 ? $where : null;
    }
}
