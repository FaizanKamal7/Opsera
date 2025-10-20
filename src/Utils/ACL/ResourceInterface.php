<?php

declare(strict_types=1);

namespace App\Utils\ACL;

interface ResourceInterface
{
    public function getResourceId(): string;
}
