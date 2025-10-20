<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

/**
 * Functional test for the controllers defined inside the UserProfileController used
 * for managing the current logged user.
 *
 * See https://symfony.com/doc/current/testing.html#functional-tests
 *
 * Whenever you test resources protected by a firewall, consider using the
 * technique explained in:
 * https://symfony.com/doc/current/testing/http_authentication.html
 *
 * Execute the application tests using this command (requires PHPUnit to be installed):
 *
 *     $ cd your-symfony-project/
 *     $ ./vendor/bin/phpunit
 */
final class UserProfileControllerTest extends WebTestCase
{
    #[DataProvider('getUrlsForAnonymousUsers')]
    public function testAccessDeniedForAnonymousUsers(string $httpMethod, string $url): void
    {
        $client = static::createClient();
        $client->request($httpMethod, $url);

        $this->assertResponseRedirects(
            'http://localhost/login',
            Response::HTTP_FOUND,
            \sprintf('The %s secure URL redirects to the login form.', $url)
        );
    }

    public static function getUrlsForAnonymousUsers(): \Generator
    {
        yield ['GET', '/profile'];
        yield ['GET', '/profile/change-password'];
    }

    public function testEditUserProfile(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = $client->getContainer()->get(UserRepository::class);

        /** @var User $user */
        $user = $userRepository->findOneByUsername('user1');

        $newUserEmail = 'admin_jane@symfony.com';

        $client->loginUser($user);

        $client->request('GET', '/profile');
        $client->submitForm('Save changes', [
            'user[email]' => $newUserEmail,
        ]);

        $this->assertResponseRedirects('/profile', Response::HTTP_SEE_OTHER);

        $user = $userRepository->findOneByEmail($newUserEmail);

        $this->assertNotNull($user);
        $this->assertSame($newUserEmail, $user->getEmail());
    }

    public function testChangePassword(): void
    {
        $client = static::createClient();

        /** @var UserRepository $userRepository */
        $userRepository = $client->getContainer()->get(UserRepository::class);

        /** @var User $user */
        $user = $userRepository->findOneByUsername('user1');

        $newUserPassword = 'new-password';

        $client->loginUser($user);
        $client->request('GET', '/profile/change-password');
        $client->submitForm('Change password', [
            'change_password[currentPassword]' => '123456',
            'change_password[newPassword][first]' => $newUserPassword,
            'change_password[newPassword][second]' => $newUserPassword,
        ]);

        $this->assertResponseRedirects(
            '/login',
            Response::HTTP_FOUND,
            'Changing password logout the user.'
        );

        $client->request('GET', '/');

        $this->assertResponseRedirects(
            '/login',
            Response::HTTP_FOUND,
            'New requests after changing password are redirected to the login form.',
        );
    }
}
