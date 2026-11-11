@extends('layouts.xpress')
@section('title', 'Failed Logins')
@section('searchBox', 'Action, ip, Route, Method, User')
@section('content')
    <div class="container-fluid">
        <div class="topbar">
            <div>
                <h3 class="mb-0">Failed Logins</h3>
                <div class="small-muted">Data loads from Database table <b>Audit Log</b></div>
            </div>

            <!-- Buttons -->
            <div class="btn-group-top">
                <form action="{{ route('xpress.profile.auditlog') }}" method="GET" style="display:inline-block;">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="startDate" class="form-control form-control-sm" value="{{ request('startDate') }}">
                        </div>
                        <div class="col-auto">
                            <label class="form-label">End Date</label>
                            <input type="date" name="endDate" class="form-control form-control-sm" value="{{ request('endDate') }}">
                        </div>
                        <div class="col-auto align-self-end">
                            <button class="btn btn-outline-primary btn-sm">Submit</button>
                        </div>
                        <div class="col-auto align-self-end">
                            <a href="{{ route('xpress.profile.auditlog') }}" class="btn btn-outline-danger btn-sm">Reset Filters</a> 
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-body">
                <x-session-message />
                <table class="table table-striped table-hover mb-0">
                    <thead class="text-center">
                        <tr>
                            <th>ID</th>
                            <th>Username</th>
                            <th>Action</th>
                            <th>Route</th>
                            <th>Method</th>
                            <th>ip</th>
                            <th>Country</th>
                            <th>User Agent</th>
                            <th>Created At</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        @foreach ($auditlogs as $log)
                            <tr>
                                @php($properties = $log->properties ?? [])
                                <td class="text-center">{{ $log->id }}</td>
                                <td class="text-center">{{ $properties['email'] ?? ($log->user->name ?? null) }}</td>
                                <td class="text-center">{{ $properties['status'] ?? ($log->description ?? null) }}</td>
                                <td class="text-center">{{ $properties['route_uri'] ?? ($properties['route_name'] ?? null) }}</td>
                                <td class="text-center">{{ $properties['method'] ?? null }}</td>
                                <td class="text-center">{{ $properties['ip'] ?? null }}</td>
                                <td class="text-center">{{ $log->country ?? ($properties['country'] ?? 'N/A') }}</td>
                                <td class="text-center">{{ $properties['user_agent'] ?? null }}</td>
                                <td class="text-center">{{ $log->created_at ?? null }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <x-pagination-info :paginator="$auditlogs" />
            </div>
        </div>
    </div>    
@endsection