<?php

declare(strict_types=1);

namespace App\Utils\ACL;

final readonly class Resource implements ResourceInterface, \Stringable
{
    public function __construct(
        private string $resourceId
    ) {
    }

    public function getResourceId(): string
    {
        return $this->resourceId;
    }

    public function __toString(): string
    {
        return $this->resourceId;
    }
}
