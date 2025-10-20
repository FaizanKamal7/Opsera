<?php

namespace App\Twig\Components;

use App\Entity\Manual;
use App\Service\UrlShortner;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent]
final class AddManualModel
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public string $manual_type = 'simple';

    #[LiveProp(writable: true)]
    public string $hash = '';

    #[LiveProp(writable: true)]
    public bool $isAlreadyExist = false;

    public function __construct(
        private UrlShortner $url_shortner,
        private EntityManagerInterface $entity_manager
    ) {}

    public function getManualType(): string
    {
        return $this->manual_type;
    }

    #[LiveAction]
    public function verifyHash(): void
    {
        if ($this->isHashExists($this->hash)) {
            $this->isAlreadyExist = true;
            $this->hash = $this->findNonExistingHash();
            return;
        }
        $this->isAlreadyExist = false;
    }

    private function isHashExists(string $hash): bool
    {
        return $this->entity_manager->getRepository(Manual::class)->findOneBy(['keyword' => $hash]) !== null;
    }

    private function findNonExistingHash(): string
    {
        $generated_hash = $this->url_shortner->generateShortUrl();
        while ($this->isHashExists($generated_hash)) {
            $generated_hash = $this->url_shortner->generateShortUrl();
        }
        return $generated_hash;
    }
}
