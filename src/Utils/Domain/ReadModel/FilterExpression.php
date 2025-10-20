<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Utils\Domain\ReadModel;

/**
 * @psalm-type FilterItem = array{
 *     field: string,
 *     operator: string,
 *     value?: mixed,
 *     ignoreCase?: bool,
 * }
 * @psalm-type FilterOperatorDescription = array{
 *     name: string,
 *     operator: string,
 *     value_required: bool,
 *     expression: string,
 * }
 */
class FilterExpression implements \JsonSerializable, \Stringable
{
    private const string LOGIC_AND = 'and';
    private const string LOGIC_OR = 'or';

    public const string OP_EQ = 'eq'; // (equal to)
    public const string OP_NEQ = 'neq'; // (not equal to)
    public const string OP_IS_NULL = 'isnull'; // (is equal to null)
    public const string OP_IS_NOT_NULL = 'isnotnull'; // (is not equal to null)
    public const string OP_LT = 'lt'; // (less than)
    public const string OP_LTE = 'lte'; // (less than or equal to)
    public const string OP_GT = 'gt'; // (greater than)
    public const string OP_GTE = 'gte'; // (greater than or equal to)
    public const string OP_STARTS_WITH = 'startswith';
    public const string OP_DOES_NOT_START_WITH = 'doesnotstartwith';
    public const string OP_ENDS_WITH = 'endswith';
    public const string OP_DOES_NOT_END_WITH = 'doesnotendwith';
    public const string OP_CONTAINS = 'contains';
    public const string OP_DOES_NOT_CONTAIN = 'doesnotcontain';
    public const string OP_IS_EMPTY = 'isempty';
    public const string OP_IS_NOT_EMPTY = 'isnotempty';

    private function __construct(
        private array $filter = [],
    ) {
    }

    public function logicX(string $logic, self ...$x): static
    {
        if (null !== $this->field()) {
            throw new \LogicException(\sprintf('You can not use logical "%s" expression on field "%s" function. Please use logical composition expression first.', $logic, $this->field()));
        }
        $clone = clone $this;
        $clone->filter['logic'] ??= self::LOGIC_AND;
        $clone->filter['filters'] ??= [];
        $inv = self::LOGIC_AND === $logic ? self::LOGIC_OR : self::LOGIC_AND;
        if ($clone->filter['logic'] === $inv && 0 === \count($clone->filter['filters'])) {
            $clone->filter = ['logic' => $logic, 'filters' => []];
        }
        if ($clone->filter['logic'] === $logic) {
            $clone->filter['filters'] = array_merge($clone->filter['filters'], $x);
        } else {
            $clone->filter['filters'][] = [
                'logic' => $logic,
                'filters' => $x,
            ];
        }

        return $clone;
    }

    public function valX(string $field, string $operator, string|int|float|null $value, bool $ignoreCase = true): static
    {
        $clone = clone $this;
        if ((null === $value || '' === $value) && static::operatorRequiresValue($operator)) {
            return $clone;
        }
        $filter = ['field' => $field, 'operator' => $operator];
        if (null !== $value && '' !== $value) {
            $filter['value'] = $value;
        }
        if (!$ignoreCase) {
            $filter['ignoreCase'] = false;
        }
        if ($this->logic()) {
            $clone->filter['filters'][] = $filter;
        } else {
            $clone->filter = $filter;
        }

        return $clone;
    }

    public function logic(): ?string
    {
        return $this->filter['logic'] ?? null;
    }

    /**
     * @return array|static[]
     */
    public function filters(): array
    {
        return array_filter(array_map(function ($filter) {
            if (\is_array($filter) || \is_string($filter)) {
                $filter = static::create($filter);
            }
            if (!$filter instanceof FilterExpression) {
                $filter = null;
            }

            return $filter;
        }, $this->filter['filters'] ?? []));
    }

    public function field(): ?string
    {
        return $this->filter['field'] ?? null;
    }

    public function operator(): ?string
    {
        return $this->filter['operator'] ?? null;
    }

    public function value(): mixed
    {
        return $this->filter['value'] ?? null;
    }

    public function ignoreCase(): bool
    {
        return $this->filter['ignoreCase'] ?? true;
    }

    public function useLogicGrouping(bool $value): static
    {
        $this->filter['grouping'] = $value;

        return $this;
    }

    public function isUsingLogicGrouping(): bool
    {
        return $this->filter['grouping'] ?? true;
    }

