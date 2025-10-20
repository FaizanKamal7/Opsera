<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace App\Utils\ACL;

class Manager
{
    public const string TYPE_ALLOW = 'TYPE_ALLOW';
    public const string TYPE_DENY = 'TYPE_DENY';
    public const string OP_ADD = 'OP_ADD';
    public const string OP_REMOVE = 'OP_REMOVE';

    protected ?RoleRegistry $_roleRegistry = null;

    /**
     * Resource tree.
     *
     * @var ResourceInterface[]
     */
    protected ?array $resources = [];

    protected RoleInterface|string|null $isAllowedRole = null;

    protected ResourceInterface|string|null $isAllowedResource = null;

    protected ?string $isAllowedPrivilege = null;

    protected array $rules = [
        'allResources' => [
            'allRoles' => [
                'allPrivileges' => [
                    'type' => self::TYPE_DENY,
                    'assert' => null,
                ],
                'byPrivilegeId' => [],
            ],
            'byRoleId' => [],
        ],
        'byResourceId' => [],
    ];

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
    public function addRole(RoleInterface|string $role, RoleInterface|string|array|null $parents = null): static
    {
        if (\is_string($role)) {
            $role = new Role($role);
        }

        $this->_getRoleRegistry()->add($role, $parents);

        return $this;
    }

    public function getRole(RoleInterface|string $role): RoleInterface
    {
        return $this->_getRoleRegistry()->get($role);
    }

    public function hasRole(RoleInterface|string $role): bool
    {
        return $this->_getRoleRegistry()->has($role);
    }

    /**
     * Returns true if and only if $role inherits from $inherit.
     *
     * Both parameters may be either a Role or a Role identifier. If
     * $onlyParents is true, then $role must inherit directly from
     * $inherit in order to return true. By default, this method looks
     * through the entire inheritance DAG to determine whether $role
     * inherits from $inherit through its ancestor Roles.
     */
    public function inheritsRole(RoleInterface|string $role, RoleInterface|string $inherit, bool $onlyParents = false): bool
    {
        return $this->_getRoleRegistry()->inherits($role, $inherit, $onlyParents);
    }

    public function removeRole(RoleInterface|string $role): static
    {
        $this->_getRoleRegistry()->remove($role);

        if ($role instanceof RoleInterface) {
            $roleId = $role->getRoleId();
        } else {
            $roleId = $role;
        }

        foreach ($this->rules['allResources']['byRoleId'] as $roleIdCurrent => $rules) {
            if ($roleId === $roleIdCurrent) {
                unset($this->rules['allResources']['byRoleId'][$roleIdCurrent]);
            }
        }
        foreach ($this->rules['byResourceId'] as $resourceIdCurrent => $visitor) {
            if (\array_key_exists('byRoleId', $visitor)) {
                foreach ($visitor['byRoleId'] as $roleIdCurrent => $rules) {
                    if ($roleId === $roleIdCurrent) {
                        unset($this->rules['byResourceId'][$resourceIdCurrent]['byRoleId'][$roleIdCurrent]);
                    }
                }
            }
        }

        return $this;
    }

    public function removeRoleAll(): static
    {
        $this->_getRoleRegistry()->removeAll();

        foreach ($this->rules['allResources']['byRoleId'] as $roleIdCurrent => $rules) {
            unset($this->rules['allResources']['byRoleId'][$roleIdCurrent]);
        }
        foreach ($this->rules['byResourceId'] as $resourceIdCurrent => $visitor) {
            foreach ($visitor['byRoleId'] as $roleIdCurrent => $rules) {
                unset($this->rules['byResourceId'][$resourceIdCurrent]['byRoleId'][$roleIdCurrent]);
            }
        }

        return $this;
    }

    public function addResource(ResourceInterface|string $resource, ResourceInterface|string|null $parent = null): static
    {
        if (\is_string($resource)) {
            $resource = new Resource($resource);
        }

        $resourceId = $resource->getResourceId();

        if ($this->has($resourceId)) {
            throw new Exception("Resource id '$resourceId' already exists in the ACL");
        }

        $resourceParent = null;
        $resourceParentId = null;

        if (null !== $parent) {
            try {
                if ($parent instanceof ResourceInterface) {
                    $resourceParentId = $parent->getResourceId();
                } else {
                    $resourceParentId = $parent;
                }
                $resourceParent = $this->get($resourceParentId);
            } catch (Exception $e) {
                throw new Exception("Parent Resource id '$resourceParentId' does not exist", 0, $e);
            }
            $this->resources[$resourceParentId]['children'][$resourceId] = $resource;
        }

        $this->resources[$resourceId] = [
            'instance' => $resource,
            'parent' => $resourceParent,
            'children' => [],
        ];

        return $this;
    }

