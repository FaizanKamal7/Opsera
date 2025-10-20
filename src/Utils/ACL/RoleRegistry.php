<?php

declare(strict_types=1);

namespace App\Utils\ACL;

class RoleRegistry
{
    /**
     * @var RoleInterface[]
     */
    private array $roles = [];

    /**
     * Adds a Role having an identifier unique to the registry.
     *
     * The $parents parameter may be a reference to, or the string identifier for,
     * a Role existing in the registry, or $parents may be passed as an array of
     * these - mixing string identifiers and objects is ok - to indicate the Roles
     * from which the newly added Role will directly inherit.
     *
     * In order to resolve potential ambiguities with conflicting rules inherited
     * from different parents, the most recently added parent takes precedence over
     * parents that were previously added. In other words, the first parent added
     * will have the least priority, and the last parent added will have the
     * highest priority.
     *
     * @param RoleInterface|RoleInterface[]|string|array $parents
     */
    public function add(RoleInterface $role, RoleInterface|string|array|null $parents = null): static
    {
        $roleId = $role->getRoleId();

        if ($this->has($roleId)) {
            throw new RegistryException("Role id '$roleId' already exists in the registry");
        }

        $roleParents = [];

        if (null !== $parents) {
            if (!\is_array($parents)) {
                $parents = [$parents];
            }
            foreach ($parents as $parent) {
                try {
                    if ($parent instanceof RoleInterface) {
                        $roleParentId = $parent->getRoleId();
                    } else {
                        $roleParentId = $parent;
                    }
                    $roleParent = $this->get($roleParentId);
                } catch (RegistryException $e) {
                    throw new RegistryException("Parent Role id '$roleParentId' does not exist", 0, $e);
                }
                $roleParents[$roleParentId] = $roleParent;
                $this->roles[$roleParentId]['children'][$roleId] = $role;
            }
        }

        $this->roles[$roleId] = [
            'instance' => $role,
            'parents' => $roleParents,
            'children' => [],
        ];

        return $this;
    }

    /**
     * Returns the identified Role.
     *
     * The $role parameter can either be a Role or a Role identifier.
     */
    public function get(RoleInterface|string $role): RoleInterface
    {
        if ($role instanceof RoleInterface) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = $role;
        }

        if (!$this->has($role)) {
            throw new RegistryException("Role '$roleId' not found");
        }

        return $this->roles[$roleId]['instance'];
    }

    /**
     * Returns true if and only if the Role exists in the registry.
     *
     * The $role parameter can either be a Role or a Role identifier.
     */
    public function has(RoleInterface|string $role): bool
    {
        if ($role instanceof RoleInterface) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = $role;
        }

        return isset($this->roles[$roleId]);
    }

    /**
     * Returns an array of an existing Role's parents.
     *
     * The array keys are the identifiers of the parent Roles, and the values are
     * the parent Role instances. The parent Roles are ordered in this array by
     * ascending priority. The highest priority parent Role, last in the array,
     * corresponds with the parent Role most recently added.
     *
     * If the Role does not have any parents, then an empty array is returned.
     *
     * @return RoleInterface[]
     */
    public function getParents(RoleInterface|string $role): array
    {
        $roleId = $this->get($role)->getRoleId();

        return $this->roles[$roleId]['parents'];
    }

    /**
     * Returns true if and only if $role inherits from $inherit.
     *
     * Both parameters may be either a Role or a Role identifier. If
     * $onlyParents is true, then $role must inherit directly from
     * $inherit in order to return true. By default, this method looks
     * through the entire inheritance DAG to determine whether $role
     * inherits from $inherit through its ancestor Roles.
     *
     * @throws RegistryException
     */
    public function inherits(RoleInterface|string $role, RoleInterface|string $inherit, bool $onlyParents = false): bool
    {
        try {
            $roleId = $this->get($role)->getRoleId();
            $inheritId = $this->get($inherit)->getRoleId();
        } catch (RegistryException $e) {
            throw new RegistryException($e->getMessage(), $e->getCode(), $e);
        }

        $inherits = isset($this->roles[$roleId]['parents'][$inheritId]);

        if ($inherits || $onlyParents) {
            return $inherits;
        }

        return array_any($this->roles[$roleId]['parents'], fn ($parent, $parentId) => $this->inherits($parentId, $inheritId));
    }

    /**
     * Removes the Role from the registry.
     *
     * The $role parameter can either be a Role or a Role identifier.
     *
     * @throws RegistryException
     *
     * @return RoleRegistry Provides a fluent interface
     */
    public function remove(RoleInterface|string $role): static
    {
        try {
            $roleId = $this->get($role)->getRoleId();
        } catch (RegistryException $e) {
            throw new RegistryException($e->getMessage(), $e->getCode(), $e);
        }

        foreach ($this->roles[$roleId]['children'] as $childId => $child) {
            unset($this->roles[$childId]['parents'][$roleId]);
        }
        foreach ($this->roles[$roleId]['parents'] as $parentId => $parent) {
            unset($this->roles[$parentId]['children'][$roleId]);
        }

        unset($this->roles[$roleId]);

        return $this;
    }

    /**
     * Removes all Roles from the registry.
     *
     * @return RoleRegistry Provides a fluent interface
     */
    public function removeAll(): static
    {
        $this->roles = [];

        return $this;
    }

    /**
     * @return RoleInterface[]
     */
    public function getRoles(): array
    {
        return $this->roles;
    }
}
