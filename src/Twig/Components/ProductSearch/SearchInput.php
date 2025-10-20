<?php

namespace App\Twig\Components\ProductSearch;

use App\Service\LoadingStateService;
use App\Trait\HasLoadingStateTrait;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(
    name: 'SearchInput',
    template: 'components/ProductSearch/SearchInput.html.twig'
)]
final class SearchInput
{
    use DefaultActionTrait;
    use ComponentToolsTrait;
    use HasLoadingStateTrait;

    #[LiveProp(writable: true)]
    public string $query = '';

    #[LiveProp(writable: true)]
    public string $searchBy = 'sku';

    public function __construct(
        LoadingStateService $loadingStateService
    )
    {
        $this->setLoadingStateService($loadingStateService);
    }

    public function updateSearch(): void
    {
        $this->startLoading();
        $this->emit('updateSearch', [
            'query' => $this->query,
            'searchBy' => $this->searchBy
        ]);
    }
}
