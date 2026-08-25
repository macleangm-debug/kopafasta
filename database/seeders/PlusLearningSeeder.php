<?php

namespace Database\Seeders;

use App\Services\Plus\PlusLearningCatalog;
use Illuminate\Database\Seeder;

class PlusLearningSeeder extends Seeder
{
    public function run(): void
    {
        app(PlusLearningCatalog::class)->seed();
    }
}
