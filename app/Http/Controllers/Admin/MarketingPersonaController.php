<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingPersona;
use App\Services\Marketing\MarketingDemoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MarketingPersonaController extends Controller
{
    public function index(MarketingDemoService $demos): View
    {
        $demos->ensureSystemPersonas();

        return view('admin.growth.personas.index', [
            'personas' => MarketingPersona::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', 'in:borrower,plus,affiliate'],
            'summary' => ['nullable', 'string', 'max:500'],
            'traits' => ['nullable', 'string', 'max:500'],
            'grade' => ['nullable', 'in:bronze,silver,gold,platinum'],
            'trust' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
        $traits = array_values(array_filter(array_map('trim', explode(',', (string) ($data['traits'] ?? '')))));
        MarketingPersona::query()->create([
            'key' => \Illuminate\Support\Str::slug($data['name']).'-'.substr(sha1($data['name'].microtime()), 0, 6),
            'name' => $data['name'],
            'role' => $data['role'],
            'summary' => $data['summary'] ?? null,
            'traits' => $traits,
            'defaults' => [
                'grade' => $data['grade'] ?? null,
                'trust' => isset($data['trust']) ? (int) $data['trust'] : null,
                'plus' => $data['role'] === 'plus',
            ],
            'restricted' => $data['role'] === 'affiliate',
            'is_system' => false,
            'created_by' => $request->user()?->id,
        ]);

        return back()->with('status', 'Persona saved. Personas never change real customers.');
    }

    public function destroy(MarketingPersona $persona): RedirectResponse
    {
        abort_if($persona->is_system, 403, 'System personas cannot be deleted.');
        $persona->delete();

        return back()->with('status', 'Persona removed.');
    }
}
