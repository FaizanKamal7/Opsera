<?php

declare(strict_types=1);

namespace App\Utils\Doctrine\DataSource\Builder;

interface PaginationFeatureInterface extends FeatureInterface
{
    public function setPage(?int $page): void;

    public function getPage(): ?int;

    public function setItemsPerPage(?int $itemsPerPage): void;

    public function getItemsPerPage(): ?int;
}