    public function get(ResourceInterface|string $resource): ResourceInterface
    {
        if ($resource instanceof ResourceInterface) {
            $resourceId = $resource->getResourceId();
        } else {
            $resourceId = (string) $resource;
        }

        if (!$this->has($resource)) {
            throw new Exception("Resource '$resourceId' not found");
        }

        return $this->resources[$resourceId]['instance'];
    }

    public function has(ResourceInterface|string $resource): bool
    {
        if ($resource instanceof ResourceInterface) {
            $resourceId = $resource->getResourceId();
        } else {
            $resourceId = (string) $resource;
        }

        return isset($this->resources[$resourceId]);
    }

    /**
     * Returns true if and only if $resource inherits from $inherit.
     *
     * Both parameters may be either a Resource or a Resource identifier. If
     * $onlyParent is true, then $resource must inherit directly from
     * $inherit in order to return true. By default, this method looks
     * through the entire inheritance tree to determine whether $resource
     * inherits from $inherit through its ancestor Resources.
     */
    public function inherits(ResourceInterface|string $resource, ResourceInterface|string $inherit, bool $onlyParent = false): bool
    {
        try {
            $resourceId = $this->get($resource)->getResourceId();
            $inheritId = $this->get($inherit)->getResourceId();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        if (null !== $this->resources[$resourceId]['parent']) {
            $parentId = $this->resources[$resourceId]['parent']->getResourceId();
            if ($inheritId === $parentId) {
                return true;
            } elseif ($onlyParent) {
                return false;
            }
        } else {
            return false;
        }

        while (null !== $this->resources[$parentId]['parent']) {
            $parentId = $this->resources[$parentId]['parent']->getResourceId();
            if ($inheritId === $parentId) {
                return true;
            }
        }

        return false;
    }

    public function remove(ResourceInterface|string $resource): static
    {
        try {
            $resourceId = $this->get($resource)->getResourceId();
        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode(), $e);
        }

        $resourcesRemoved = [$resourceId];
        if (null !== ($resourceParent = $this->resources[$resourceId]['parent'])) {
            unset($this->resources[$resourceParent->getResourceId()]['children'][$resourceId]);
        }
        foreach ($this->resources[$resourceId]['children'] as $childId => $child) {
            $this->remove($childId);
            $resourcesRemoved[] = $childId;
        }

        foreach ($resourcesRemoved as $resourceIdRemoved) {
            foreach ($this->rules['byResourceId'] as $resourceIdCurrent => $rules) {
                if ($resourceIdRemoved === $resourceIdCurrent) {
                    unset($this->rules['byResourceId'][$resourceIdCurrent]);
                }
            }
        }

        unset($this->resources[$resourceId]);

        return $this;
    }

    public function removeAll(): static
    {
        foreach ($this->resources as $resourceId => $resource) {
            foreach ($this->rules['byResourceId'] as $resourceIdCurrent => $rules) {
                if ($resourceId === $resourceIdCurrent) {
                    unset($this->rules['byResourceId'][$resourceIdCurrent]);
                }
            }
        }

        $this->resources = [];

        return $this;
    }

    /**
     * Adds an "allow" rule to the ACL.
     *
     * @param RoleInterface|RoleInterface[]|string|array|null         $roles
     * @param ResourceInterface|ResourceInterface[]|string|array|null $resources
     */
    public function allow(RoleInterface|string|array|null $roles = null, ResourceInterface|string|array|null $resources = null, string|array|null $privileges = null, ?AssertInterface $assert = null): static
    {
        return $this->setRule(self::OP_ADD, self::TYPE_ALLOW, $roles, $resources, $privileges, $assert);
    }

    /**
     * Adds a "deny" rule to the ACL.
     *
     * @param RoleInterface|RoleInterface[]|string|array|null         $roles
     * @param ResourceInterface|ResourceInterface[]|string|array|null $resources
     */
    public function deny(RoleInterface|string|array|null $roles = null, ResourceInterface|string|array|null $resources = null, string|array|null $privileges = null, ?AssertInterface $assert = null): static
    {
        return $this->setRule(self::OP_ADD, self::TYPE_DENY, $roles, $resources, $privileges, $assert);
    }

