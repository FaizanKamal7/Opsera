<?php

declare(strict_types=1);

namespace App;

use App\Utils\ACL\AbstractACL;
use App\Utils\ACL\Manager;

final class ACL extends AbstractACL
{
    public const string ROLE_SUPER_ADMIN = 'ROLE_SUPER_ADMIN';
    public const string ROLE_ADMIN = 'ROLE_ADMIN';
    public const string ROLE_USER = 'ROLE_USER';

    public const string IS_AUTHENTICATED = 'IS_AUTHENTICATED';
    public const string IS_AUTHENTICATED_FULLY = 'IS_AUTHENTICATED_FULLY';
    public const string IS_REMEMBERED = 'IS_REMEMBERED';
    public const string PUBLIC_ACCESS = 'PUBLIC_ACCESS';

    public const string R_USER_PROFILE = 'R_USER_PROFILE';
    public const string R_USER_CHANGE_OWN_PASSWORD = 'R_USER_CHANGE_OWN_PASSWORD';

    protected static array $config = [
        'roles' => [
            self::ROLE_USER => [
                'name' => 'User',
                'inherits' => [],
            ],
            self::ROLE_ADMIN => [
                'name' => 'Admin',
                'inherits' => [self::ROLE_USER],
            ],
            self::ROLE_SUPER_ADMIN => [
                'name' => 'Super admin',
                'inherits' => [self::ROLE_ADMIN],
            ],
        ],
        'resources' => [
            self::R_USER_PROFILE => [
                'name' => 'User profile',
                'children' => [
                    self::R_USER_CHANGE_OWN_PASSWORD => [
                        'name' => 'Change password',
                    ],
                ],
            ],
        ],
    ];

    protected function loadRules(Manager $acl): void
    {
        $acl->deny();

        $acl->allow(self::ROLE_USER, self::R_USER_PROFILE);
    }
}
