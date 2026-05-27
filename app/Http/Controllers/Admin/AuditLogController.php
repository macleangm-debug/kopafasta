<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AuditLogController extends Controller
{
    public function index()
    {
        $logs = AuditLog::query()
            ->latest('id')
            ->paginate(50);

        return view('admin.audit-logs.index', compact('logs'));
    }

    public function show($id)
    {
        $log = AuditLog::findOrFail($id);
        return view('admin.audit-logs.show', compact('log'));
    }
}
