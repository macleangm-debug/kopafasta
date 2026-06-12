<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\FaceVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FaceVerificationService
{
    public function angles(): array
    {
        return config('face_verification.angles', []);
    }

    public function requiredAngleKeys(): array
    {
        return array_keys($this->angles());
    }

    public function isVerified(Customer $customer): bool
    {
        return $customer->face_verification_status === 'verified';
    }

    public function profileStepComplete(Customer $customer): bool
    {
        return in_array($customer->face_verification_status ?? '', ['pending', 'verified'], true);
    }

    public function canApply(Customer $customer): bool
    {
        return $this->isVerified($customer);
    }

    public function latestByAngle(Customer $customer): Collection
    {
        return FaceVerification::query()
            ->where('customer_id', $customer->id)
            ->whereIn('angle', $this->requiredAngleKeys())
            ->latest()
            ->get()
            ->unique('angle')
            ->keyBy('angle');
    }

    public function progress(Customer $customer): array
    {
        $latest = $this->latestByAngle($customer);
        $required = count($this->requiredAngleKeys());
        $uploaded = $latest->count();

        return [
            'required' => $required,
            'uploaded' => $uploaded,
            'percent'  => $required > 0 ? (int) round(($uploaded / $required) * 100) : 0,
            'complete' => $uploaded >= $required,
        ];
    }

    public function upload(Customer $customer, string $angle, UploadedFile $file): FaceVerification
    {
        if (! in_array($angle, $this->requiredAngleKeys(), true)) {
            throw new \InvalidArgumentException('Invalid face capture angle.');
        }

        $path = $file->store("borrower/{$customer->id}/face", 'public');

        return DB::transaction(function () use ($customer, $angle, $path): FaceVerification {
            $record = FaceVerification::create([
                'customer_id' => $customer->id,
                'angle'       => $angle,
                'file_path'   => $path,
                'status'      => 'pending_review',
            ]);

            $progress = $this->progress($customer->fresh());

            if ($progress['complete']) {
                $customer->update([
                    'face_verification_status' => 'pending',
                    'face_verified_at'         => null,
                    'face_rejection_notes'     => null,
                ]);

                $kyc = $customer->kyc ?? CustomerKyc::firstOrCreate(
                    ['customer_id' => $customer->id],
                    ['status' => 'pending', 'payload' => []]
                );

                if (in_array($kyc->status, ['pending', 'rejected'], true)) {
                    $kyc->update(['status' => 'in_review']);
                }
            } elseif ($customer->face_verification_status !== 'verified') {
                $customer->update(['face_verification_status' => 'incomplete']);
            }

            return $record;
        });
    }

    public function approve(Customer $customer, User $reviewer, ?string $notes = null): void
    {
        DB::transaction(function () use ($customer, $reviewer, $notes): void {
            $latestIds = $this->latestByAngle($customer)->pluck('id');

            FaceVerification::whereIn('id', $latestIds)->update([
                'status'      => 'verified',
                'verified_at' => now(),
                'verified_by' => $reviewer->id,
                'notes'       => $notes,
            ]);

            $customer->update([
                'face_verification_status' => 'verified',
                'face_verified_at'         => now(),
                'face_rejection_notes'     => null,
            ]);

            $kyc = $customer->kyc;
            if ($kyc && $this->progress($customer)['complete']) {
                $payload = $kyc->payload ?? [];
                $payload['face_verification'] = [
                    'status'      => 'verified',
                    'verified_at' => now()->toIso8601String(),
                    'verified_by' => $reviewer->id,
                ];
                $kyc->update(['payload' => $payload]);
            }
        });
    }

    public function reject(Customer $customer, User $reviewer, string $notes): void
    {
        DB::transaction(function () use ($customer, $reviewer, $notes): void {
            $latestIds = $this->latestByAngle($customer)->pluck('id');

            FaceVerification::whereIn('id', $latestIds)->update([
                'status'      => 'rejected',
                'verified_at' => now(),
                'verified_by' => $reviewer->id,
                'notes'       => $notes,
            ]);

            $customer->update([
                'face_verification_status' => 'rejected',
                'face_verified_at'         => null,
                'face_rejection_notes'     => $notes,
            ]);

            $kyc = $customer->kyc;
            if ($kyc) {
                $payload = $kyc->payload ?? [];
                $payload['face_verification'] = [
                    'status'      => 'rejected',
                    'rejected_at' => now()->toIso8601String(),
                    'notes'       => $notes,
                ];
                $kyc->update([
                    'payload' => $payload,
                    'status'  => 'rejected',
                ]);
            }
        });
    }

    public function statusLabel(Customer $customer): array
    {
        return match ($customer->face_verification_status) {
            'verified' => ['Verified', 'bg-emerald-100 text-emerald-800'],
            'pending'  => ['Pending review', 'bg-sky-100 text-sky-800'],
            'rejected' => ['Rejected', 'bg-red-100 text-red-800'],
            default    => ['Incomplete', 'bg-amber-100 text-amber-800'],
        };
    }

    /**
     * @return array{order: list<string>, current_index: int, current_angle: string|null, complete: bool, total: int}
     */
    public function wizardState(Customer $customer): array
    {
        $order = $this->requiredAngleKeys();
        $latest = $this->latestByAngle($customer);

        if ($customer->face_verification_status === 'rejected') {
            $latest = $latest->filter(fn (FaceVerification $photo) => $photo->status !== 'rejected');
        }

        $total = count($order);
        $currentIndex = 0;

        foreach ($order as $index => $angle) {
            if (! $latest->has($angle)) {
                $currentIndex = $index;
                break;
            }
            $currentIndex = $index + 1;
        }

        $complete = $latest->count() >= $total;
        $activeIndex = min($currentIndex, max($total - 1, 0));

        return [
            'order'          => $order,
            'current_index'  => $activeIndex,
            'current_angle'  => $order[$activeIndex] ?? null,
            'complete'       => $complete,
            'total'          => $total,
        ];
    }
}
