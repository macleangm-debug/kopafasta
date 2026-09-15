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
    #[Url] public string $desk = '';
    #[Url] public string $sort = 'created_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingDesk(): void { $this->resetPage(); }

    public function setDesk(string $desk): void
    {
        $this->desk = $desk;
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
        $userId = auth('admin')->id();

        $rows = SupportTicket::query()
            ->with(['customer', 'assignee'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('ticket_number', 'like', $term)
                        ->orWhere('subject', 'like', $term)
                        ->orWhere('guest_name', 'like', $term)
                        ->orWhere('guest_email', 'like', $term)
                        ->orWhereHas('customer', fn ($q) => $q->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term));
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->desk === 'mine', fn ($q) => $q->where('assigned_to', $userId))
            ->when($this->desk === 'unassigned', fn ($q) => $q->whereNull('assigned_to'))
            ->when($this->desk === 'waiting', fn ($q) => $q->where('status', 'waiting'))
            ->when($this->desk === 'escalated', fn ($q) => $q->where('priority', 'urgent')
                ->whereNotIn('status', ['resolved', 'closed']))
            ->when($this->desk === 'resolved', fn ($q) => $q->whereIn('status', ['resolved', 'closed']))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $statuses = ['open', 'in_progress', 'waiting', 'resolved', 'closed'];
        $desks = [
            '' => 'All',
            'mine' => 'My Tickets',
            'unassigned' => 'Unassigned',
            'waiting' => 'Waiting',
            'escalated' => 'Escalated',
            'resolved' => 'Resolved',
        ];

        return view('livewire.admin.support-tickets-table', compact('rows', 'statuses', 'desks'));
    }
}