    /**
     * Removes "allow" permissions from the ACL.
     *
     * @param RoleInterface|RoleInterface[]|string|array|null         $roles
     * @param ResourceInterface|ResourceInterface[]|string|array|null $resources
     */
    public function removeAllow(RoleInterface|string|array|null $roles = null, ResourceInterface|string|array|null $resources = null, string|array|null $privileges = null): static
    {
        return $this->setRule(self::OP_REMOVE, self::TYPE_ALLOW, $roles, $resources, $privileges);
    }

    /**
     * Removes "deny" restrictions from the ACL.
     *
     * @param RoleInterface|RoleInterface[]|string|array|null         $roles
     * @param ResourceInterface|ResourceInterface[]|string|array|null $resources
     */
    public function removeDeny(RoleInterface|string|array|null $roles = null, ResourceInterface|string|array|null $resources = null, string|array|null $privileges = null): static
    {
        return $this->setRule(self::OP_REMOVE, self::TYPE_DENY, $roles, $resources, $privileges);
    }

    /**
     * Performs operations on ACL rules.
     *
     * The $operation parameter may be either OP_ADD or OP_REMOVE, depending on whether the
     * user wants to add or remove a rule, respectively:
     *
     * OP_ADD specifics:
     *
     *      A rule is added that would allow one or more Roles access to [certain $privileges
     *      upon] the specified Resource(s).
     *
     * OP_REMOVE specifics:
     *
     *      The rule is removed only in the context of the given Roles, Resources, and privileges.
     *      Existing rules to which the remove operation does not apply would remain in the
     *      ACL.
     *
     * The $type parameter may be either TYPE_ALLOW or TYPE_DENY, depending on whether the
     * rule is intended to allow or deny permission, respectively.
     *
     * The $roles and $resources parameters may be references to, or the string identifiers for,
     * existing Resources/Roles, or they may be passed as arrays of these - mixing string identifiers
     * and objects is ok - to indicate the Resources and Roles to which the rule applies. If either
     * $roles or $resources is null, then the rule applies to all Roles or all Resources, respectively.
     * Both may be null in order to work with the default rule of the ACL.
     *
     * The $privileges parameter may be used to further specify that the rule applies only
     * to certain privileges upon the Resource(s) in question. This may be specified to be a single
     * privilege with a string, and multiple privileges may be specified as an array of strings.
     *
     * If $assert is provided, then its assert() method must return true in order for
     * the rule to apply. If $assert is provided with $roles, $resources, and $privileges all
     * equal to null, then a rule having a type of:
     *
     *      TYPE_ALLOW will imply a type of TYPE_DENY, and
     *
     *      TYPE_DENY will imply a type of TYPE_ALLOW
     *
     * when the rule's assertion fails. This is because the ACL needs to provide expected
     * behavior when an assertion upon the default ACL rule fails.
     *
     * @param RoleInterface|RoleInterface[]|string|array|null         $roles
     * @param ResourceInterface|ResourceInterface[]|string|array|null $resources
     */
    public function setRule(string $operation, string $type, RoleInterface|string|array|null $roles = null, ResourceInterface|string|array|null $resources = null, string|array|null $privileges = null, ?AssertInterface $assert = null): static
    {
        // ensure that the rule type is valid; normalize input to uppercase
        $type = mb_strtoupper($type);
        if (self::TYPE_ALLOW !== $type && self::TYPE_DENY !== $type) {
            throw new Exception("Unsupported rule type; must be either '".self::TYPE_ALLOW."' or '".self::TYPE_DENY."'");
        }

        // ensure that all specified Roles exist; normalize input to array of Role objects or null
        if (!\is_array($roles)) {
            $roles = [$roles];
        } elseif (0 === \count($roles)) {
            $roles = [null];
        }
        $rolesTemp = $roles;
        $roles = [];
        foreach ($rolesTemp as $role) {
            if (null !== $role) {
                $roles[] = $this->_getRoleRegistry()->get($role);
            } else {
                $roles[] = null;
            }
        }
        unset($rolesTemp);

        $allResources = []; // this might be used later if resource iteration is required
        // ensure that all specified Resources exist; normalize input to array of Resource objects or null
        if (null !== $resources) {
            if (!\is_array($resources)) {
                $resources = [$resources];
            } elseif (0 === \count($resources)) {
                $resources = [null];
            }
            $resourcesTemp = $resources;
            $resources = [];
            foreach ($resourcesTemp as $resource) {
                if (null !== $resource) {
                    $resources[] = $this->get($resource);
                } else {
                    $resources[] = null;
                }
            }
            unset($resourcesTemp, $resource);
        } else {
            foreach ($this->resources as $rTarget) {
                $allResources[] = $rTarget['instance'];
            }
            unset($rTarget);
        }

        // normalize privileges to array
        if (null === $privileges) {
            $privileges = [];
        } elseif (!\is_array($privileges)) {
            $privileges = [$privileges];
        }

        switch ($operation) {
            // add to the rules
            case self::OP_ADD:
                if (null !== $resources) {
                    // this block will iterate the provided resources
                    foreach ($resources as $resource) {
                        foreach ($roles as $role) {
                            $rules = &$this->_getRules($resource, $role, true);
                            if (0 === \count($privileges)) {
                                $rules['allPrivileges']['type'] = $type;
                                $rules['allPrivileges']['assert'] = $assert;
                                if (!isset($rules['byPrivilegeId'])) {
                                    $rules['byPrivilegeId'] = [];
                                }
                            } else {
                                foreach ($privileges as $privilege) {
                                    $rules['byPrivilegeId'][$privilege]['type'] = $type;
                                    $rules['byPrivilegeId'][$privilege]['assert'] = $assert;
                                }
                            }
                        }
                    }
                } else {
                    // this block will apply to all resources in a global rule
                    foreach ($roles as $role) {
                        $rules = &$this->_getRules(null, $role, true);
                        if (0 === \count($privileges)) {
                            $rules['allPrivileges']['type'] = $type;
                            $rules['allPrivileges']['assert'] = $assert;
                        } else {
                            foreach ($privileges as $privilege) {
                                $rules['byPrivilegeId'][$privilege]['type'] = $type;
                                $rules['byPrivilegeId'][$privilege]['assert'] = $assert;
                            }
                        }
                    }
                }
                break;

                // remove from the rules
            case self::OP_REMOVE:
                if (null !== $resources) {
                    // this block will iterate the provided resources
                    foreach ($resources as $resource) {
                        foreach ($roles as $role) {
                            $rules = &$this->_getRules($resource, $role);
                            if (null === $rules) {
                                continue;
                            }
                            if (0 === \count($privileges)) {
                                if (null === $resource && null === $role) {
                                    if ($type === $rules['allPrivileges']['type']) {
                                        $rules = [
                                            'allPrivileges' => [
                                                'type' => self::TYPE_DENY,
                                                'assert' => null,
                                            ],
                                            'byPrivilegeId' => [],
                                        ];
                                    }
                                    continue;
                                }

                                if (isset($rules['allPrivileges']['type'])
                                    && $type === $rules['allPrivileges']['type']) {
                                    unset($rules['allPrivileges']);
                                }
                            } else {
                                foreach ($privileges as $privilege) {
                                    if (isset($rules['byPrivilegeId'][$privilege])
                                        && $type === $rules['byPrivilegeId'][$privilege]['type']) {
                                        unset($rules['byPrivilegeId'][$privilege]);
                                    }
                                }
                            }
                        }
                    }
                } else {
                    // this block will apply to all resources in a global rule
                    foreach ($roles as $role) {
                        /*
                         * since null (all resources) was passed to this setRule() call, we need
                         * clean up all the rules for the global allResources, as well as the individually
                         * set resources (per privilege as well)
                         */
                        foreach (array_merge([null], $allResources) as $resource) {
                            $rules = &$this->_getRules($resource, $role, true);
                            if (null === $rules) {
                                continue;
                            }
                            if (0 === \count($privileges)) {
                                if (null === $role) {
                                    if ($type === $rules['allPrivileges']['type']) {
                                        $rules = [
                                            'allPrivileges' => [
                                                'type' => self::TYPE_DENY,
                                                'assert' => null,
                                            ],
                                            'byPrivilegeId' => [],
                                        ];
                                    }
                                    continue;
                                }

                                if (isset($rules['allPrivileges']['type']) && $type === $rules['allPrivileges']['type']) {
                                    unset($rules['allPrivileges']);
                                }
                            } else {
                                foreach ($privileges as $privilege) {
                                    if (isset($rules['byPrivilegeId'][$privilege])
                                        && $type === $rules['byPrivilegeId'][$privilege]['type']) {
                                        unset($rules['byPrivilegeId'][$privilege]);
                                    }
                                }
                            }
                        }
                    }
                }
                break;

            default:
                throw new Exception("Unsupported operation; must be either '".self::OP_ADD."' or '".self::OP_REMOVE."'");
        }

        return $this;
    }

