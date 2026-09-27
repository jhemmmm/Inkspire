<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\FilterAuditTrailRequest;
use App\Models\AuditLog;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class AuditTrailController extends Controller
{
    /**
     * Show the read-only, filterable audit trail for Admin.
     */
    public function index(FilterAuditTrailRequest $request): Response
    {
        $entries = AuditLog::query()
            ->with('user:id,name,email,role')
            ->latest('created_at')
            ->when($request->filled('user'), fn ($q) => $q->where('user_id', $request->integer('user')))
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->string('action')))
            ->when($request->filled('from'), fn ($q) => $q->whereDate('created_at', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('created_at', '<=', $request->date('to')))
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('admin/AuditTrail', [
            'entries' => $entries,
            'filters' => $request->only(['user', 'action', 'from', 'to']),
            'users' => User::query()->select(['id', 'name'])->orderBy('name')->get(),
            'actions' => ['created', 'updated', 'deleted', 'login', 'logout', 'failed_login', 'lockout'],
        ]);
    }
}
