<?php

namespace App\Livewire\Admin;

use App\Models\Loan;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LoansTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    #[Url]
    public string $sort = 'created_at';

    #[Url]
    public string $direction = 'desc';

    public int $perPage = 15;

    public bool $lockStatus = false;

    protected $queryString = [];

    public function mount(?string $status = null, bool $lockStatus = false): void
    {
        if ($status !== null && $status !== '') {
            $this->status = $status;
        }
        $this->lockStatus = $lockStatus;
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingStatus(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $column): void
    {
        if ($this->sort === $column) {
            $this->direction = $this->direction === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sort = $column;
            $this->direction = 'asc';
        }
    }

    public function render()
    {
        $loans = Loan::query()
            ->with(['customer', 'product', 'application.postApprovalFees'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('loan_number', 'like', $term)
                        ->orWhereHas('customer', function ($q) use ($term) {
                            $q->where('first_name', 'like', $term)
                                ->orWhere('last_name', 'like', $term)
                                ->orWhere('phone', 'like', $term)
                                ->orWhere('customer_number', 'like', $term);
                        });
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $statuses = ['pending', 'active', 'disbursed', 'arrears', 'restructuring', 'closed', 'defaulted', 'written_off'];

        return view('livewire.admin.loans-table', compact('loans', 'statuses'));
    }
}