    public function isFilterEmpty(): bool
    {
        if (0 === \count($this->filter) || null === $this->field() || null === $this->operator()) {
            return 0 === \count($this->filters());
        }

        return false;
    }

    public function expression(): ?string
    {
        $operator = $this->operator();
        if (null === $operator) {
            return null;
        }
        $description = static::getOperatorsDescription()[$operator] ?? null;
        if (null === $description) {
            return null;
        }
        $expression = $description['expression'] ?? null;
        if (null === $expression) {
            return null;
        }
        $value = $this->value() ?? '';
        $value = $this->ignoreCase() ? mb_strtoupper((string) $value) : $value;

        return str_replace('%value%', $value, $expression);
    }

    public function fieldExpression(string $field): string
    {
        return $this->fieldExpressionAt($field, 0);
    }

    private function fieldExpressionAt(string $field, int $level): string
    {
        if ($field === $this->field()) {
            return $this->expression();
        }

        $children = array_filter(array_map(fn ($filter) => $filter->fieldExpressionAt($field, $level + 1), $this->filters()));
        $result = implode(\count($children) > 1 ? " {$this->logic()} " : '', $children);

        return $level > 0 && \count($children) > 1 ? '('.$result.')' : $result;
    }

    /**
     * @return FilterItem[]
     */
    public function fieldFilters(string $field): array
    {
        $result = [];
        if ($field === ($this->filter['field'] ?? null)) {
            $result[] = $this->filter;
        }
        foreach ($this->filters() as $filter) {
            $result = array_merge($result, $filter->fieldFilters($field));
        }

        return $result;
    }

    private function removeFiltersForField(string $field): static
    {
        $clone = clone $this;
        if ($field === $clone->field()) {
            $clone->filter = [];
        }
        if (\count($clone->filter['filters'] ?? []) > 0) {
            $clone->filter['filters'] = array_map(fn (FilterExpression $filter) => $filter->removeFiltersForField($field), $clone->filters());
        }

        return $clone->compact();
    }

    public function compact(): static
    {
        $clone = clone $this;
        if (\count($clone->filter['filters'] ?? []) > 0) {
            $clone->filter['filters'] = array_map(fn (FilterExpression $filter) => $filter->compact(), $clone->filters());
            $clone->filter['filters'] = array_map(fn (FilterExpression $filter) => $filter->isFilterEmpty() ? null : $filter, $clone->filters());
            $clone->filter['filters'] = array_values(array_filter($clone->filter['filters']));
            if (0 === \count($clone->filter['filters'])) {
                unset($clone->filter['filters']);
            }
        }
        if (0 === \count($clone->filter['filters'] ?? [])) {
            if ($clone->isFilterEmpty()) {
                $clone->filter = [];
            }
        }

        return $clone;
    }

    public function reset(?string $field = null): static
    {
        $clone = clone $this;
        if (null !== $field) {
            $clone = $clone->removeFiltersForField($field);
        } else {
            $clone->filter = [];
        }

        return $clone;
    }

    public function andX(self ...$x): static
    {
        return $this->logicX(self::LOGIC_AND, ...$x);
    }

    public function orX(self ...$x): static
    {
        return $this->logicX(self::LOGIC_OR, ...$x);
    }

