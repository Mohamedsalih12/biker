<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LoginLog;
use App\Services\AuditService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    protected $startDate;

    protected $endDate;

    public function __construct(Request $request, protected AuditService $audit)
    {
        $this->startDate = $request->startDate ?? Carbon::now()->startOfDay()->toDateString();
        $this->endDate = $request->endDate ?? Carbon::now()->endOfDay()->toDateString();
    }

    public function index(Request $request)
    {
        $this->authorize('isAdmin');
        if ($request->has('clear')) {
            return redirect()->route('xpress.profile.activitylog');
        }

        $activitylogs = ActivityLog::query()
            ->with(['user:id,name', 'subject'])

            ->when($request->filled('searchBox'), function ($q) use ($request) {
                $search = '%'.$request->searchBox.'%';

                $q->where(function ($query) use ($search) {
                    $query->orWhere('log_name', 'like', $search)
                        ->orWhere('description', 'like', $search)
                        ->orWhere('subject_type', 'like', $search)
                        ->orWhere('event', 'like', $search)
                        ->orWhere('properties', 'like', $search)
                        ->orWhere('attribute_changes', 'like', $search)
                        ->orWhereHas('user', function ($subQ) use ($search) {
                            $subQ->where('name', 'like', $search);
                        });
                });
            })
            ->whereDate('created_at', '>=', $this->startDate)
            ->whereDate('created_at', '<=', $this->endDate)

            ->latest()
            ->paginate(15);
        $activitylogs->appends($request->except('page'));

        return view('admin.users.activitylog', compact('activitylogs'));
    }

    public function show(ActivityLog $activityLog)
    {
        $this->authorize('isAdmin');

        $activityLog->load([
            'user:id,name',
            'subject',
        ]);

        return response()->json([
            'id' => $activityLog->id,
            'log_name' => $activityLog->log_name,
            'description' => $activityLog->description,
            'event' => $activityLog->event,
            'created_at' => optional($activityLog->created_at)?->format('n/j/Y, g:i:s A'),

            'causer' => [
                'id' => $activityLog->user?->id,
                'name' => $activityLog->user->name ?? 'System',
                'type' => $activityLog->causer_type,
            ],

            'subject' => [
                'id' => $activityLog->subject_id,
                'type' => $activityLog->subject_type,
                'display' => $activityLog->subject_type
                    ? class_basename($activityLog->subject_type).' #'.$activityLog->subject_id
                    : 'N/A',
            ],

            'properties' => $activityLog->properties ?? [],
            'attribute_changes' => $activityLog->attribute_changes ?? [],
        ]);
    }

    public function loginlog(Request $request)
    {
        $this->authorize('isAdmin');

        // If clear filter button pressed, redirect to clean route (no query params)
        if ($request->has('clear')) {
            return redirect()->route('xpress.profile.loginlog');
        }

        $logs = LoginLog::query()
            ->with(['user:id,name'])

            ->when($request->filled('searchBox'), function ($query) use ($request) {
                $search = '%'.$request->searchBox.'%';
                $query->where(function ($query) use ($search) {
                    $query->where('pos_code', 'like', $search)
                        ->orWhere('ip_address', 'like', $search)
                        ->orWhere('country', 'like', $search)
                        ->orWhereHas('user', function ($subQ) use ($search) {
                            $subQ->where('name', 'like', $search);
                        });

                });
            })
            ->whereDate('created_at', '>=', $this->startDate)
            ->whereDate('created_at', '<=', $this->endDate)

            ->orderBy('id', 'desc')
            ->paginate(15);

        foreach ($logs as $log) {
            if ($log->country) {
                continue;
            }

            $country = $this->audit->countryCodeForIp($log->ip_address);

            if ($country) {
                $log->forceFill(['country' => $country])->save();
            }
        }

        $logs->appends($request->except('page'));

        return view('admin.users.loginlog', compact('logs'));
    }

    public function auditlog(Request $request)
    {
        $this->authorize('isAdmin');

        if ($request->has('clear')) {
            return redirect()->route('xpress.profile.auditlog');
        }

        $auditlogs = ActivityLog::query()
            ->when($request->filled('searchBox'), function ($query) use ($request) {
                $search = '%'.$request->searchBox.'%';

                $query->where(function ($query) use ($search) {
                    $query->where('description', 'like', $search)
                        ->orWhere('log_name', 'like', $search)
                        ->orWhere('event', 'like', $search)
                        ->orWhere('country', 'like', $search)
                        ->orWhere('properties', 'like', $search);
                });
            })
            ->whereDate('created_at', '>=', $this->startDate)
            ->whereDate('created_at', '<=', $this->endDate)
            ->where('properties', 'like', '%failed%')
            ->latest()
            ->paginate(15);

        foreach ($auditlogs as $auditlog) {
            if ($auditlog->country) {
                continue;
            }

            $country = $this->audit->countryCodeForIp($auditlog->properties['ip'] ?? null);

            if ($country) {
                $auditlog->forceFill(['country' => $country])->save();
            }
        }

        $auditlogs->appends($request->except('page'));

        return view('admin.users.auditlog', compact('auditlogs'));
    }
}
