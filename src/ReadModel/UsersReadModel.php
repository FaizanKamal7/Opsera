<?php

declare(strict_types=1);

namespace App\ReadModel;

use App\Utils\Doctrine\DataSource;
use App\Utils\Doctrine\DataSourceBuilder;
use App\Utils\Doctrine\DoctrineReadDataProvider;
use App\Utils\Domain\ReadModel\ReadDataProviderInterface;
use Doctrine\DBAL\Connection;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * @implements ReadDataProviderInterface<array{
 *     id: string,
 *     username: string,
 *     active: string,
 *     full_name: string,
 *     language: string,
 *     roles: string,
 * }>
 */
#[Autoconfigure(shared: false)]
final class UsersReadModel implements ReadDataProviderInterface
{
    use DoctrineReadDataProvider;

    public const string FIELD_ID = 'id';
    public const string FIELD_USERNAME = 'username';
    // public const string FIELD_PASSWORD = 'password';
    public const string FIELD_ACTIVE = 'active';
    public const string FIELD_LANGUAGE = 'language';
    public const string FIELD_ROLES = 'roles';
    public const string FIELD_FULL_NAME = 'full_name';
    // public const string FIELD_EMAIL = 'email';

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    protected function createDataSource(): DataSource
    {
        return new DataSourceBuilder()
            ->withData(<<<'SQL'
                select r.* from (
                    select
                        t.id as "id",
                        t.username as "username",
                        t.full_name as "full_name",
                        t.active as "active",
                        t.language as "language",
                        t.roles as "roles"
                    from users t
                ) r
                /*#WHERE#*/
                /*#ORDERBY#*/
            SQL)
            ->create($this->connection);
    }
}
