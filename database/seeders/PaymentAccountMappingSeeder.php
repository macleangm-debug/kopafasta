<?php

namespace Database\Seeders;

use App\Services\PaymentAccountService;
use Illuminate\Database\Seeder;

class PaymentAccountMappingSeeder extends Seeder
{
    public function run(): void
    {
        app(PaymentAccountService::class)->ensureDefaultMappings();
    }
}
