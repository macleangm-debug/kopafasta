<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Lender;
use App\Models\Vendor;
use App\Services\PartnerProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class PartnerFaceVerificationController extends Controller
{
    public function store(Request $request, string $angle, PartnerProfileService $profiles): JsonResponse
    {
        $request->validate([
            'photo' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        try {
            return response()->json($profiles->storeFaceAngle(
                $this->entity(),
                $angle,
                $request->file('photo')
            ));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Request $request, string $angle, PartnerProfileService $profiles): JsonResponse
    {
        try {
            return response()->json($profiles->removeFaceAngle($this->entity(), $angle));
        } catch (\InvalidArgumentException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
        }
    }

    public function submit(Request $request, PartnerProfileService $profiles): RedirectResponse|JsonResponse
    {
        $entity = $this->entity();

        try {
            $result = $profiles->submitFace($entity);
        } catch (\InvalidArgumentException $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => $e->getMessage()], 422);
            }

            return $this->redirectToFace($request)->with('error', $e->getMessage());
        }

        $entity->refresh();
        $payload = $profiles->jsonSavedPayload($entity, 'face', (bool) ($result['celebrate'] ?? false));

        if ($request->expectsJson()) {
            return response()->json($payload);
        }

        return $this->redirectToFace($request);
    }

    private function entity(): Vendor|Lender
    {
        $user = Auth::user();
        abort_unless($user, 403);

        if ($user->role === 'investor') {
            $lender = Lender::query()->where('user_id', $user->id)->first();
            abort_unless($lender, 403);

            return $lender;
        }

        abort_unless($user->role === 'vendor', 403);
        $partner = Vendor::query()->where('user_id', $user->id)->first();
        abort_unless($partner, 403);

        return $partner;
    }

    private function redirectToFace(Request $request): RedirectResponse
    {
        $submitName = (string) $request->route()?->getName();
        $profileName = preg_replace('/\.face-verification\.submit$/', '.profile', $submitName);
        if (is_string($profileName) && $profileName !== $submitName && Route::has($profileName)) {
            return redirect()->route($profileName, ['section' => 'face']);
        }

        return back();
    }
}
