<?php

declare(strict_types=1);

namespace App\Service\OpenApi\Attribute;

#[\Attribute(\Attribute::TARGET_PARAMETER | \Attribute::IS_REPEATABLE)]
final class MapReadModel
{
}
