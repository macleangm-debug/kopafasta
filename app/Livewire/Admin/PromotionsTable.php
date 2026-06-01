<?php

namespace App\Livewire\Admin;

use App\Models\Promotion;
use Livewire\Component;
use Livewire\WithPagination;

class PromotionsTable extends Component
{
    use WithPagination;

    public function render()
    {
        return view('livewire.admin.promotions-table', [
            'rows' => Promotion::query()->orderByDesc('id')->paginate(15),
        ]);
    }
}
