<?php

declare(strict_types=1);

namespace App\Utils\ACL;

interface RoleInterface
{
    public function getRoleId(): string;
}
