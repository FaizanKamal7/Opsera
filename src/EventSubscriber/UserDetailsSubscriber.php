<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\ACL;
use App\Entity\User;
use KevinPapst\TablerBundle\Event\UserDetailsEvent;
use KevinPapst\TablerBundle\Model\MenuItemModel;
use KevinPapst\TablerBundle\Model\UserModel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webmozart\Assert\Assert;

final readonly class UserDetailsSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Security $security,
        private ACL $acl,
    ) {
    }

    public function onShowUser(UserDetailsEvent $event): void
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user) {
            return;
        }

        Assert::isInstanceOf($user, User::class);
        $userId = $user->getId();
        Assert::notNull($userId);

        $model = new UserModel('user_'.$userId, $user->getUserIdentifier());
        $model->setName($user->getFullName() ?? $user->getUsername() ?? 'UNKNOWN');
        $model->setTitle($this->formatUserTitle($user));
        // $model->setAvatar('bundles/tabler/images/default_avatar.png');

        $event->setUser($model);

        $event->addLink(new MenuItemModel('profile', 'title.user_profile', 'profile_edit', [], 'fas fa-user-cog'));
    }

    private function formatUserTitle(User $user): string
    {
        $rolesList = $this->acl->getRolesList();
        $rolesList = array_intersect_key($rolesList, array_flip($user->getRoles()));
        if (0 === \count($rolesList)) {
            return '';
        }
        ksort($rolesList);
        $title = [array_shift($rolesList)];
        if (\count($rolesList) > 0) {
            $crrLen = mb_strlen($title[0]);
            do {
                $role = array_shift($rolesList);
                $crrLen += mb_strlen($role) + 3;
                $title[] = $role;
            } while (mb_strlen($user->getFullName() ?? '') > $crrLen && \count($rolesList) > 0);
        }

        return implode(' / ', $title).(\count($rolesList) > 0 ? ' + '.\count($rolesList) : '');
    }

    public static function getSubscribedEvents(): array
    {
        return [
            UserDetailsEvent::class => ['onShowUser', 100],
        ];
    }
}