    /**
     * Returns true if and only if the Role has access to the Resource.
     *
     * The $role and $resource parameters may be references to, or the string identifiers for,
     * an existing Resource and Role combination.
     *
     * If either $role or $resource is null, then the query applies to all Roles or all Resources,
     * respectively. Both may be null to query whether the ACL has a "blacklist" rule
     * (allow everything to all). By default, Manager creates a "whitelist" rule (deny
     * everything to all), and this method would return false unless this default has
     * been overridden (i.e., by executing $acl->allow()).
     *
     * If a $privilege is not provided, then this method returns false if and only if the
     * Role is denied access to at least one privilege upon the Resource. In other words, this
     * method returns true if and only if the Role is allowed all privileges on the Resource.
     *
     * This method checks Role inheritance using a depth-first traversal of the Role registry.
     * The highest priority parent (i.e., the parent most recently added) is checked first,
     * and its respective parents are checked similarly before the lower-priority parents of
     * the Role are checked.
     */
    public function isAllowed(RoleInterface|string|null $role = null, ResourceInterface|string|null $resource = null, ?string $privilege = null): bool
    {
        // reset role & resource to null
        $this->isAllowedRole = null;
        $this->isAllowedResource = null;
        $this->isAllowedPrivilege = null;

        if (null !== $role) {
            // keep track of originally called role
            $this->isAllowedRole = $role;
            $role = $this->_getRoleRegistry()->get($role);
            if (!$this->isAllowedRole instanceof RoleInterface) {
                $this->isAllowedRole = $role;
            }
        }

        if (null !== $resource) {
            // keep track of originally called resource
            $this->isAllowedResource = $resource;
            $resource = $this->get($resource);
            if (!$this->isAllowedResource instanceof ResourceInterface) {
                $this->isAllowedResource = $resource;
            }
        }

        if (null === $privilege) {
            // query on all privileges
            while (true) {
                // depth-first search on $role if it is not 'allRoles' pseudo-parent
                if (null !== $role && null !== ($result = $this->_roleDFSAllPrivileges($role, $resource))) {
                    return $result;
                }

                // look for rule on 'allRoles' pseudo-parent
                if (null !== ($rules = $this->_getRules($resource))) {
                    if (array_any($rules['byPrivilegeId'], fn ($rule, $privilege) => self::TYPE_DENY === $this->_getRuleType($resource, null, $privilege))) {
                        return false;
                    }
                    if (null !== ($ruleTypeAllPrivileges = $this->_getRuleType($resource))) {
                        return self::TYPE_ALLOW === $ruleTypeAllPrivileges;
                    }
                }

                // try next Resource
                $resource = $this->resources[$resource->getResourceId()]['parent'];
            }   // loop terminates at 'allResources' pseudo-parent
        } else {
            $this->isAllowedPrivilege = $privilege;
            // query on one privilege
            while (true) {
                // depth-first search on $role if it is not 'allRoles' pseudo-parent
                if (null !== $role && null !== ($result = $this->_roleDFSOnePrivilege($role, $resource, $privilege))) {
                    return $result;
                }

                // look for rule on 'allRoles' pseudo-parent
                if (null !== ($ruleType = $this->_getRuleType($resource, null, $privilege))) {
                    return self::TYPE_ALLOW === $ruleType;
                } elseif (null !== ($ruleTypeAllPrivileges = $this->_getRuleType($resource))) {
                    return self::TYPE_ALLOW === $ruleTypeAllPrivileges;
                }

                // try next Resource
                $resource = $this->resources[$resource->getResourceId()]['parent'];
            }   // loop terminates at 'allResources' pseudo-parent
        }
    }

