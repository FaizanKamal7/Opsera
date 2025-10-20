<?php

declare(strict_types=1);

namespace App\Controller;

use App\ACL;
use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Form\UserType;
use App\Translation\ControllerTranslationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Controller used to manage current user. The #[CurrentUser] attribute
 * tells Symfony to inject the currently logged user into the given argument.
 * It can only be used in controllers and it's an alternative to the
 * $this->getUser() method, which still works inside controllers.
 */
#[Route('/profile'), IsGranted(ACL::ROLE_USER)]
final class UserProfileController extends AbstractController
{
    use ControllerTranslationTrait;

    #[IsGranted(ACL::R_USER_PROFILE)]
    #[Route(name: 'profile_edit', methods: ['GET', 'POST'])]
    public function edit(
        #[CurrentUser] User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        RequestStack $requestStack,
    ): Response {
        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $requestStack->getSession()->set('_locale', $user->getLanguage());

            $this->flashSuccess('user.profile.updated');

            return $this->redirectToRoute('profile_edit', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('user-profile/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    #[IsGranted(ACL::R_USER_CHANGE_OWN_PASSWORD)]
    #[Route('/change-password', name: 'user_change_password', methods: ['GET', 'POST'])]
    public function changePassword(
        #[CurrentUser] User $user,
        Request $request,
        EntityManagerInterface $entityManager,
        Security $security,
    ): Response {
        $form = $this->createForm(ChangePasswordType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->flashWarning('user.profile.password_changed');

            // The logout method applies an automatic protection against CSRF attacks;
            // it's explicitly disabled here because the form already has a CSRF token validated.
            return $security->logout(validateCsrfToken: false) ?? $this->redirectToRoute('homepage');
        }

        return $this->render('user-profile/change_password.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route(path: '/ui/dark-mode', name: 'ui_use_dark_mode')]
    public function themeDark(RequestStack $requestStack): Response
    {
        $requestStack->getSession()->set('theme', 'dark');

        return $this->redirectToRoute('homepage');
    }

    #[Route(path: '/ui/light-mode', name: 'ui_light_mode')]
    public function themeLight(RequestStack $requestStack): Response
    {
        $requestStack->getSession()->remove('theme');

        return $this->redirectToRoute('homepage');
    }
}
