<?php

namespace App\Livewire\Admin;

use App\Models\Customer;
use App\Services\ProfileCompletionService;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class CustomersTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $status = '';

    public int $perPage = 15;

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }

    public function render()
    {
        $customers = Customer::query()
            ->with('branch')
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('first_name', 'like', $term)
                        ->orWhere('last_name', 'like', $term)
                        ->orWhere('phone', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('customer_number', 'like', $term)
                        ->orWhere('national_id', 'like', $term);
                });
            })
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->latest()
            ->paginate($this->perPage);

        $profileService = app(ProfileCompletionService::class);
        $customers->getCollection()->transform(function (Customer $customer) use ($profileService) {
            $customer->profile_percent = $profileService->calculate($customer)['percent'];

            return $customer;
        });

        return view('livewire.admin.customers-table', compact('customers'));
    }
}