    public function equalTo(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_EQ, $value, $ignoreCase);
    }

    public function notEqualTo(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_NEQ, $value, $ignoreCase);
    }

    public function lowerThan(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_LT, $value, $ignoreCase);
    }

    public function lowerThanOrEqual(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_LTE, $value, $ignoreCase);
    }

    public function greaterThan(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_GT, $value, $ignoreCase);
    }

    public function greaterThanOrEqual(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_GTE, $value, $ignoreCase);
    }

    public function startsWith(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_STARTS_WITH, $value, $ignoreCase);
    }

    public function doesNotStartWith(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_DOES_NOT_START_WITH, $value, $ignoreCase);
    }

    public function endsWith(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_ENDS_WITH, $value, $ignoreCase);
    }

    public function doesNotEndWith(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_DOES_NOT_END_WITH, $value, $ignoreCase);
    }

    public function contains(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_CONTAINS, $value, $ignoreCase);
    }

    public function doesNotContain(string $field, string|int|float|null $value, bool $ignoreCase = true): static
    {
        return $this->valX($field, self::OP_DOES_NOT_CONTAIN, $value, $ignoreCase);
    }

    public function isNull(string $field): static
    {
        return $this->valX($field, self::OP_IS_NULL, null);
    }

    public function isNotNull(string $field): static
    {
        return $this->valX($field, self::OP_IS_NOT_NULL, null);
    }

    public function isEmpty(string $field): static
    {
        return $this->valX($field, self::OP_IS_EMPTY, null);
    }

    public function isNotEmpty(string $field): static
    {
        return $this->valX($field, self::OP_IS_NOT_EMPTY, null);
    }

    public function toArray(): array
    {
        $filter = $this->filter;
        if (\count($filter['filters'] ?? []) > 0) {
            $filter['filters'] = array_map(fn (FilterExpression $f) => $f->toArray(), $this->filters());
        }

        return $filter;
    }

    public function __clone(): void
    {
        if (\count($this->filter['filters'] ?? []) > 0) {
            $this->filter['filters'] = array_map(fn (FilterExpression $filter) => $filter->toArray(), $this->filters());
            $this->filter['filters'] = $this->filters();
        }
    }

    public function __toString(): string
    {
        return json_encode($this->filter);
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    public static function operatorRequiresValue(string $operator): bool
    {
        return static::getOperatorsDescription()[$operator]['value_required'] ?? false;
    }

    /**
     * @return array<string, FilterOperatorDescription>
     */
    public static function getOperatorsDescription(): array
    {
        return [
            self::OP_EQ => [
                'name' => 'Is equal to',
                'operator' => self::OP_EQ,
                'value_required' => true,
                'expression' => '=%value%',
            ],
            self::OP_NEQ => [
                'name' => 'Is not equal to',
                'operator' => self::OP_NEQ,
                'value_required' => true,
                'expression' => '!=%value%',
            ],
            self::OP_IS_NULL => [
                'name' => 'Is null',
                'operator' => self::OP_IS_NULL,
                'value_required' => false,
                'expression' => 'IS NULL',
            ],
            self::OP_IS_NOT_NULL => [
                'name' => 'Is not null',
                'operator' => self::OP_IS_NOT_NULL,
                'value_required' => false,
                'expression' => 'IS NOT NULL',
            ],
            self::OP_LT => [
                'name' => 'Is lower than',
                'operator' => self::OP_LT,
                'value_required' => true,
                'expression' => '<%value%',
            ],
            self::OP_LTE => [
                'name' => 'Is lower then or equal',
                'operator' => self::OP_LTE,
                'value_required' => true,
                'expression' => '<=%value%',
            ],
            self::OP_GT => [
                'name' => 'Is greater than',
                'operator' => self::OP_GT,
                'value_required' => true,
                'expression' => '>%value%',
            ],
            self::OP_GTE => [
                'name' => 'Is greater than or equal',
                'operator' => self::OP_GTE,
                'value_required' => true,
                'expression' => '>=%value%',
            ],
            self::OP_STARTS_WITH => [
                'name' => 'Is starting with',
                'operator' => self::OP_STARTS_WITH,
                'value_required' => true,
                'expression' => '^%value%',
            ],
            self::OP_DOES_NOT_START_WITH => [
                'name' => 'Is not starting with',
                'operator' => self::OP_DOES_NOT_START_WITH,
                'value_required' => true,
                'expression' => '!^%value%',
            ],
            self::OP_ENDS_WITH => [
                'name' => 'Is ending with',
                'operator' => self::OP_ENDS_WITH,
                'value_required' => true,
                'expression' => '%value%$',
            ],
            self::OP_DOES_NOT_END_WITH => [
                'name' => 'Is not ending with',
                'operator' => self::OP_DOES_NOT_END_WITH,
                'value_required' => true,
                'expression' => '%value%!$',
            ],
            self::OP_CONTAINS => [
                'name' => 'Contains',
                'operator' => self::OP_CONTAINS,
                'value_required' => true,
                'expression' => '%value%',
            ],
            self::OP_DOES_NOT_CONTAIN => [
                'name' => 'Does not contain',
                'operator' => self::OP_DOES_NOT_CONTAIN,
                'value_required' => true,
                'expression' => '!%value%',
            ],
            self::OP_IS_EMPTY => [
                'name' => 'Is empty',
                'operator' => self::OP_IS_EMPTY,
                'value_required' => false,
                'expression' => 'IS EMPTY',
            ],
            self::OP_IS_NOT_EMPTY => [
                'name' => 'Is not empty',
                'operator' => self::OP_IS_NOT_EMPTY,
                'value_required' => false,
                'expression' => 'IS NOT EMPTY',
            ],
        ];
    }

    public static function create(string|array $filter = []): static
    {
        if (\is_string($filter)) {
            $filter = json_decode($filter, true);
        }

        return new static($filter);
    }
}
