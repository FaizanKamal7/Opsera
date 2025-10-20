<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Webmozart\Assert\Assert;

final class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        /** @var string[] */
        private array $enabledLocales,
        private ?string $defaultLocale = null,
    ) {
        if (0 === \count($this->enabledLocales)) {
            throw new \UnexpectedValueException('The list of supported locales must not be empty.');
        }

        $this->defaultLocale = $defaultLocale ?? $this->enabledLocales[0];

        if (!\in_array($this->defaultLocale, $this->enabledLocales, true)) {
            throw new \UnexpectedValueException(\sprintf('The default locale ("%s") must be one of "%s".', $this->defaultLocale, implode(', ', $this->enabledLocales)));
        }

        // Add the default locale at the first position of the array,
        // because Symfony\HttpFoundation\Request::getPreferredLanguage
        // returns the first element when no appropriate language is found
        array_unshift($this->enabledLocales, $this->defaultLocale);
        $this->enabledLocales = array_unique($this->enabledLocales);
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$event->isMainRequest()) {
            return;
        }

        // if (!$request->hasPreviousSession()) {
        //     return;
        // }

        $hasSession = $request->hasSession();
        $locale = $sessionLocale = $hasSession ? ((string) $request->getSession()->get('_locale') ?: null) : null;
        $locale = $locale ?: ((string) $request->attributes->get('_locale') ?: null);
        $locale = $locale ?: ((string) $request->getPreferredLanguage($this->enabledLocales) ?: null);

        if (!$locale) {
            return;
        }

        if ($hasSession && !$sessionLocale) {
            $request->getSession()->set('_locale', $locale);
        }

        $request->setLocale($locale);
    }

    public function onLoginSuccessEvent(LoginSuccessEvent $event): void
    {
        /** @var User $user */
        $user = $event->getUser();
        Assert::isInstanceOf($user, User::class);
        $this->requestStack->getSession()->set('_locale', $user->getLanguage());
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLoginSuccessEvent',
            KernelEvents::REQUEST => [
                ['onKernelRequest', 20],
            ],
        ];
    }
}
