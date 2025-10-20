<?php

declare(strict_types=1);

namespace App\Utils\Domain\ReadModel;

class SortExpression implements \JsonSerializable, \Stringable
{
    public const string DIR_ASC = 'asc';
    public const string DIR_DESC = 'desc';

    private function __construct(
        private array $sort = []
    ) {
    }

    private function by(string $field, string $dir): static
    {
        $cloned = clone $this;
        $cloned->reset($field);

        if (null !== ($cloned->sort['field'] ?? null)) {
            $cloned->sort = [$cloned->sort];
        }
        if (\count($cloned->sort) > 0) {
            $cloned->sort[] = ['field' => $field, 'dir' => $dir];
        } else {
            $cloned->sort = ['field' => $field, 'dir' => $dir];
        }

        return $cloned;
    }

    public function asc(string ...$field): static
    {
        $result = clone $this;
        foreach ($field as $item) {
            $result = $result->reset($item);
            $result = $result->by($item, self::DIR_ASC);
        }

        return $result;
    }

    public function desc(string ...$field): static
    {
        $result = clone $this;
        foreach ($field as $item) {
            $result = $result->reset($item);
            $result = $result->by($item, self::DIR_DESC);
        }

        return $result;
    }

    public function reset(?string $field = null): static
    {
        $clone = clone $this;
        if (null !== $field) {
            $items = $clone->items();
            foreach ($items as $index => ['field' => $itemField]) {
                if ($field === $itemField) {
                    unset($items[$index]);
                    $clone->sort = array_values($items);
                    break;
                }
            }
        } else {
            $clone->sort = [];
        }

        return $clone;
    }

    public function sort(): array
    {
        return $this->sort;
    }

    public function dir(string $field): ?string
    {
        $items = $this->items();
        $item = array_find($items, fn (array $item) => $field === $item['field']) ?? [];

        return $item['dir'] ?? null;
    }

    public function num(string $field): ?int
    {
        $items = $this->items();
        $item = array_find($items, fn (array $item) => $field === $item['field']) ?? [];
        $index = array_search($item, $items, true);

        return false === $index ? null : (int) ($index + 1);
    }

    public function count(): int
    {
        return \count($this->items());
    }

    public function isSortEmpty(): bool
    {
        return 0 === $this->count();
    }

    /**
     * @return array<array-key, array{field: string, dir: string}>
     */
    public function items(): array
    {
        return array_values($this->sort) === $this->sort ? $this->sort : [$this->sort];
    }

    public function toArray(): array
    {
        return $this->sort;
    }

    public function __toString(): string
    {
        return json_encode($this->sort);
    }

    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    public static function create(string|array $sort = []): static
    {
        if (\is_string($sort)) {
            $sort = json_decode($sort, true);
        }

        return new static($sort);
    }
}
