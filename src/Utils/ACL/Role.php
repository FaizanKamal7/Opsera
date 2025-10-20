<?php

declare(strict_types=1);

namespace App\Utils\ACL;

final readonly class Role implements RoleInterface, \Stringable
{
    public function __construct(
        private string $roleId
    ) {
    }

    public function getRoleId(): string
    {
        return $this->roleId;
    }

    public function __toString(): string
    {
        return $this->roleId;
    }
}
