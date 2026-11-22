<?php

namespace App\Http\Controllers\Admin;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends AdminController
{
    public function index(Request $request)
    {
        $logs = AuditLog::with('user:id,name')
            ->when($request->input('action'), fn ($q, $a) => $q->where('action', $a))
            ->latest('created_at')->latest('id')->paginate(40)->withQueryString();
        $actions = AuditLog::distinct()->orderBy('action')->pluck('action');

        return view('admin.audit.index', compact('logs', 'actions'));
    }
}
