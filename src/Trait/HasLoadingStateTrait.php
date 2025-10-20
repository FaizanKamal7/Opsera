<?php

namespace App\Trait;
use App\Service\LoadingStateService;

trait HasLoadingStateTrait
{
    private LoadingStateService $loadingStateService;

    public function setLoadingStateService(LoadingStateService $loadingStateService): void
    {
        $this->loadingStateService = $loadingStateService;
    }

    protected function startLoading(): void
    {
        $this->loadingStateService->startLoading();
    }

    protected function stopLoading(): void
    {
        $this->loadingStateService->stopLoading();
    }

    protected function isLoading(): bool
    {
        return $this->loadingStateService->isLoading();
    }
}