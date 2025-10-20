<?php

namespace App\Utils\Traits;

trait LoaderTrait
{
    public function startLoading(string $key = 'global'): void
    {
        $this->getRequest()->getSession()->set('_loading_' . $key, true);
    }

    public function stopLoading(string $key = 'global'): void
    {
        $this->getRequest()->getSession()->remove('_loading_' . $key);
    }

    public function isLoading(string $key = 'global'): bool
    {
        return $this->getRequest()->getSession()->has('_loading_' . $key);
    }
}
