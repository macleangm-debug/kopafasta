<?php

namespace App\Livewire\Admin;

use App\Models\User;
use App\Services\RoleService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class UsersTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $role = '';
    #[Url] public string $sort = 'created_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingRole(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->direction = $this->sort === $col
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sort = $col;
    }

    public function render(RoleService $roles)
    {
        $staffRoles = $roles->staffRoles();

        $rows = User::query()
            ->where(function ($q) use ($staffRoles) {
                $q->whereIn('role', $staffRoles)
                    ->orWhere(function ($inner) use ($staffRoles) {
                        // Multi-role JSON may list staff even when primary role drifted.
                        foreach ($staffRoles as $code) {
                            $inner->orWhereJsonContains('roles', $code);
                        }
                    });
            })
            // Never list borrower/partner portal identities in Staff Users.
            ->whereNotIn('role', ['borrower', 'customer', 'vendor', 'investor'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('phone', 'like', $term);
                });
            })
            ->when($this->role !== '', fn ($q) => $q->where('role', $this->role))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $filterRoles = $staffRoles;
        $roleLabels = collect($filterRoles)
            ->mapWithKeys(fn (string $code) => [
                $code => $code === 'agent' ? 'Customer Support' : $roles->label($code),
            ])
            ->all();

        return view('livewire.admin.users-table', compact('rows', 'filterRoles', 'roleLabels'));
    }
}
