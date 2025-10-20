<?php

declare(strict_types=1);

namespace App\Utils\Doctrine\DataSource\Builder;

interface ValueFeatureInterface extends FeatureInterface
{
    public function sortData(array $data): array;
}
