<?php

namespace App\Http\Controllers\Admin;

use App\Models\ProfileSectionDefinition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProfileSectionDefinitionController extends ResourceController
{
    protected string $model = ProfileSectionDefinition::class;
    protected string $routePrefix = 'admin.profile-sections';
    protected string $viewFolder = 'profile-sections';
    protected string $singular = 'profile section';

    public function index()
    {
        $records = ProfileSectionDefinition::query()->orderBy('display_order')->orderBy('id')->get();

        return view('admin.profile-sections.index', compact('records'));
    }

    protected function rules(?Model $model = null): array
    {
        $id = $model?->id;

        return [
            'key'                  => ['required', 'string', 'max:60', 'alpha_dash', 'unique:profile_section_definitions,key,'.$id],
            'icon'                 => ['nullable', 'string', 'max:20'],
            'name_en'              => ['required', 'string', 'max:120'],
            'name_sw'              => ['nullable', 'string', 'max:120'],
            'description_en'       => ['nullable', 'string', 'max:500'],
            'description_sw'       => ['nullable', 'string', 'max:500'],
            'is_required'          => ['nullable', 'boolean'],
            'input_type'           => ['required', 'string', 'max:40'],
            'validation_rules'     => ['nullable', 'string'],
            'display_order'        => ['required', 'integer', 'min:0'],
            'required_before_loan' => ['nullable', 'boolean'],
            'is_active'            => ['nullable', 'boolean'],
            'maps_to'              => ['nullable', 'string', 'max:60'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'inputTypes' => [
                'text'           => 'Text',
                'number'         => 'Number',
                'date'           => 'Date',
                'dropdown'       => 'Dropdown',
                'file_upload'    => 'File upload',
                'image_upload'   => 'Image upload',
                'camera_capture' => 'Camera capture',
                'section_link'   => 'Link to existing section',
            ],
            'mapTargets' => [
                'personal'  => 'Personal information',
                'activity'  => 'Contact / activity',
                'residence' => 'Residence',
                'kyc'       => 'National ID / KYC',
                'face'      => 'Facial verification',
                'payment'   => 'Payment account',
                'kin'       => 'Next of kin',
                'assets'    => 'Collaterals',
            ],
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data['is_required'] = request()->boolean('is_required');
        $data['required_before_loan'] = request()->boolean('required_before_loan');
        $data['is_active'] = request()->boolean('is_active', true);

        $mapsTo = $data['maps_to'] ?? null;
        unset($data['maps_to']);

        $metadata = $existing?->metadata ?? [];
        if ($mapsTo) {
            $metadata['maps_to'] = $mapsTo;
        }
        $data['metadata'] = $metadata;

        if (filled($data['validation_rules'] ?? null) && is_string($data['validation_rules'])) {
            $decoded = json_decode($data['validation_rules'], true);
            $data['validation_rules'] = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
        } elseif (! is_array($data['validation_rules'] ?? null)) {
            $data['validation_rules'] = [];
        }

        return parent::transform($data, $existing);
    }
}
