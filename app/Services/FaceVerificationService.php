<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\FaceVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

    /** @return list<string> */
    public function requiredAngleKeysFor(Customer $customer): array
    {
        $keys = $this->requiredAngleKeys();

        if ($customer->no_physical_nida_card) {
            return array_values(array_filter($keys, fn (string $key) => $key !== 'holding_nida'));
        }

        return $keys;
    }

    public function isVerified(Customer $customer): bool
    {
        return $customer->face_verification_status === 'verified';
    }

    /**
     * Unlock face capture so the borrower can retake photos (same idea as replacing NIDA images).
     * Pending review stays locked until staff acts; verified / revision can start a new capture.
     * Pass $force=true from underwriting when photos are unclear and a retake is required.
     */
    public function beginRetake(Customer $customer, bool $force = false): void
    {
        if (! $force && $customer->face_verification_status === 'pending') {
            throw new \InvalidArgumentException(__('borrower.nida.face_retake_pending_blocked'));
        }

        $customer->update([
            'face_verification_status' => 'incomplete',
            'face_verified_at'         => null,
            'face_rejection_notes'     => $force
                ? ($customer->face_rejection_notes ?: 'Clearer photos requested by underwriting.')
                : null,
        ]);
    }

    public function profileStepComplete(Customer $customer): bool
    {
        return in_array($customer->face_verification_status ?? '', ['pending', 'verified'], true);
    }

    public function canApply(Customer $customer): bool
    {
        return $this->isVerified($customer);
    }

    public function latestByAngle(Customer $customer, bool $allConfigured = false): Collection
    {
        $angles = $allConfigured
            ? $this->requiredAngleKeys()
            : $this->requiredAngleKeysFor($customer);

        return FaceVerification::query()
            ->where('customer_id', $customer->id)
            ->whereIn('angle', $angles)
            ->latest()
            ->get()
            ->unique('angle')
            ->keyBy('angle');
    }

    public function progress(Customer $customer): array
    {
        $latest = $this->latestByAngle($customer);
        $required = count($this->requiredAngleKeysFor($customer));
        $uploaded = $latest->count();

        return [
            'required' => $required,
            'uploaded' => $uploaded,
            'percent'  => $required > 0 ? (int) round(($uploaded / $required) * 100) : 0,
            'complete' => $uploaded >= $required,
        ];
    }

    /** Public avatar for lists — prefers a non-rejected front capture. */
    public function avatarUrl(Customer $customer): ?string
    {
        $photos = $this->latestByAngle($customer, true);
        $photo = $photos->get('front')
            ?? $photos->first(fn (FaceVerification $row) => ($row->status ?? '') !== 'rejected' && filled($row->file_path));

        if (! $photo || ($photo->status ?? '') === 'rejected' || ! filled($photo->file_path)) {
            return null;
        }

        return asset('storage/'.$photo->file_path);
    }

    public function upload(Customer $customer, string $angle, UploadedFile $file): FaceVerification
    {
        if (! in_array($angle, $this->requiredAngleKeysFor($customer), true)) {
            throw new \InvalidArgumentException('Invalid face capture angle.');
        }

        $path = $file->store("borrower/{$customer->id}/face", 'public');
        $existing = $this->latestByAngle($customer)->get($angle);

        return DB::transaction(function () use ($customer, $angle, $path, $existing): FaceVerification {
            if ($existing?->file_path) {
                Storage::disk('public')->delete($existing->file_path);
            }

            $record = FaceVerification::create([
                'customer_id' => $customer->id,
                'angle'       => $angle,
                'file_path'   => $path,
                'status'      => 'pending_review',
            ]);

            $progress = $this->progress($customer->fresh());

            // Replacing angles always re-opens review — including previously verified faces.
            $customer->update([
                'face_verification_status' => 'incomplete',
                'face_verified_at' => null,
            ]);

            return $record;
        });
    }

    /**
     * Lock photos for admin review after the borrower confirms on the final step.
     */
    public function submit(Customer $customer): void
    {
        $progress = $this->progress($customer);

        if (! $progress['complete']) {
            throw new \InvalidArgumentException('Upload all required face photos before submitting.');
        }

        if ($this->isVerified($customer)) {
            throw new \InvalidArgumentException('Your face verification is already approved.');
        }

        if ($customer->face_verification_status === 'pending') {
            throw new \InvalidArgumentException('Your photos are already under review.');
        }

        DB::transaction(function () use ($customer): void {
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
        });
    }

    public function remove(Customer $customer, string $angle): void
    {
        if (! in_array($angle, $this->requiredAngleKeysFor($customer), true)) {
            throw new \InvalidArgumentException('Invalid face capture angle.');
        }

        $photo = $this->latestByAngle($customer)->get($angle);

        if (! $photo) {
            throw new \InvalidArgumentException('No photo found for this angle.');
        }

        DB::transaction(function () use ($customer, $photo): void {
            if ($photo->file_path) {
                Storage::disk('public')->delete($photo->file_path);
            }

            $photo->delete();

            $customer->refresh();
            $progress = $this->progress($customer);

            if (! $progress['complete'] && in_array($customer->face_verification_status, ['pending', 'incomplete', 'rejected', 'revision_required'], true)) {
                $customer->update([
                    'face_verification_status' => 'incomplete',
                    'face_rejection_notes'     => null,
                ]);
            }
        });
    }

    /**
     * @return list<array{key: string, label: string, step_title: string, instruction: string, pose: string, done: bool, previewUrl: string|null}>
     */
    public function wizardSteps(Customer $customer): array
    {
        $wizard = $this->wizardState($customer);
        $angles = $this->angles();
        $photos = $this->latestByAngle($customer);

        return collect($wizard['order'])->map(function (string $key) use ($angles, $photos) {
            $meta = $angles[$key] ?? [];
            $photo = $photos[$key] ?? null;

            return [
                'key'         => $key,
                'label'       => __('borrower.face_verification_page.angles.'.$key.'.label'),
                'step_title'  => __('borrower.face_verification_page.angles.'.$key.'.label'),
                'instruction' => __('borrower.face_verification_page.angles.'.$key.'.instruction'),
                'pose'        => match ($key) {
                    'left'  => 'left',
                    'right' => 'right',
                    default => 'front',
                },
                'done'        => $photo !== null && ($photo->status ?? '') !== 'rejected',
                'previewUrl'  => ($photo !== null && ($photo->status ?? '') !== 'rejected' && $photo->file_path)
                    ? asset('storage/'.$photo->file_path)
                    : null,
            ];
        })->values()->all();
    }

    /**
     * @return array<string, string>
     */
    public function uploadUrls(Customer $customer): array
    {
        return collect($this->wizardState($customer)['order'])->mapWithKeys(fn (string $key) => [
            $key => route('site.borrower.face-verification.store', ['angle' => $key]),
        ])->all();
    }

    /**
     * @return array<string, string>
     */
    public function deleteUrls(Customer $customer): array
    {
        return collect($this->wizardState($customer)['order'])->mapWithKeys(fn (string $key) => [
            $key => route('site.borrower.face-verification.destroy', ['angle' => $key]),
        ])->all();
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
