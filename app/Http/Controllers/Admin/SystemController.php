<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ReleaseInfoService;
use Illuminate\View\View;

class SystemController extends Controller
{
    public function show(ReleaseInfoService $release): View
    {
        return view('admin.settings.system', [
            'release' => $release->snapshot(),
        ]);
    }
}
