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
        $data = $this->payload($request);
        $data['signature_path'] = $this->storeSignature($request);
        $data['stamp_path'] = $this->storeStamp($request);

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
        $data = $this->payload($request);

        if ($request->boolean('remove_signature')) {
            if ($signatory->signature_path) {
                Storage::disk('public')->delete($signatory->signature_path);
            }
            $data['signature_path'] = null;
        } elseif ($path = $this->storeSignature($request)) {
            if ($signatory->signature_path) {
                Storage::disk('public')->delete($signatory->signature_path);
            }
            $data['signature_path'] = $path;
        }

        if ($request->boolean('remove_stamp')) {
            if ($signatory->stamp_path) {
                Storage::disk('public')->delete($signatory->stamp_path);
            }
            $data['stamp_path'] = null;
        } elseif ($path = $this->storeStamp($request)) {
            if ($signatory->stamp_path) {
                Storage::disk('public')->delete($signatory->stamp_path);
            }
            $data['stamp_path'] = $path;
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
        if ($signatory->stamp_path) {
            Storage::disk('public')->delete($signatory->stamp_path);
        }

        $signatory->delete();

        return redirect()->route('admin.settings.signatories.index')
            ->with('status', 'Signatory removed.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name'           => ['required', 'string', 'max:120'],
            'position'       => ['nullable', 'string', 'max:120'],
            'email'          => ['nullable', 'email', 'max:150'],
            'signatory_type' => ['required', 'in:company,legal_advocate'],
            'is_active'      => ['nullable', 'boolean'],
            'signature_image'=> ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'stamp_image'    => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'remove_signature' => ['nullable', 'boolean'],
            'remove_stamp'     => ['nullable', 'boolean'],
        ]) + ['is_active' => $request->boolean('is_active', true)];
    }

    /** @return array<string, mixed> */
    private function payload(Request $request): array
    {
        $data = $this->validated($request);

        unset(
            $data['signature_image'],
            $data['stamp_image'],
            $data['remove_signature'],
            $data['remove_stamp'],
        );

        return $data;
    }

    private function storeStamp(Request $request): ?string
    {
        if (! $request->hasFile('stamp_image')) {
            return null;
        }

        return $request->file('stamp_image')->store('signatories/stamps', 'public');
    }

    private function storeSignature(Request $request): ?string
    {
        if ($request->hasFile('signature_image')) {
            return $request->file('signature_image')->store('signatories', 'public');
        }

        if (! $request->boolean('signature_touched')) {
            return null;
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
