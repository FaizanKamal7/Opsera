<?php

namespace App\Service;

class LoadingStateService
{
    private bool $isLoading = false;
    private int $loadingCounter = 0;

    public function startLoading(): void
    {
        $this->loadingCounter++;
        $this->isLoading = true;
    }

    public function stopLoading(): void
    {
        $this->loadingCounter = max(0, $this->loadingCounter - 1);
        $this->isLoading = $this->loadingCounter > 0;
    }

    public function isLoading(): bool
    {
        return $this->isLoading;
    }
}