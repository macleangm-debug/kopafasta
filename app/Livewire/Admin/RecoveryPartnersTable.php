<?php

namespace App\Livewire\Admin;

use App\Services\RecoveryPartnerService;
use App\Services\RecoveryPolicyService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class RecoveryPartnersTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $sort = 'name';
    #[Url] public string $direction = 'asc';
    public int $perPage = 15;

    public string $partnerType = '';

    public function mount(string $partnerType): void
    {
        $this->partnerType = $partnerType;
    }

    public function updatingSearch(): void
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

    public function render(RecoveryPartnerService $partners)
    {
        $rows = $partners->filteredQuery($this->partnerType, $this->search)
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sort === 'vendor_number' ? 'partner_number' : $this->sort, $this->direction)
            ->paginate($this->perPage);

        $stats = [];
        foreach ($rows as $row) {
            $stats[$row->id] = $partners->statsForVendor($row);
        }

        return view('livewire.admin.recovery-partners-table', [
            'rows'        => $rows,
            'stats'       => $stats,
            'partnerType' => $this->partnerType,
            'label'       => app(RecoveryPolicyService::class)->partnerTypeLabel($this->partnerType),
            'statuses'    => ['active', 'inactive', 'suspended'],
        ]);
    }
}
