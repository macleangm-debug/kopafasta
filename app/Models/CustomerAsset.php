<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerAsset extends Model
{
    protected $fillable = [
        'customer_id',
        'asset_type',
        'label',
        'description',
        'registration_number',
        'estimated_value',
        'photo_paths',
        'metadata',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'estimated_value' => 'decimal:2',
            'photo_paths'     => 'array',
            'metadata'        => 'array',
            'is_active'       => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return array<string, string> */
    public static function typeOptions(): array
    {
        return [
            'vehicle'   => 'Vehicle',
            'house'     => 'House',
            'land'      => 'Land',
            'equipment' => 'Equipment',
        ];
    }

    /** @return array<string, string> */
    public static function typeIcons(): array
    {
        return [
            'vehicle'   => '🚗',
            'house'     => '🏠',
            'land'      => '🌍',
            'equipment' => '⚙️',
        ];
    }

    /**
     * Type-specific detail fields. `column` maps a field to a real DB column;
     * everything else is stored inside metadata['details'][key].
     *
     * @return array<string, array<int, array{key:string,type:string,column?:bool}>>
     */
    public static function detailFields(): array
    {
        return [
            'house' => [
                ['key' => 'property_name', 'type' => 'text'],
                ['key' => 'address', 'type' => 'text'],
                ['key' => 'ownership', 'type' => 'text'],
                ['key' => 'plot_number', 'type' => 'text'],
                ['key' => 'bedrooms', 'type' => 'number'],
                ['key' => 'bathrooms', 'type' => 'number'],
                ['key' => 'construction_type', 'type' => 'text'],
            ],
            'land' => [
                ['key' => 'plot_number', 'type' => 'text'],
                ['key' => 'location', 'type' => 'text'],
                ['key' => 'size', 'type' => 'text'],
                ['key' => 'land_use', 'type' => 'text'],
                ['key' => 'ownership', 'type' => 'text'],
            ],
            'vehicle' => [
                ['key' => 'registration_number', 'type' => 'text', 'column' => true],
                ['key' => 'make', 'type' => 'text'],
                ['key' => 'year', 'type' => 'number'],
                ['key' => 'chassis_number', 'type' => 'text'],
                ['key' => 'mileage', 'type' => 'number', 'format' => 'thousands'],
            ],
            'equipment' => [
                ['key' => 'equipment_type', 'type' => 'text'],
                ['key' => 'brand', 'type' => 'text'],
                ['key' => 'model', 'type' => 'text'],
                ['key' => 'serial_number', 'type' => 'text'],
                ['key' => 'purchase_year', 'type' => 'number'],
                ['key' => 'condition', 'type' => 'text'],
            ],
        ];
    }

    /** @return array<int, array{key:string,type:string,column?:bool}> */
    public static function detailFieldsFor(string $type): array
    {
        return self::detailFields()[$type] ?? [];
    }

    /** Detail values stored in metadata['details']. @return array<string, mixed> */
    public function details(): array
    {
        return (array) ($this->metadata['details'] ?? []);
    }

    public function detail(string $key): mixed
    {
        return $this->details()[$key] ?? null;
    }

    public function hasComprehensiveInsurance(): bool
    {
        return $this->hasVehicleInsurance();
    }

    /** Vehicle has an insurance certificate on file (comprehensive or third-party). */
    public function hasVehicleInsurance(): bool
    {
        $meta = $this->metadata ?? [];

        return filled($meta['insurance_document_path'] ?? null);
    }

    public function insuranceType(): ?string
    {
        $type = $this->detail('insurance_type');

        return filled($type) ? (string) $type : null;
    }

    /** @return array<string, string> */
    public static function insuranceTypeOptions(): array
    {
        return [
            'comprehensive' => 'Comprehensive',
            'third_party'   => 'Third-party',
        ];
    }

    /**
     * Canonical inspection angles — same set the borrower profile and the valuer use.
     *
     * @return array<string, string>
     */
    public static function photoAngleLabels(?string $type = null): array
    {
        $base = [
            'front' => 'Front',
            'back' => 'Back',
        ];
        if (! in_array((string) $type, ['land', 'house', ''], true)) {
            $base += [
                'left' => 'Left',
                'right' => 'Right',
            ];
        }

        return $base + [
            'owner' => 'Owner with asset',
        ];
    }

    /** Angles the owner must photograph (excludes the owner-with-asset portrait). @return list<string> */
    public static function bodyPhotoAngleKeys(?string $type = null): array
    {
        return array_values(array_filter(
            array_keys(self::photoAngleLabels($type)),
            fn (string $key) => $key !== 'owner'
        ));
    }

    public function isVehicleLike(): bool
    {
        return ! in_array((string) $this->asset_type, ['land', 'house', ''], true);
    }

    /** @return list<string> */
    public function missingPhotoAngles(): array
    {
        $have = $this->photosByAngle();
        $missing = [];
        foreach (array_keys(self::photoAngleLabels($this->asset_type)) as $angle) {
            if (! filled($have[$angle] ?? null)) {
                $missing[] = $angle;
            }
        }

        return $missing;
    }

    public function hasCompletePhotoSet(): bool
    {
        return $this->missingPhotoAngles() === [];
    }

    public static function angleFromLabel(?string $label, ?string $docType = null): ?string
    {
        $hay = strtolower(trim(($docType ?? '').' '.($label ?? '')));
        if ($hay === '') {
            return null;
        }
        if (str_contains($hay, 'owner') || str_contains($hay, 'person') || str_contains($hay, 'with asset')) {
            return 'owner';
        }
        if (str_contains($hay, 'front')) {
            return 'front';
        }
        if (str_contains($hay, 'back') || str_contains($hay, 'rear')) {
            return 'back';
        }
        if (preg_match('/\bleft\b/', $hay)) {
            return 'left';
        }
        if (preg_match('/\bright\b/', $hay)) {
            return 'right';
        }

        return null;
    }

    /** Ordered list of every stored image path (asset photos + person shot). @return array<int, string> */
    public function galleryPaths(): array
    {
        $paths = array_values($this->photo_paths ?? []);
        if ($person = ($this->metadata['person_with_asset_path'] ?? null)) {
            $paths[] = $person;
        }

        return array_values(array_filter($paths));
    }

    /**
     * Asset photos keyed by inspection angle, including owner-with-asset.
     *
     * @return array<string, string> angle => storage path
     */
    public function photosByAngle(): array
    {
        $person = (string) ($this->metadata['person_with_asset_path'] ?? '');
        $keyed = (array) ($this->metadata['photo_angles'] ?? []);
        $labels = self::photoAngleLabels($this->asset_type);
        $out = [];
        foreach (array_keys($labels) as $angle) {
            if ($angle === 'owner') {
                continue;
            }
            $path = $keyed[$angle] ?? null;
            if (is_string($path) && filled($path) && $path !== $person) {
                $out[$angle] = $path;
            }
        }
        if ($out === []) {
            $order = array_values(array_filter(array_keys($labels), fn ($angle) => $angle !== 'owner'));
            foreach (array_values($this->photo_paths ?? []) as $i => $path) {
                if (! filled($path) || $path === $person) {
                    continue;
                }
                $angle = $order[$i] ?? null;
                if ($angle && ! isset($out[$angle])) {
                    $out[$angle] = $path;
                }
            }
        }
        if (filled($person)) {
            $out['owner'] = $person;
        } elseif (filled($keyed['owner'] ?? null)) {
            $out['owner'] = (string) $keyed['owner'];
        }

        return $out;
    }

    public function thumbnailPath(): ?string
    {
        $person = (string) ($this->metadata['person_with_asset_path'] ?? '');
        foreach ($this->photosByAngle() as $angle => $path) {
            if ($angle === 'owner' || $path === $person) {
                continue;
            }

            return $path;
        }
        foreach (array_values($this->photo_paths ?? []) as $path) {
            if (filled($path) && $path !== $person) {
                return $path;
            }
        }

        return null;
    }
}
