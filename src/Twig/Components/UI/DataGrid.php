<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Twig\Components\UI;

use App\Components\UI\DataGrid\Grid;
use App\Components\UI\DataGrid\GridBuilder;
use App\Utils\Domain\ReadModel\QueryExpression;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent]
final class DataGrid implements DataGridInterface
{
    use DataGridTrait;

    public function buildGrid(GridBuilder $gridBuilder): Grid
    {
        throw new \LogicException('No grid was provided!');
    }

    public function setupDefaultQuery(QueryExpression $query): QueryExpression
    {
        return $query;
    }

    public function modifyQuery(QueryExpression $query): QueryExpression
    {
        return $query;
    }
}
