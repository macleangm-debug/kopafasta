<?php

namespace Tests;

use App\Models\CompanySignatory;
use App\Models\Setting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * Seed authorised CEO signature + company stamp so decision letters can render.
     * Writes real files under storage/app/public because LegalSettingsService resolves by filesystem path.
     */
    protected function seedAuthorisedLetterAssets(): void
    {
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        foreach (['signatories', 'legal'] as $dir) {
            $path = storage_path('app/public/'.$dir);
            if (! is_dir($path)) {
                mkdir($path, 0755, true);
            }
        }

        file_put_contents(storage_path('app/public/signatories/ceo-test.png'), $png);
        file_put_contents(storage_path('app/public/legal/company-stamp.png'), $png);

        CompanySignatory::query()->updateOrCreate(
            ['signatory_type' => 'ceo', 'name' => 'Test CEO'],
            [
                'position' => 'Chief Executive Officer',
                'signature_path' => 'signatories/ceo-test.png',
                'is_active' => true,
            ],
        );

        Setting::set('legal.stamp_path', 'legal/company-stamp.png');
        Setting::set('legal.signatory_name', 'Test CEO');
        Setting::set('legal.signatory_title', 'Chief Executive Officer');
    }
}
