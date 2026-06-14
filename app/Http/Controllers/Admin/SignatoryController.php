<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompanySignatory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SignatoryController extends Controller
{
    public function index(): View
    {
        return view('admin.settings.signatories.index', [
            'signatories' => CompanySignatory::query()->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.settings.signatories.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['signature_path'] = $this->storeSignature($request);

        CompanySignatory::create($data);

        return redirect()->route('admin.settings.signatories.index')
            ->with('status', 'Signatory added.');
    }

    public function edit(CompanySignatory $signatory): View
    {
        return view('admin.settings.signatories.edit', compact('signatory'));
    }

    public function update(Request $request, CompanySignatory $signatory): RedirectResponse
    {
        $data = $this->validated($request);

        if ($path = $this->storeSignature($request)) {
            if ($signatory->signature_path) {
                Storage::disk('public')->delete($signatory->signature_path);
            }
            $data['signature_path'] = $path;
        }

        $signatory->update($data);

        return redirect()->route('admin.settings.signatories.index')
            ->with('status', 'Signatory updated.');
    }

    public function destroy(CompanySignatory $signatory): RedirectResponse
    {
        if ($signatory->signature_path) {
            Storage::disk('public')->delete($signatory->signature_path);
        }

        $signatory->delete();

        return redirect()->route('admin.settings.signatories.index')
            ->with('status', 'Signatory removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'     => ['required', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:120'],
            'email'    => ['nullable', 'email', 'max:150'],
            'is_active'=> ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    private function storeSignature(Request $request): ?string
    {
        if ($request->hasFile('signature_image')) {
            return $request->file('signature_image')->store('signatories', 'public');
        }

        $dataUrl = (string) $request->input('signature_data', '');
        if ($dataUrl === '' || ! str_starts_with($dataUrl, 'data:image')) {
            return null;
        }

        [$meta, $encoded] = explode(',', $dataUrl, 2);
        $extension = str_contains($meta, 'image/jpeg') ? 'jpg' : 'png';
        $binary = base64_decode($encoded, true);

        if ($binary === false) {
            return null;
        }

        $path = 'signatories/'.Str::uuid().'.'.$extension;
        Storage::disk('public')->put($path, $binary);

        return $path;
    }
}
