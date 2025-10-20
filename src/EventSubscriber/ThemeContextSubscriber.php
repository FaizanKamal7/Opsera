<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use KevinPapst\TablerBundle\Helper\ContextHelper;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final readonly class ThemeContextSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ContextHelper $contextHelper,
        private RequestStack $requestStack
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['prepareEnvironment', -100],
        ];
    }

    public function prepareEnvironment(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $theme = (string) $this->requestStack->getSession()->get('theme') ?: null;
        if ($theme) {
            $this->contextHelper->setIsDarkMode('dark' === $theme);
        }
    }
}
