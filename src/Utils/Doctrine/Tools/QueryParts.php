<?php

/** @noinspection PhpUnused */
/* @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace App\Utils\Doctrine\Tools;

use Doctrine\ORM\Query\Expr;
use Doctrine\ORM\QueryBuilder;

class QueryParts implements \Stringable
{
    private static ?Expr $expressionBuilder = null;

    private array $parts = [
        'where' => null,
        'groupBy' => [],
        'having' => null,
        'orderBy' => [],
    ];

    public function getQueryPart(string $queryPartName): mixed
    {
        return $this->parts[$queryPartName];
    }

    public function getQueryParts(): array
    {
        return $this->parts;
    }

    public function isEmpty(): bool
    {
        $parts = array_filter($this->parts, function ($val) {
            return '' === $val || null === $val || [] === $val;
        });

        return 0 === \count($parts);
    }

    /**
     * Either appends to or replaces a single, generic query part.
     *
     * The available parts are: 'where', 'groupBy', 'having' and 'orderBy'.
     */
    public function add(string $partName, string|object|array $part, bool $append = false): static
    {
        if ($append && ('where' === $partName || 'having' === $partName)) {
            throw new \InvalidArgumentException("Using \$append = true does not have an effect with 'where' or 'having' ".'parts. See QueryBuilder#andWhere() for an example for correct usage.');
        }

        $isMultiple = \is_array($this->parts[$partName]);

        // Allow adding any part retrieved from self::getQueryParts().
        if (\is_array($part)) {
            $part = reset($part);
        }

        if ($append && $isMultiple) {
            if (\is_array($part)) {
                $key = key($part);

                $this->parts[$partName][$key][] = $part[$key];
            } else {
                $this->parts[$partName][] = $part;
            }
        } else {
            $this->parts[$partName] = $isMultiple ? [$part] : $part;
        }

        return $this;
    }

    public function where(mixed ...$predicates): static
    {
        self::validateVariadicParameter($predicates);

        if (!(1 === \count($predicates) && $predicates[0] instanceof Expr\Composite)) {
            $predicates = new Expr\Andx($predicates);
        }

        return $this->add('where', $predicates);
    }

    public function andWhere(mixed ...$where): static
    {
        self::validateVariadicParameter($where);

        $part = $this->getQueryPart('where');

        if ($part instanceof Expr\Andx) {
            $part->addMultiple($where);
        } else {
            array_unshift($where, $part);
            $part = new Expr\Andx($where);
        }

        return $this->add('where', $part);
    }

    public function orWhere(mixed ...$where): static
    {
        self::validateVariadicParameter($where);

        $part = $this->getQueryPart('where');

        if ($part instanceof Expr\Orx) {
            $part->addMultiple($where);
        } else {
            array_unshift($where, $part);
            $part = new Expr\Orx($where);
        }

        return $this->add('where', $part);
    }

    public function groupBy(string ...$groupBy): static
    {
        self::validateVariadicParameter($groupBy);

        return $this->add('groupBy', new Expr\GroupBy($groupBy));
    }

    public function addGroupBy(string ...$groupBy): static
    {
        self::validateVariadicParameter($groupBy);

        return $this->add('groupBy', new Expr\GroupBy($groupBy), true);
    }

    public function having(mixed ...$having): static
    {
        self::validateVariadicParameter($having);

        if (!(1 === \count($having) && ($having[0] instanceof Expr\Andx || $having[0] instanceof Expr\Orx))) {
            $having = new Expr\Andx($having);
        }

        return $this->add('having', $having);
    }

    public function andHaving(mixed ...$having): static
    {
        self::validateVariadicParameter($having);

        $part = $this->getQueryPart('having');

        if ($part instanceof Expr\Andx) {
            $part->addMultiple($having);
        } else {
            array_unshift($having, $part);
            $part = new Expr\Andx($having);
        }

        return $this->add('having', $part);
    }

    public function orHaving(mixed ...$having): static
    {
        self::validateVariadicParameter($having);

        $part = $this->getQueryPart('having');

        if ($part instanceof Expr\Orx) {
            $part->addMultiple($having);
        } else {
            array_unshift($having, $part);
            $part = new Expr\Orx($having);
        }

        return $this->add('having', $part);
    }

    public function orderBy(string|Expr\OrderBy $sort, ?string $order = null): static
    {
        $orderBy = $sort instanceof Expr\OrderBy ? $sort : new Expr\OrderBy($sort, $order);

        return $this->add('orderBy', $orderBy);
    }

    public function addOrderBy(string|Expr\OrderBy $sort, ?string $order = null): static
    {
        $orderBy = $sort instanceof Expr\OrderBy ? $sort : new Expr\OrderBy($sort, $order);

        return $this->add('orderBy', $orderBy, true);
    }

    private function getReducedQueryPart(string $queryPartName, array $options = []): string
    {
        $queryPart = $this->getQueryPart($queryPartName);

        if (empty($queryPart)) {
            return $options['empty'] ?? '';
        }

        return ($options['pre'] ?? '')
             .(\is_array($queryPart) ? implode($options['separator'], $queryPart) : $queryPart)
             .($options['post'] ?? '');
    }

    public function resetQueryParts(?array $parts = null): static
    {
        if (null === $parts) {
            $parts = array_keys($this->parts);
        }

        foreach ($parts as $part) {
            $this->resetQueryPart($part);
        }

        return $this;
    }

    public function resetQueryPart(string $part): static
    {
        $this->parts[$part] = \is_array($this->parts[$part]) ? [] : null;

        return $this;
    }

    public function getWhereSql(): string
    {
        return $this->getReducedQueryPart('where', ['pre' => ' WHERE ']);
    }

    public function getGroupBySql(): string
    {
        return $this->getReducedQueryPart('groupBy', ['pre' => ' GROUP BY ', 'separator' => ', ']);
    }

    public function getHavingSql(): string
    {
        return $this->getReducedQueryPart('having', ['pre' => ' HAVING ']);
    }

    public function getOrderBySql(): string
    {
        return $this->getReducedQueryPart('orderBy', ['pre' => ' ORDER BY ', 'separator' => ', ']);
    }

    public function getWhereSqlReduced(): string
    {
        return $this->getReducedQueryPart('where');
    }

    public function getGroupBySqlReduced(): string
    {
        return $this->getReducedQueryPart('groupBy', ['separator' => ', ']);
    }

    public function getHavingSqlReduced(): string
    {
        return $this->getReducedQueryPart('having');
    }

    public function getOrderBySqlReduced(): string
    {
        return $this->getReducedQueryPart('orderBy', ['separator' => ', ']);
    }

    public function hasWhere(): bool
    {
        $val = $this->getWhereSqlReduced();
        $val = mb_trim(str_replace(\PHP_EOL, ' ', $val));

        return '' !== $val;
    }

    public function hasGroupBy(): bool
    {
        $val = $this->getGroupBySqlReduced();
        $val = mb_trim(str_replace(\PHP_EOL, ' ', $val));

        return '' !== $val;
    }

    public function hasHaving(): bool
    {
        $val = $this->getHavingSqlReduced();
        $val = mb_trim(str_replace(\PHP_EOL, ' ', $val));

        return '' !== $val;
    }

    public function hasOrderBy(): bool
    {
        $val = $this->getOrderBySqlReduced();
        $val = mb_trim(str_replace(\PHP_EOL, ' ', $val));

        return '' !== $val;
    }

    public function getSql(): string
    {
        return $this->getWhereSql()
            .$this->getGroupBySql()
            .$this->getHavingSql()
            .$this->getOrderBySql();
    }

    public function expr(): Expr
    {
        if (null === self::$expressionBuilder) {
            self::$expressionBuilder = new Expr();
        }

        return self::$expressionBuilder;
    }

    public function addTo(self|QueryBuilder $parts): static
    {
        $new = clone $this;
        $newParts = $new->getQueryParts();
        if ($new->hasWhere()) {
            $parts->andWhere($newParts['where']);
        }
        if ($new->hasGroupBy()) {
            foreach ($newParts['groupBy'] as $item) {
                $parts->addGroupBy($item);
            }
        }
        if ($new->hasHaving()) {
            $parts->andHaving($newParts['having']);
        }
        if ($new->hasOrderBy()) {
            foreach ($newParts['orderBy'] as $item) {
                $parts->addOrderBy($item);
            }
        }

        return $this;
    }

    public function __toString(): string
    {
        return $this->getSql();
    }

    /**
     * Deep clones all expression objects in the Query parts.
     *
     * @return void
     */
    public function __clone()
    {
        foreach ($this->parts as $part => $elements) {
            if (\is_array($this->parts[$part])) {
                foreach ($this->parts[$part] as $idx => $element) {
                    if (\is_object($element)) {
                        $this->parts[$part][$idx] = clone $element;
                    }
                }
            } elseif (\is_object($elements)) {
                $this->parts[$part] = clone $elements;
            }
        }
    }

    /**
     * @param TItem[] $parameter
     *
     * @template TItem
     *
     * @psalm-assert list<TItem> $parameter
     */
    private static function validateVariadicParameter(array $parameter): void
    {
        if (array_is_list($parameter)) {
            return;
        }

        [, $trace] = debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS);
        \assert(isset($trace['class']));

        $additionalArguments = array_values(array_filter(
            array_keys($parameter),
            is_string(...),
        ));

        throw new \BadMethodCallException(\sprintf('Invalid call to %s::%s(), unknown named arguments: %s', $trace['class'], $trace['function'], implode(', ', $additionalArguments)));
    }
}
