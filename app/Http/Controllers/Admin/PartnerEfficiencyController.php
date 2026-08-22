<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AffiliateEvaluationService;
use App\Services\PartnerEfficiencyService;
use Illuminate\View\View;

class PartnerEfficiencyController extends Controller
{
    public function index(PartnerEfficiencyService $efficiency, AffiliateEvaluationService $affiliates): View
    {
        $board = $efficiency->board();
        $board['affiliates'] = $affiliates->volumeBoard();

        return view('admin.partners.efficiency', $board);
    }
}
