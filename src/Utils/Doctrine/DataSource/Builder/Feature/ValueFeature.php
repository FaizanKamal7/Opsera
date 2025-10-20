<?php

declare(strict_types=1);

namespace App\Utils\Doctrine\DataSource\Builder\Feature;

use App\Utils\Doctrine\DataSource\Builder\AbstractFeature;
use App\Utils\Doctrine\DataSource\Builder\ValueFeatureInterface;

class ValueFeature extends AbstractFeature implements ValueFeatureInterface
{
    private array $values = [];

    public function isBreaking(): bool
    {
        return true;
    }

    protected function doPrepare(array $params): bool
    {
        $values = null;
        $value = null;

        $defaults = compact([
            'values',
            'value',
        ]);

        $params = array_replace($defaults, array_intersect_key($params, $defaults));
        extract($params);

        if (null === $values && null !== $value) {
            $values = [$value];
        }

        $this->values = \is_array($values) ? $values : [];

        return \count($this->values) > 0;
    }

    protected function doUnprepare(): void
    {
        $this->values = [];
    }

    protected function doApply(): void
    {
        if (empty($this->values)) {
            return;
        }

        $field = $this->getRootIdentifier();
        $field = $this->mapField($field);

        $parts = $this->getBuilder()->parts();
        $parts->andWhere($parts->expr()->in($field, $this->values));
    }

    protected function doUnApply(): void
    {
    }

    public function sortData(array $data): array
    {
        if (empty($this->values) || 1 === \count($data)) {
            return $data;
        }

        $field = $this->getRootIdentifier();
        $values = $this->values;

        $item = reset($data);
        if (\is_array($item)) {
            usort($data, function ($a, $b) use ($field, $values) {
                return array_search($a[$field], $values, true) > array_search($b[$field], $values, true) ? 1 : -1;
            });
        }
        if (\is_object($item)) {
            $field = 'get'.$field;
            usort($data, function ($a, $b) use ($field, $values) {
                $av = \call_user_func([$a, $field]);
                $bv = \call_user_func([$b, $field]);

                return array_search($av, $values, true) > array_search($bv, $values, true) ? 1 : -1;
            });
        }

        return $data;
    }
}
