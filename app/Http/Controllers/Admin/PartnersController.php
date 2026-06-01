<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PartnerService;
use Illuminate\View\View;

class PartnersController extends Controller
{
    public function index(PartnerService $partners): View
    {
        return view('admin.partners.index', [
            'roleOptions' => $partners->roleOptions(),
        ]);
    }
}