    /**
     * Returns the Role registry for this ACL.
     *
     * If no Role registry has been created yet, a new default Role registry
     * is created and returned.
     */
    protected function _getRoleRegistry(): RoleRegistry
    {
        if (null === $this->_roleRegistry) {
            $this->_roleRegistry = new RoleRegistry();
        }

        return $this->_roleRegistry;
    }

    /**
     * Performs a depth-first search of the Role DAG, starting at $role, in order to find a rule
     * allowing/denying $role access to all privileges upon $resource.
     *
     * This method returns true if a rule is found and allows access. If a rule exists and denies access,
     * then this method returns false. If no applicable rule is found, then this method returns null.
     */
    protected function _roleDFSAllPrivileges(RoleInterface $role, ?ResourceInterface $resource = null): ?bool
    {
        $dfs = [
            'visited' => [],
            'stack' => [],
        ];

        if (null !== ($result = $this->_roleDFSVisitAllPrivileges($role, $resource, $dfs))) {
            return $result;
        }

        while (null !== ($role = array_pop($dfs['stack']))) {
            if (!isset($dfs['visited'][$role->getRoleId()])) {
                if (null !== ($result = $this->_roleDFSVisitAllPrivileges($role, $resource, $dfs))) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Visits an $role in order to look for a rule allowing/denying $role access to all privileges upon $resource.
     *
     * This method returns true if a rule is found and allows access. If a rule exists and denies access,
     * then this method returns false. If no applicable rule is found, then this method returns null.
     *
     * This method is used by the internal depth-first search algorithm and may modify the DFS data structure.
     */
    protected function _roleDFSVisitAllPrivileges(RoleInterface $role, ?ResourceInterface $resource = null, ?array &$dfs = null): ?bool
    {
        if (null === $dfs) {
            throw new Exception('$dfs parameter may not be null');
        }

        if (null !== ($rules = $this->_getRules($resource, $role))) {
            if (array_any($rules['byPrivilegeId'], fn ($rule, $privilege) => self::TYPE_DENY === $this->_getRuleType($resource, $role, $privilege))) {
                return false;
            }
            if (null !== ($ruleTypeAllPrivileges = $this->_getRuleType($resource, $role))) {
                return self::TYPE_ALLOW === $ruleTypeAllPrivileges;
            }
        }

        $dfs['visited'][$role->getRoleId()] = true;
        foreach ($this->_getRoleRegistry()->getParents($role) as $roleParent) {
            $dfs['stack'][] = $roleParent;
        }

        return null;
    }

    /**
     * Performs a depth-first search of the Role DAG, starting at $role, in order to find a rule
     * allowing/denying $role access to a $privilege upon $resource.
     *
     * This method returns true if a rule is found and allows access. If a rule exists and denies access,
     * then this method returns false. If no applicable rule is found, then this method returns null.
     */
    protected function _roleDFSOnePrivilege(RoleInterface $role, ?ResourceInterface $resource = null, ?string $privilege = null): ?bool
    {
        if (null === $privilege) {
            throw new Exception('$privilege parameter may not be null');
        }

        $dfs = [
            'visited' => [],
            'stack' => [],
        ];

        if (null !== ($result = $this->_roleDFSVisitOnePrivilege($role, $resource, $privilege, $dfs))) {
            return $result;
        }

        while (null !== ($role = array_pop($dfs['stack']))) {
            if (!isset($dfs['visited'][$role->getRoleId()])) {
                if (null !== ($result = $this->_roleDFSVisitOnePrivilege($role, $resource, $privilege, $dfs))) {
                    return $result;
                }
            }
        }

        return null;
    }

    /**
     * Visits an $role in order to look for a rule allowing/denying $role access to a $privilege upon $resource.
     *
     * This method returns true if a rule is found and allows access. If a rule exists and denies access,
     * then this method returns false. If no applicable rule is found, then this method returns null.
     *
     * This method is used by the internal depth-first search algorithm and may modify the DFS data structure.
     */
    protected function _roleDFSVisitOnePrivilege(RoleInterface $role, ?ResourceInterface $resource = null, ?string $privilege = null, ?array &$dfs = null): ?bool
    {
        if (null === $privilege) {
            throw new Exception('$privilege parameter may not be null');
        }

        if (null === $dfs) {
            throw new Exception('$dfs parameter may not be null');
        }

        if (null !== ($ruleTypeOnePrivilege = $this->_getRuleType($resource, $role, $privilege))) {
            return self::TYPE_ALLOW === $ruleTypeOnePrivilege;
        } elseif (null !== ($ruleTypeAllPrivileges = $this->_getRuleType($resource, $role))) {
            return self::TYPE_ALLOW === $ruleTypeAllPrivileges;
        }

        $dfs['visited'][$role->getRoleId()] = true;
        foreach ($this->_getRoleRegistry()->getParents($role) as $roleParent) {
            $dfs['stack'][] = $roleParent;
        }

        return null;
    }

    /**
     * Returns the rule type associated with the specified Resource, Role, and privilege
     * combination.
     *
     * If a rule does not exist or its attached assertion fails, which means that
     * the rule is not applicable, then this method returns null. Otherwise, the
     * rule type applies and is returned as either TYPE_ALLOW or TYPE_DENY.
     *
     * If $resource or $role is null, then this means that the rule must apply to
     * all Resources or Roles, respectively.
     *
     * If $privilege is null, then the rule must apply to all privileges.
     *
     * If all three parameters are null, then the default ACL rule type is returned,
     * based on whether its assertion method passes.
     */
    protected function _getRuleType(?ResourceInterface $resource = null, ?RoleInterface $role = null, ?string $privilege = null): ?string
    {
        // get the rules for the $resource and $role
        if (null === ($rules = $this->_getRules($resource, $role))) {
            return null;
        }

        // follow $privilege
        if (null === $privilege) {
            if (isset($rules['allPrivileges'])) {
                $rule = $rules['allPrivileges'];
            } else {
                return null;
            }
        } elseif (!isset($rules['byPrivilegeId'][$privilege])) {
            return null;
        } else {
            $rule = $rules['byPrivilegeId'][$privilege];
        }

        $assertionValue = true;
        // check assertion first
        if ($rule['assert']) {
            $assertion = $rule['assert'];
            $assertionValue = $assertion->assert(
                $this,
                ($this->isAllowedRole instanceof RoleInterface) ? $this->isAllowedRole : $role,
                ($this->isAllowedResource instanceof ResourceInterface) ? $this->isAllowedResource : $resource,
                $this->isAllowedPrivilege
            );
        }

        if (null === $rule['assert'] || $assertionValue) {
            return $rule['type'];
        } elseif (null !== $resource || null !== $role || null !== $privilege) {
            return null;
        } elseif (self::TYPE_ALLOW === $rule['type']) {
            return self::TYPE_DENY;
        }

        return self::TYPE_ALLOW;
    }

    /**
     * Returns the rules associated with a Resource and a Role, or null if no such rules exist.
     *
     * If either $resource or $role is null, this means that the rules returned are for all Resources or all Roles,
     * respectively. Both can be null to return the default rule set for all Resources and all Roles.
     *
     * If the $create parameter is true, then a rule set is first created and then returned to the caller.
     */
    protected function &_getRules(?ResourceInterface $resource = null, ?RoleInterface $role = null, bool $create = false): ?array
    {
        // create a reference to null
        $null = null;
        $nullRef = &$null;

        // follow $resource
        /* @noinspection PhpLoopNeverIteratesInspection */
        do {
            if (null === $resource) {
                $visitor = &$this->rules['allResources'];
                break;
            }
            $resourceId = $resource->getResourceId();
            if (!isset($this->rules['byResourceId'][$resourceId])) {
                if (!$create) {
                    return $nullRef;
                }
                $this->rules['byResourceId'][$resourceId] = [];
            }
            $visitor = &$this->rules['byResourceId'][$resourceId];
        } while (false);

        // follow $role
        if (null === $role) {
            if (!isset($visitor['allRoles'])) {
                if (!$create) {
                    return $nullRef;
                }
                $visitor['allRoles']['byPrivilegeId'] = [];
            }

            return $visitor['allRoles'];
        }
        $roleId = $role->getRoleId();
        if (!isset($visitor['byRoleId'][$roleId])) {
            if (!$create) {
                return $nullRef;
            }
            $visitor['byRoleId'][$roleId]['byPrivilegeId'] = [];
            $visitor['byRoleId'][$roleId]['allPrivileges'] = ['type' => null, 'assert' => null];
        }

        return $visitor['byRoleId'][$roleId];
    }

    /**
     * Returns an array of registered roles.
     *
     * Note that this method does not return instances of registered roles,
     * but only the role identifiers.
     *
     * @return string[] of registered roles
     */
    public function getRoles(): array
    {
        return array_keys($this->_getRoleRegistry()->getRoles());
    }

    /**
     * Returns an array of registered resources.
     *
     * Note that this method does not return instances of registered resources,
     * but only the resource identifiers.
     *
     * @return string[] of registered resources
     */
    public function getResources(): array
    {
        return array_keys($this->resources);
    }
}
