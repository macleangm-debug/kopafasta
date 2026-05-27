<?php

namespace App\Livewire\Admin;

use App\Models\Vendor;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class VendorsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $category = '';
    #[Url] public string $sort = 'created_at';
    #[Url] public string $direction = 'desc';
    public int $perPage = 15;

    public bool $lockStatus = false;
    public bool $lockCategory = false;

    public function mount(?string $status = null, ?string $category = null, bool $lockStatus = false, bool $lockCategory = false): void
    {
        if ($status !== null && $status !== '')     { $this->status = $status; }
        if ($category !== null && $category !== '') { $this->category = $category; }
        $this->lockStatus = $lockStatus;
        $this->lockCategory = $lockCategory;
    }

    public function updatingSearch(): void   { $this->resetPage(); }
    public function updatingStatus(): void   { $this->resetPage(); }
    public function updatingCategory(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->direction = $this->sort === $col
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sort = $col;
    }

    public function render()
    {
        $rows = Vendor::query()
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($qq) use ($term) {
                    $qq->where('name', 'like', $term)
                       ->orWhere('vendor_number', 'like', $term)
                       ->orWhere('phone', 'like', $term);
                });
            })
            ->when($this->status !== '',   fn ($q) => $q->where('status', $this->status))
            ->when($this->category !== '', fn ($q) => $q->where('category', $this->category))
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $statuses   = ['active', 'inactive', 'suspended'];
        $categories = ['gps_installer', 'insurance', 'valuer', 'towing', 'yard', 'auctioneer'];

        return view('livewire.admin.vendors-table', [
            'rows'       => $rows,
            'statuses'   => $statuses,
            'categories' => $categories,
        ]);
    }
}
