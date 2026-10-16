<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\AccessScope;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->hasPermission('audit_logs.view'), 403);

        $logs = AuditLog::query()
            ->with(['user', 'company'])
            ->when(! $request->user()->isSuperAdmin(), function ($query) use ($request): void {
                $ids = app(AccessScope::class)->companyIds($request->user());
                $query->whereIn('company_id', $ids === [] ? [0] : $ids);
            })
            ->when($request->filled('event'), fn ($query) => $query->where('event', $request->string('event')->toString()))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('created_at', '<=', $request->date('to')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.audit.index', compact('logs'));
    }
}
