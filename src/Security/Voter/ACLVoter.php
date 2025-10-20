<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\ACL;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, string|object>
 */
#[Exclude]
final class ACLVoter extends Voter
{
    public function __construct(
        private readonly ACL $acl
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return !\in_array($attribute, [
            ACL::IS_AUTHENTICATED,
            ACL::IS_AUTHENTICATED_FULLY,
            ACL::IS_REMEMBERED,
            ACL::PUBLIC_ACCESS,
        ], true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        if (\in_array(ACL::ROLE_SUPER_ADMIN, $user->getRoles(), true)) {
            return true;
        }

        if (!$user->getActive()) {
            return false;
        }

        if (\is_string($subject)) {
            $resource = $subject;
            $privilege = $attribute;
        } else {
            $resource = $attribute;
            $privilege = null;
        }

        if (str_starts_with($resource, 'ROLE_')) {
            if (!array_any($user->getRoles(), fn ($role) => $role === $resource || $this->acl->inheritsRole($role, $resource))) {
                return false;
            }

            if (null !== $privilege) {
                return array_any($user->getRoles(), fn ($role) => $this->acl->isAllowed($role, $resource, $privilege));
            }

            return true;
        }

        return array_any($user->getRoles(), fn ($role) => $this->acl->isAllowed($role, $resource, $privilege));
    }
}
