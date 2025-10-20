<?php

declare(strict_types=1);

namespace App\Twig;

use App\Service\LoadingStateService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Intl\Locales;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class AppExtension extends AbstractExtension
{
    /**
     * @var list<array{code: string, name: string}>|null
     */
    private ?array $locales = null;

    // The $locales argument is injected thanks to the service container.
    // See https://symfony.com/doc/current/service_container.html#binding-arguments-by-name-or-type
    public function __construct(
        /** @var string[] */
        private readonly array        $enabledLocales,
        private ContainerBagInterface $params,
        private RequestStack          $requestStack,
        private LoadingStateService   $loadingStateService
    )
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('locales', $this->getLocales(...)),
            new TwigFunction('app_version', $this->getAppVersion(...)),
            new TwigFunction('is_loading', [$this, 'isLoading']),

        ];
    }

    /**
     * Takes the list of codes of the locales (languages) enabled in the
     * application and returns an array with the name of each locale written
     * in its own language (e.g. English, Français, Español, etc.).
     *
     * @return array<int, array<string, string>>
     */
    public function getLocales(): array
    {
        if (null !== $this->locales) {
            return $this->locales;
        }

        $this->locales = [];

        foreach ($this->enabledLocales as $localeCode) {
            $this->locales[] = ['code' => $localeCode, 'name' => Locales::getName($localeCode, $localeCode)];
        }

        return $this->locales;
    }

    public function getAppVersion(): string
    {
        /** @var string $version */
        $version = $this->params->get('app.version');

        return $version;
    }

    public function isLoading(): bool
    {
        return $this->loadingStateService->isLoading();
    }
}
