<?php

namespace App\Http\Controllers\Admin;

use App\Models\Customer;
use App\Models\Guarantor;
use Illuminate\Database\Eloquent\Model;

class GuarantorController extends ResourceController
{
    protected string $model = Guarantor::class;
    protected string $routePrefix = 'admin.guarantors';
    protected string $viewFolder = 'guarantors';
    protected string $singular = 'guarantor';

    protected function rules(?Model $model = null): array
    {
        return [
            'first_name'    => ['required', 'string', 'max:100'],
            'last_name'     => ['required', 'string', 'max:100'],
            'phone'         => ['required', 'string', 'max:30'],
            'email'         => ['nullable', 'email', 'max:150'],
            'national_id'   => ['nullable', 'string', 'max:50'],
            'address'       => ['nullable', 'string', 'max:500'],
            'relationship'  => ['nullable', 'string', 'max:50'],
        ];
    }

    protected function formData(): array
    {
        return [
            'relationships' => [
                'spouse' => 'Spouse', 'parent' => 'Parent', 'sibling' => 'Sibling',
                'child' => 'Child', 'relative' => 'Relative', 'friend' => 'Friend',
                'colleague' => 'Colleague', 'employer' => 'Employer', 'other' => 'Other',
            ],
        ];
    }
}
