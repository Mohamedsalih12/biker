<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class Audit
{
    public static function log(
        string $action,
        array $meta = [],
        ?string $auditableType = null,
        ?int $auditableId = null,
        ?Request $request = null
    ): void {
        $request ??= request();

        AuditLog::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'auditable_type' => $auditableType,
            'auditable_id' => $auditableId,
            'route' => optional($request->route())->getName() ?? $request->path(),
            'method' => $request->method(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 2000),
            'meta' => $meta,
        ]);
    }
}
