<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Customer;

class BranchService
{
    public function headOffice(): ?Branch
    {
        return Branch::query()
            ->where('code', 'HQ001')
            ->where('is_active', true)
            ->first()
            ?? Branch::query()->where('is_active', true)->orderBy('id')->first();
    }

    public function headOfficeId(): ?int
    {
        return $this->headOffice()?->id;
    }

    public function assignDefault(Customer $customer): Customer
    {
        if ($customer->branch_id) {
            return $customer;
        }

        $headOfficeId = $this->headOfficeId();
        if ($headOfficeId) {
            $customer->update(['branch_id' => $headOfficeId]);
        }

        return $customer->refresh();
    }
}
