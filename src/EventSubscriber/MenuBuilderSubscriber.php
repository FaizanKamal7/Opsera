<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use KevinPapst\TablerBundle\Event\MenuEvent;
use KevinPapst\TablerBundle\Model\MenuItemInterface;
use KevinPapst\TablerBundle\Model\MenuItemModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Webmozart\Assert\Assert;

// use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final readonly class MenuBuilderSubscriber implements EventSubscriberInterface
{
    public function __construct() {}

    public function onSetupNavbar(MenuEvent $event): void
    {
        $administration = new MenuItemModel('administration', 'Administration', null, [], 'fas fa-screwdriver-wrench');
        $administration->addChild(new MenuItemModel('users', 'title.users_index', 'app_users_index', [], 'fas fa-users'));
        $modules = new MenuItemModel('modules', 'Modules', null, [], 'fas fa-screwdriver-wrench');
        $modules->addChild(new MenuItemModel('url_shortner', 'title.url_shortner', 'app_url_shortner', [], 'fas fa-users'));

        $event->addItem(new MenuItemModel('homepage', 'homepage', 'app_admin_dashboard', [], 'homepage'));
        $event->addItem($administration);
        $event->addItem($modules);
    }

    public function onActivateNavbar(MenuEvent $event): void
    {
        $this->activateMenuItems($event);
    }

    /**
     * @param MenuItemInterface[]|null $items
     */
    private function activateMenuItems(MenuEvent $event, ?array $items = null, ?string $route = null): void
    {
        $items = $items ?? $event->getItems();
        $route = $route ?? $this->getRoute($event);
        foreach ($items as $item) {
            if ($item->hasChildren()) {
                $this->activateMenuItems($event, $item->getChildren(), $route);
            } elseif ($item->getRoute() === $route) {
                $item->setIsActive(true);
            }
        }
    }

    private function getRoute(MenuEvent $event): string
    {
        $request = $event->getRequest();
        $route = (string) $request->attributes->get('_route') ?: null;
        $route = $route ?: (string) $request->query->all()['_route'] ?: null;
        $route = $route ?: (string) $request->request->all()['_route'] ?: null;
        Assert::stringNotEmpty($route, 'Can not determine the current route');

        return $route;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            MenuEvent::class => [
                ['onSetupNavbar', 100],
                ['onActivateNavbar', 99],
            ],
        ];
    }
}
