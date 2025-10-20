<?php

namespace App\Twig\Components;

use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\UX\TwigComponent\Attribute\AsTwigComponent;
use Symfony\UX\TwigComponent\Attribute\PreMount;

#[AsTwigComponent()]
final class Alert
{
    public string $type = 'success';
    public string $message;
    public string $sub_message = '';


    #[PreMount]
    public function preMount(array $data): array
    {
        // validate data
        $resolver = new OptionsResolver();
        $resolver->setIgnoreUndefined(true);

        $resolver->setDefaults(['type' => 'success']);
        $resolver->setAllowedValues('type', ['success', 'danger', 'warning', 'info']);
        $resolver->setRequired('message');
        $resolver->setAllowedTypes('message', 'string');

        return $resolver->resolve($data) + $data;
    }

    public function getIcon(): string
    {
        return match ($this->type) {
            'danger' => 'fa-circle-xmark',
            'warning' => 'fa-triangle-exclamation',
            'info' => 'fa-circle-info',
            default => 'fa-circle-check',
        };
    }
}
