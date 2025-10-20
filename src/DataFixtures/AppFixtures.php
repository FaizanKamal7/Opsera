<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\ACL;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $this->loadUsers($manager);
    }

    private function loadUsers(ObjectManager $manager): void
    {
        foreach ($this->getUserData() as [$fullname, $username, $active, $password, $email, $language, $roles]) {
            $user = new User();
            $user->setFullName($fullname);
            $user->setUsername($username);
            $user->setActive($active);
            $user->setPassword($this->passwordHasher->hashPassword($user, $password));
            $user->setEmail($email);
            $user->setLanguage($language);
            $user->setRoles($roles);

            $manager->persist($user);

            $this->addReference($username, $user);
        }

        $manager->flush();
    }

    /**
     * @return array<array{string, string, bool, string, string, string, array<string>}>
     */
    private function getUserData(): array
    {
        return array_merge([
            // $fullname, $username, active, $password, $email, $language, $roles
            ['ExpertM', 'expertm', true, '111111', 'expertm@example.com', 'en', [ACL::ROLE_SUPER_ADMIN]],
            ['Administrator', 'admin', true, '123456', 'admin@example.com', 'en', [ACL::ROLE_ADMIN]],
            ['User 1', 'user1', true, '123456', 'user1@example.com', 'en', [ACL::ROLE_USER]],
        ]/*, array_map(function ($index) {
            return ['User '.$index, 'user'.$index, true, '123456', 'user'.$index.'@example.com', 'en', [ACL::ROLE_USER]];
        }, array_keys(array_fill(10, 151, '')))*/);
    }
}
