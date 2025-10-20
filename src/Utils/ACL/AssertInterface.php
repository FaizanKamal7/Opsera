<?php

declare(strict_types=1);

namespace App\Utils\ACL;

interface AssertInterface
{
    public function assert(Manager $acl, ?RoleInterface $role = null, ?ResourceInterface $resource = null, ?string $privilege = null): bool;
}
