<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\User;
use App\Services\PinService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SeedGradeQaPersonasCommand extends Command
{
    protected $signature = 'grades:seed-qa-personas {--pin=1234 : PIN for every QA persona}';

    protected $description = 'Create staging QA borrowers for Bronze, Silver, Gold, Platinum, and Gold under review';

    public function handle(PinService $pins): int
    {
        $pin = (string) $this->option('pin');
        $rows = [];

        foreach ($this->personas() as $persona) {
            $customer = $this->upsert($persona, $pin, $pins);
            $rows[] = [
                $persona['label'],
                $customer->phone,
                $pin,
                strtoupper((string) $customer->grade),
                $customer->grade_status,
                $customer->grade_integrity,
            ];
        }

        $this->table(['Persona', 'Phone', 'PIN', 'Grade', 'Status', 'Integrity'], $rows);
        $this->comment('These accounts are for staging visual QA. They are not created by the grade engine.');

        return self::SUCCESS;
    }

    private function personas(): array
    {
        return [
            ['label' => 'Bronze', 'number' => 'QA-GRD-BRONZE', 'phone' => '255700000001', 'grade' => 'bronze', 'status' => 'ok', 'integrity' => 'normal'],
            ['label' => 'Silver', 'number' => 'QA-GRD-SILVER', 'phone' => '255700000002', 'grade' => 'silver', 'status' => 'ok', 'integrity' => 'normal'],
            ['label' => 'Gold', 'number' => 'QA-GRD-GOLD', 'phone' => '255700000003', 'grade' => 'gold', 'status' => 'ok', 'integrity' => 'normal'],
            ['label' => 'Platinum', 'number' => 'QA-GRD-PLATINUM', 'phone' => '255700000004', 'grade' => 'platinum', 'status' => 'ok', 'integrity' => 'normal'],
            ['label' => 'Gold under review', 'number' => 'QA-GRD-GOLD-REVIEW', 'phone' => '255700000005', 'grade' => 'gold', 'status' => 'under_review', 'integrity' => 'review'],
        ];
    }

    private function upsert(array $persona, string $pin, PinService $pins): Customer
    {
        $customer = Customer::query()->where('customer_number', $persona['number'])->first();

        if (! $customer) {
            $user = User::query()->create([
                'name' => $persona['label'].' QA',
                'email' => Str::lower($persona['number']).'@qa.kopafasta.test',
                'password' => Hash::make('password'),
                'role' => 'borrower',
                'phone' => $persona['phone'],
            ]);
            $customer = Customer::query()->create([
                'user_id' => $user->id,
                'customer_number' => $persona['number'],
                'type' => 'individual',
                'status' => 'active',
                'first_name' => $persona['label'],
                'last_name' => 'QA',
                'phone' => $persona['phone'],
                'country_code' => 'TZ',
            ]);
        }

        $customer->forceFill([
            'grade' => $persona['grade'],
            'calculated_grade' => $persona['grade'],
            'grade_score' => ['bronze' => 20, 'silver' => 50, 'gold' => 70, 'platinum' => 90][$persona['grade']],
            'grade_status' => $persona['status'],
            'grade_integrity' => $persona['integrity'],
            'grade_review_until' => $persona['status'] === 'under_review' ? now()->addDays(14) : null,
        ])->save();

        $pins->setPin($customer->user, $pin);

        return $customer->fresh();
    }
}
