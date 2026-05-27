<?php

namespace App\Http\Controllers\Admin;

use App\Models\BlacklistEntry;
use Illuminate\Database\Eloquent\Model;

class BlacklistEntryController extends ResourceController
{
    protected string $model = BlacklistEntry::class;
    protected string $routePrefix = 'admin.blacklist-entries';
    protected string $viewFolder = 'blacklist-entries';
    protected string $singular = 'blacklist entry';

    protected function rules(?Model $model = null): array
    {
        return [
            'identifier_type'  => ['required', 'in:nida,phone,email,tin,passport,name'],
            'identifier_value' => ['required', 'string', 'max:255'],
            'reason'  => ['required', 'string', 'max:255'],
            'source'  => ['required', 'in:internal,crb,court,regulator,other'],
            'listed_on'  => ['nullable', 'date'],
            'expires_on' => ['nullable', 'date'],
            'is_active'  => ['nullable', 'boolean'],
            'notes'      => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function formData(): array
    {
        return [
            'types'   => ['nida'=>'NIDA','phone'=>'Phone','email'=>'Email','tin'=>'TIN','passport'=>'Passport','name'=>'Name'],
            'sources' => ['internal'=>'Internal','crb'=>'CRB','court'=>'Court','regulator'=>'Regulator','other'=>'Other'],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['added_by_user_id'] = auth()->id();
        return $data;
    }
}
