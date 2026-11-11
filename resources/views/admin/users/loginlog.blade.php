@extends('layouts.xpress')
@section('title', 'Login Logs')
@section('searchBox', 'Search by Username, POS Code')
@section('content')
    <div class="container-fluid">
        <div class="topbar">
            <div>
                <h3 class="mb-0">Login Logs</h3>
                <div class="small-muted">Data loads from Database table <b>Login Logs</b></div>
            </div>

            <!-- Buttons -->
            <div class="btn-group-top">
                <form action="{{ route('xpress.profile.loginlog') }}" method="GET" style="display:inline-block;">
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
                            <a href="{{ route('xpress.profile.loginlog') }}" class="btn btn-outline-danger btn-sm">Reset Filters</a> 
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
                            <th>POS Code</th>
                            <th>IP</th>
                            <th>Country</th>
                            <th>Login Time</th>
                            <th>Logout Time</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        @foreach ($logs as $log)
                            <tr>
                                <td class="text-center">{{ $log->id }}</td>
                                <td class="text-center">{{ $log->user->name ?? null }}</td>
                                <td class="text-center">{{ $log->pos_code }}</td>
                                <td class="text-center">{{ $log->ip_address ?? 'N/A' }}</td>
                                <td class="text-center">{{ $log->country ?? 'N/A' }}</td>
                                <td class="text-center">{{ $log->login_time }}</td>
                                <td class="text-center">{{ $log->logout_time ?? null }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <x-pagination-info :paginator="$logs" />
            </div>            
        </div>
    </div>
@endsection