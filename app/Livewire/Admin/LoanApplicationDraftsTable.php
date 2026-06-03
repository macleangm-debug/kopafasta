<?php

namespace App\Livewire\Admin;

use App\Models\LoanApplicationDraft;
use App\Services\LoanApplicationDraftService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LoanApplicationDraftsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $phase = '';
    #[Url] public string $sort = 'saved_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 20;

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingPhase(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $col): void
    {
        $this->direction = $this->sort === $col
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sort = $col;
    }

    public function render()
    {
        $drafts = LoanApplicationDraft::query()
            ->with(['customer', 'product'])
            ->whereIn('phase', ['details', 'application'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->whereHas('customer', fn ($q) => $q
                        ->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('customer_number', 'like', $term))
                        ->orWhereHas('product', fn ($q) => $q->where('name', 'like', $term)
                            ->orWhere('code', 'like', $term));
                });
            })
            ->when($this->phase !== '', fn ($q) => $q->where('phase', $this->phase))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $draftService = app(LoanApplicationDraftService::class);

        return view('livewire.admin.loan-application-drafts-table', [
            'rows'         => $drafts,
            'draftService' => $draftService,
            'phases'       => ['details' => __('admin.application_drafts.phase_details'), 'application' => __('admin.application_drafts.phase_application')],
        ]);
    }
}
