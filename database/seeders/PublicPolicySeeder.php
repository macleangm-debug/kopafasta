<?php

namespace Database\Seeders;

use App\Services\PublicPolicyService;
use Illuminate\Database\Seeder;

class PublicPolicySeeder extends Seeder
{
    public function run(): void
    {
        app(PublicPolicyService::class)->publishDefaults();
    }
}
