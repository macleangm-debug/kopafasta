<?php

namespace App\Livewire\Admin;

use App\Models\SupportTicket;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class SupportTicketsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $sort = 'created_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->direction = $this->sort === $col
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sort = $col;
    }

    public function render()
    {
        $rows = SupportTicket::query()
            ->with('customer')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('ticket_number', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhereHas('customer', fn ($q) => $q->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $statuses = ['open', 'in_progress', 'resolved', 'closed'];

        return view('livewire.admin.support-tickets-table', compact('rows', 'statuses'));
    }
}
