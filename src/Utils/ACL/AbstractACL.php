<?php

declare(strict_types=1);

namespace App\Utils\ACL;

abstract class AbstractACL
{
    public const string R_ALL = 'ALL';
    public const string P_ALL = 'ALL';

    protected const string DENY = Manager::TYPE_DENY;
    protected const string ALLOW = Manager::TYPE_ALLOW;

    private ?Manager $manager = null;

    public function inheritsRole(RoleInterface|string $role, RoleInterface|string $inherit, bool $onlyParents = false): bool
    {
        return $this->getManager()->inheritsRole($role, $inherit, $onlyParents);
    }

    public function isAllowed(RoleInterface|string|null $role = null, ResourceInterface|string|null $resource = null, ?string $privilege = null): bool
    {
        return $this->getManager()->isAllowed($role, $resource, $privilege);
    }

    /**
     * @return array<string, string>
     */
    public function getRolesList(): array
    {
        return array_map(fn ($t) => $t['name'], (static::$config ?? [])['roles'] ?? []);
    }

    private function loadRoles(Manager $manager, array $roles): void
    {
        foreach ($roles as $role => ['inherits' => $roleParents]) {
            $manager->addRole($role, $roleParents);
        }
    }

    private function loadResources(Manager $manager, array $resources, ?string $parent = null): void
    {
        foreach ($resources as $resource => $item) {
            $manager->addResource($resource, $parent);
            $this->loadResources($manager, $item['children'] ?? [], $resource);
        }
    }

    abstract protected function loadRules(Manager $acl): void;

    private function getManager(): Manager
    {
        if (null !== $this->manager) {
            return $this->manager;
        }

        $config = static::$config ?? [];

        $manager = new Manager();

        $this->loadRoles($manager, $config['roles'] ?? []);
        $this->loadResources($manager, $config['resources'] ?? []);
        $this->loadRules($manager);

        $this->manager = $manager;

        return $this->manager;
    }
}
