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

    /** Ordered list of every stored image path (asset photos + person shot). @return array<int, string> */
    public function galleryPaths(): array
    {
        $paths = array_values($this->photo_paths ?? []);
        if ($person = ($this->metadata['person_with_asset_path'] ?? null)) {
            $paths[] = $person;
        }

        return array_values(array_filter($paths));
    }

    public function thumbnailPath(): ?string
    {
        return $this->galleryPaths()[0] ?? null;
    }
}
