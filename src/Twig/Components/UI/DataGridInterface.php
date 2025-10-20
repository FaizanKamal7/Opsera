<?php

declare(strict_types=1);

namespace App\Twig\Components\UI;

use App\Components\UI\DataGrid\Grid;
use App\Components\UI\DataGrid\GridBuilder;
use App\Utils\Domain\ReadModel\QueryExpression;

interface DataGridInterface
{
    /**
     * Build the grid object which.
     */
    public function buildGrid(GridBuilder $gridBuilder): Grid;

    /**
     * Setup default query expressions rules which will be used when rendering the grid for the first time. They can be changed by the user while working with the grid UI.
     *
     * Note: The QueryExpression is a value object which means you can not modify it directly, but every method which tries to modify it's state will return a new instance.
     */
    public function setupDefaultQuery(QueryExpression $query): QueryExpression;

    /**
     * Setup permanent query expression rules which will be used throughout the whole life cycle of the grid. They can not be changed by the user while working with the grid UI.
     *
     * Note: The QueryExpression is a value object which means you can not modify it directly, but every method which tries to modify it's state will return a new instance.
     */
    public function modifyQuery(QueryExpression $query): QueryExpression;
}
