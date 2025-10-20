<?php

declare(strict_types=1);

namespace App\Controller;

use App\ACL;
use App\Entity\User;
use App\Translation\ControllerTranslationTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(ACL::ROLE_ADMIN)]
final class UsersController extends AbstractController
{
    use ControllerTranslationTrait;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/users', name: 'app_users_index')]
    public function index(): Response
    {
        return $this->render('users/index.html.twig');
    }

    #[Route('/users/new', name: 'app_users_add')]
    public function add(): Response
    {
        throw new \LogicException('Not implemented yet');
        //        return $this->render('users/edit.html.twig');
    }

    #[Route('/users/edit/{id}', name: 'app_users_edit')]
    public function edit(User $user): Response
    {
        throw new \LogicException('Not implemented yet');
        //        return $this->render('users/edit.html.twig');
    }

    #[Route('/users/delete/{id}', name: 'app_users_delete')]
    public function delete(User $user): Response
    {
        $this->entityManager->remove($user);
        $this->entityManager->flush();
        $this->flashSuccess('app.users.delete.result.successful');

        return $this->redirectToRoute('app_users_index');
    }

    #[Route('/users/deactivate/{user}', name: 'app_users_deactivate')]
    public function deactivate(Request $request, ?User $user = null): Response
    {
        $list = $this->handleUserActivationRequest(false, $request, $user);

        if (0 === \count($list)) {
            $this->flashWarning('app.users.deactivate.none');
        } elseif (1 === \count($list)) {
            $user ??= reset($list);
            $this->flashSuccess('app.users.deactivate.success', ['%username%' => $user->getUsername() ?? '?', '%count%' => 1]);
        } else {
            $this->flashSuccess('app.users.deactivate.success', ['%count%' => \count($list)]);
        }

        return $this->redirectToRoute('app_users_index');
    }

    #[Route('/users/activate/{user}', name: 'app_users_activate')]
    public function activate(Request $request, ?User $user = null): Response
    {
        $list = $this->handleUserActivationRequest(true, $request, $user);

        if (0 === \count($list)) {
            $this->flashWarning('app.users.activate.none');
        } elseif (1 === \count($list)) {
            $user ??= reset($list);
            $this->flashSuccess('app.users.activate.success', ['%username%' => $user->getUsername() ?? '?', '%count%' => 1]);
        } else {
            $this->flashSuccess('app.users.activate.success', ['%count%' => \count($list)]);
        }

        return $this->redirectToRoute('app_users_index');
    }

    /**
     * @return User[]
     */
    private function handleUserActivationRequest(bool $activeState, Request $request, ?User $user = null): array
    {
        $list = [];
        if (null !== $user) {
            $list[] = $user;
        } else {
            $list = $request->request->all('ids');
            $list = array_filter($list, is_numeric(...));
            $list = array_filter(array_map(fn ($id) => $this->entityManager->getRepository(User::class)->find($id), $list));
        }

        $this->entityManager->wrapInTransaction(function () use ($list, $activeState) {
            array_walk($list, fn (User $u) => $u->setActive($activeState));
            array_walk($list, $this->entityManager->persist(...));
        });

        return array_values($list);
    }
}
