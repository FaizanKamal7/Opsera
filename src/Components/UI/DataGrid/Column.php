<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Components\UI\DataGrid;

use App\Utils\Domain\ReadModel\FilterExpression;
use App\Utils\Std\StdUtils;
use Symfony\Component\PropertyAccess\PropertyAccess;

/**
 * @psalm-type ColumnParams = array{
 *     col_class?: string,
 *     escape?: bool,
 *     filter_operator?: string,
 *     ignore_case?: bool,
 *     hide_column_filter_operator?: bool,
 * }
 * @psalm-type ColumnValue = string|int
 * @psalm-type ColumnValues = array<ColumnValue, string>
 * @psalm-type ColumnValuesListItem = array{
 *     value: ColumnValue,
 *     text: string
 * }
 * @psalm-type ColumnValuesList = array<array-key, ColumnValuesListItem>
 *
 * @psalm-import-type FilterOperatorDescription from FilterExpression
 */
class Column
{
    /**
     * @var callable
     */
    private $valueAccessor;

    public function __construct(
        public readonly string $name,
        public readonly string $field,
        public readonly string $type,
        public readonly ?int $width = null,
        public readonly bool $sortable = true,
        public readonly bool $filterable = true,
        public readonly bool $visible = true,
        /**
         * @var ColumnParams
         */
        public readonly array $params = [],
        /**
         * @var ColumnValues
         */
        public readonly array $values = [],
        public mixed $template = ColumnTemplate::AUTO,
    ) {
        $this->valueAccessor = \count($this->values) > 0 ? $this->getColumnListValue(...) : $this->getColumnValue(...);
        $isAutoTemplate = ColumnTemplate::AUTO === $this->template;
        if ($isAutoTemplate) {
            $this->template = match ($this->type) {
                ColumnType::BOOLEAN => ColumnTemplate::BOOLEAN,
                ColumnType::DATE => ColumnTemplate::DATETIME,
                default => ColumnTemplate::TEXT,
            };
        }
        if (\is_callable($this->template)) {
            $templateRenderer = $this->template;
            $valueAccessor = $this->valueAccessor;
            $charset = \ini_get('default_charset');
            $charset = false === $charset || '' === $charset ? 'UTF-8' : $charset;
            $useRawValue = StdUtils::getCallableParameterCount($templateRenderer) > 2;
            $this->valueAccessor = function (object|array $objectOrArray) use ($valueAccessor, $templateRenderer, $charset, $useRawValue): mixed {
                /** @var scalar|null $value */
                $value = \call_user_func($valueAccessor, $objectOrArray);
                $value = htmlspecialchars((string) $value, \ENT_COMPAT | \ENT_SUBSTITUTE, $charset);
                /** @var scalar|object|null $rawValue */
                $rawValue = $useRawValue ? $this->getRawValue($objectOrArray) : null;

                return \call_user_func($templateRenderer, $value, $objectOrArray, $rawValue);
            };
            $this->template = ColumnTemplate::TEXT;
        }
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function getRawValue(object|array $objectOrArray): mixed
    {
        return $this->getColumnValue($objectOrArray);
    }

    /**
     * @phpstan-ignore missingType.iterableValue
     */
    public function getValue(object|array $objectOrArray): mixed
    {
        return \call_user_func($this->valueAccessor, $objectOrArray);
    }

    public function getParam(string $name, mixed $default = null): mixed
    {
        return $this->params[$name] ?? $default;
    }

    /**
     * @return ColumnParams
     */
    public function getParams(): array
    {
        return $this->params;
    }

    public function getDefaultFilterOperator(): string
    {
        return (string) ($this->getParam('filter_operator') ?? match ($this->type) {
            ColumnType::STRING => FilterExpression::OP_STARTS_WITH,
            ColumnType::DATE => FilterExpression::OP_GTE,
            default => FilterExpression::OP_EQ,
        });
    }

    /**
     * @return ColumnValuesList
     */
    public function getValuesList(): array
    {
        $result = [];
        foreach ($this->values as $key => $value) {
            $result[] = ['text' => $value, 'value' => $key];
        }

        return $result;
    }

    /**
     * @return FilterOperatorDescription[]
     */
    public function getFilterOperatorsList(): array
    {
        $desc = FilterExpression::getOperatorsDescription();

        return match ($this->type) {
            ColumnType::STRING => [
                $desc[FilterExpression::OP_EQ],
                $desc[FilterExpression::OP_NEQ],
                $desc[FilterExpression::OP_IS_NULL],
                $desc[FilterExpression::OP_IS_NOT_NULL],
                $desc[FilterExpression::OP_STARTS_WITH],
                $desc[FilterExpression::OP_DOES_NOT_START_WITH],
                $desc[FilterExpression::OP_ENDS_WITH],
                $desc[FilterExpression::OP_DOES_NOT_END_WITH],
                $desc[FilterExpression::OP_CONTAINS],
                $desc[FilterExpression::OP_DOES_NOT_CONTAIN],
                $desc[FilterExpression::OP_IS_EMPTY],
                $desc[FilterExpression::OP_IS_NOT_EMPTY],
            ],
            ColumnType::NUMBER, ColumnType::DATE => [
                $desc[FilterExpression::OP_EQ],
                $desc[FilterExpression::OP_NEQ],
                $desc[FilterExpression::OP_IS_NULL],
                $desc[FilterExpression::OP_IS_NOT_NULL],
                $desc[FilterExpression::OP_LT],
                $desc[FilterExpression::OP_LTE],
                $desc[FilterExpression::OP_GT],
                $desc[FilterExpression::OP_GTE],
                $desc[FilterExpression::OP_IS_EMPTY],
                $desc[FilterExpression::OP_IS_NOT_EMPTY],
            ],
            default => [
                $desc[FilterExpression::OP_EQ],
                $desc[FilterExpression::OP_NEQ],
                $desc[FilterExpression::OP_IS_NULL],
                $desc[FilterExpression::OP_IS_NOT_NULL],
                $desc[FilterExpression::OP_IS_EMPTY],
                $desc[FilterExpression::OP_IS_NOT_EMPTY],
            ],
        };
    }

    /**
     * @psalm-suppress MixedAssignment
     *
     * @phpstan-ignore missingType.iterableValue
     */
    private function getColumnValue(object|array $objectOrArray): mixed
    {
        if (\is_array($objectOrArray)) {
            if (false === mb_strpos($this->field, '.')) {
                return $objectOrArray[$this->field] ?? null;
            }

            return PropertyAccess::createPropertyAccessor()->getValue($objectOrArray, "[$this->field]");
        }

        return PropertyAccess::createPropertyAccessor()->getValue($objectOrArray, $this->field);
    }

    /**
     * @psalm-suppress MixedAssignment,MixedArrayTypeCoercion,MixedArrayOffset
     *
     * @phpstan-ignore missingType.iterableValue
     */
    private function getColumnListValue(object|array $objectOrArray): string
    {
        $value = $this->getColumnValue($objectOrArray);
        $normalizedValue = null === $value || '' === $value ? null : match ($this->type) {
            ColumnType::BOOLEAN => \in_array($value, [true, 1, '1',  'y', 'Y', 'true', 'TRUE'], true) ? true : (
                \in_array($value, [false, 0, '0',  'n', 'N', 'false', 'FALSE'], true) ? false : $value
            ),
            ColumnType::NUMBER => ctype_digit((string) $value) ? (int) $value : (float) $value,
            default => (string) $value,
        };

        return $this->values[$normalizedValue] ?? (string) $value;
    }
}
