<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AdminSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminSearchController extends Controller
{
    public function __invoke(Request $request, AdminSearchService $search): JsonResponse
    {
        $user = $request->user('admin') ?? $request->user();
        abort_unless($user, 403);

        return response()->json([
            'q' => (string) $request->query('q', ''),
            'groups' => $search->search($user, (string) $request->query('q', '')),
        ]);
    }
}
