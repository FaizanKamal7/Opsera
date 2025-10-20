<?php

namespace App\Service;

use App\ACL;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Centralized service for building application navigation menus
 * 
 * This service provides a flexible way to define and filter menu links 
 * dynamically based on the current user's roles and permissions.
 */
class NavigationBuilder
{
    private Security $security;
    private ACL $acl;

    public function __construct(Security $security, ACL $acl)
    {
        $this->security = $security;
        $this->acl = $acl;
    }

    /**
     * Generate user menu links based on user roles with role hierarchy
     * 
     * @return array
     */
    public function generateUserMenuLinks(): array
    {
        $user = $this->security->getUser();
        $links = [];

        if (!$user) {
            return $links;
        }

        // Define links with their specific roles
        $roleLinkConfigurations = [
            ACL::ROLE_USER => [
                [
                    'label' => 'Profile',
                    'route' => 'profile_edit',
                    'icon' => 'user',
                    'roles' => [ACL::ROLE_USER]
                ],
                [
                    'label' => 'Change Password',
                    'route' => 'user_change_password',
                    'icon' => 'key',
                    'roles' => [ACL::ROLE_USER]
                ]
            ],
            ACL::ROLE_ADMIN => [
                [
                    'label' => 'User Management',
                    'route' => 'homepage',
                    'icon' => 'users',
                    'roles' => [ACL::ROLE_ADMIN]
                ]
            ],
            ACL::ROLE_SUPER_ADMIN => [
                [
                    'label' => 'System Settings',
                    'route' => 'homepage',
                    'icon' => 'cogs',
                    'roles' => [ACL::ROLE_SUPER_ADMIN]
                ]
            ]
        ];

        // Collect links for the user's roles and their inherited roles
        $userRoles = $user->getRoles();
        foreach ($userRoles as $role) {
            // Collect links for the current role and all inherited roles
            foreach ($roleLinkConfigurations as $configRole => $roleLinks) {
                if ($role === $configRole || $this->acl->inheritsRole($role, $configRole)) {
                    foreach ($roleLinks as $link) {
                        if ($this->isLinkAllowed($link, $userRoles)) {
                            // Prevent duplicate links
                            $linkKey = $link['route'];
                            $links[$linkKey] = $link;
                        }
                    }
                }
            }
        }

        // Convert links back to a numeric array and sort
        return array_values($links);
    }

    /**
     * Check if a link is allowed based on user roles
     * 
     * @param array $link
     * @param array $userRoles
     * @return bool
     */
    private function isLinkAllowed(array $link, array $userRoles): bool
    {
        $allowedRoles = $link['roles'] ?? [];

        // Check if any of the link's roles match or are inherited by user roles
        foreach ($allowedRoles as $allowedRole) {
            foreach ($userRoles as $userRole) {
                if ($userRole === $allowedRole || $this->acl->inheritsRole($userRole, $allowedRole)) {
                    return true;
                }
            }
        }

        return false;
    }
}
