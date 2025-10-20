<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\ACL;
use App\Components\UI\DataGrid\ColumnTemplate;
use App\Components\UI\DataGrid\ColumnType;
use App\Components\UI\DataGrid\Grid;
use App\Components\UI\DataGrid\GridBuilder;
use App\Form\UserType;
use App\ReadModel\UsersReadModel;
use App\Twig\Components\UI\DataGridInterface;
use App\Twig\Components\UI\DataGridTrait;
use App\Utils\Domain\ReadModel\FilterExpression;
use App\Utils\Domain\ReadModel\QueryExpression;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;

#[AsLiveComponent]
final class UsersDataGrid implements DataGridInterface
{
    use DataGridTrait;

    public function __construct(
        private readonly UsersReadModel $dataSource,
        /** @var string[] */
        private readonly array $enabledLocales,
        private readonly ACL $acl,
    ) {
    }

    public function setupDefaultQuery(QueryExpression $query): QueryExpression
    {
        $query = $query->sortBy('id', 'asc');

        return $query;
    }

    public function modifyQuery(QueryExpression $query): QueryExpression
    {
        return $query;
    }

    public function buildGrid(GridBuilder $gridBuilder): Grid
    {
        $userRolesList = array_map(fn ($t) => $this->trans($t), $this->acl->getRolesList());

        return $gridBuilder
            ->withData($this->dataSource)
            ->withColumn(
                $this->trans('ID'),
                UsersReadModel::FIELD_ID,
                ColumnType::NUMBER,
                width: 100
            )
            ->withColumn(
                $this->trans('Username'),
                UsersReadModel::FIELD_USERNAME,
                ColumnType::STRING,
                width: 200
            )
            ->withColumn(
                $this->trans('Full name'),
                UsersReadModel::FIELD_FULL_NAME,
                ColumnType::STRING
            )
            ->withColumn(
                $this->trans('Role'),
                UsersReadModel::FIELD_ROLES,
                ColumnType::STRING,
                template: function (string $value, array $row, string $json) use ($userRolesList) {
                    /** @var string[]|false $roles */
                    $roles = json_decode($json);
                    $roles = false === $roles ? [] : $roles;

                    return implode('', array_map(function ($role) use ($userRolesList) {
                        $color = 'default';
                        if (ACL::ROLE_SUPER_ADMIN === $role || $this->acl->inheritsRole($role, ACL::ROLE_SUPER_ADMIN)) {
                            $color = 'red';
                        } elseif (ACL::ROLE_ADMIN === $role || $this->acl->inheritsRole($role, ACL::ROLE_ADMIN)) {
                            $color = 'yellow';
                        }

                        return '<span class="badge badge-lg badge-outline text-'.$color.'"><i class="fa fa-user"></i> '.($userRolesList[$role] ?? $role).'</span>';
                    }, $roles));
                },
                params: [
                    'filter_operator' => FilterExpression::OP_CONTAINS,
                    'ignore_case' => false,
                ],
                values: array_combine(array_map(fn ($v) => '"'.$v.'"', array_keys($userRolesList)), array_values($userRolesList)),
            )
            ->withColumn(
                $this->trans('Active'),
                UsersReadModel::FIELD_ACTIVE,
                ColumnType::BOOLEAN,
                width: 150,
                template: ColumnTemplate::BOOLEAN_BADGE,
                values: [
                    0 => $this->trans('Inactive'),
                    1 => $this->trans('Active'),
                ],
            )
            ->withColumn(
                $this->trans('Language'),
                UsersReadModel::FIELD_LANGUAGE,
                ColumnType::STRING,
                width: 150,
                values: UserType::formatEnabledLanguagesList($this->enabledLocales),
            )
            ->withRowActions(fn (array $row) => [
                $gridBuilder->editRowAction($this->generateUrl('app_users_edit', ['id' => $row['id']])),
                $gridBuilder->removeRowAction($this->generateUrl('app_users_delete', ['id' => $row['id']])),
            ])
            ->withBatchAction(
                'activate',
                $this->trans('Activate'),
                $this->generateUrl('app_users_activate')
            )
            ->withBatchAction(
                'deactivate',
                $this->trans('Deactivate'),
                $this->generateUrl('app_users_deactivate')
            )
            ->create();
    }
}
