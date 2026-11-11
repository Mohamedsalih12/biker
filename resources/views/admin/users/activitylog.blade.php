@extends('layouts.xpress')
@section('title', 'Activity Log')
@section('searchBox', 'Log Name, Description, Subject type, Event, Username')
@section('content')
    <div class="container-fluid">
        <div class="topbar">
            <div>
                <h3 class="mb-0">Activty Log</h3>
                <div class="small-muted">Data loads from Database table <b>Activity Log</b></div>
            </div>

            <!-- Buttons -->
            <div class="btn-group-top">
                <form action="{{ route('xpress.profile.activitylog') }}" method="GET" style="display:inline-block;">
                    <div class="row g-2 align-items-end">
                        <div class="col-auto align-self-center">
                            <strong><label class="form-label">Start Date</label></strong>
                        </div>
                        <div class="col-auto align-self-center">
                            <input type="date" name="startDate" class="form-control form-control-sm" value="{{ request('endDate', now()->format('Y-m-d')) }}">
                        </div>
                        <div class="col-auto align-self-center">
                            <strong><label class="form-label">End Date</label></strong>
                        </div>
                        <div class="col-auto align-self-center">
                            <input type="date" name="endDate" class="form-control form-control-sm" value="{{ request('endDate', now()->format('Y-m-d')) }}">
                        </div>
                        <div class="col-auto align-self-center">
                            <button class="btn btn-outline-primary btn-sm">Submit</button>
                        </div>
                        <div class="col-auto align-self-center">
                            <a href="{{ route('xpress.profile.activitylog') }}" class="btn btn-outline-danger btn-sm">Reset Filters</a> 
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
                            <th>Log Name</th>
                            <th>Description</th>
                            <th>subject_type</th>
                            <th>event</th>
                            <th>Username</th>
                            <th>created_at</th>
                        </tr>
                    </thead>
                    <tbody class="text-center">
                        @foreach ($activitylogs as $log)
                            <tr>
                                <td class="text-center">{{ $log->id }}</td>
                                <td class="text-center">
                                    <button 
                                        type="button"
                                        class="btn btn-sm btn-view-details"
                                        data-url="{{ route('admin.activitylog.show', $log->id) }}"
                                    >{{ $log->log_name ?? null }}
                                    </button>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('admin.activitylog.show', $log->id) }}" @style(['color: #000', 'text-decoration: none']) >
                                        {{ $log->description ?? null  }}
                                    </a>
                                </td>
                                <td class="text-center">{{ $log->subject_type ?? null  }}</td>
                                <td class="text-center">{{ $log->event ?? null  }}</td>
                                <td class="text-center">{{ $log->user->name ?? null  }}</td>
                                <td class="text-center">{{ $log->created_at ?? null  }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <x-pagination-info :paginator="$activitylogs" />
            </div>
        </div>
    </div>
<!-- Activity Details Modal -->
<div class="modal fade" id="activityDetailsModal" tabindex="-1" aria-labelledby="activityDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <div>
                    <h5 class="modal-title mb-1" id="activityDetailsModalLabel">Activity Details</h5>
                    <small class="text-muted" id="modalSubtitle">Loading...</small>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="row g-3">
                    <!-- Event Information -->
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white fw-semibold">
                                Event Information
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="text-muted small">Event Type</div>
                                    <span class="badge rounded-pill bg-info-subtle text-info-emphasis" id="modalEventType">-</span>
                                </div>

                                <div class="mb-3">
                                    <div class="text-muted small">Description</div>
                                    <div id="modalDescription">-</div>
                                </div>

                                <div class="mb-0">
                                    <div class="text-muted small">Date & Time</div>
                                    <div id="modalCreatedAt">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Context -->
                    <div class="col-md-6">
                        <div class="card h-100 border-0 shadow-sm">
                            <div class="card-header bg-white fw-semibold">
                                Context
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="text-muted small">Performed by</div>
                                    <div id="modalCauser">-</div>
                                </div>

                                <div class="mb-0">
                                    <div class="text-muted small">Subject</div>
                                    <div id="modalSubject">-</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Properties -->
                    <div class="col-12">
                        <div class="card border-0 shadow-sm">
                            <div class="card-header bg-white fw-semibold">
                                Activity Properties
                            </div>
                            <div class="card-body">
                                <pre id="modalProperties" class="mb-0 p-3 rounded text-light" style="background:#0b1220; min-height:140px; white-space:pre-wrap;">{}</pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const modalEl = document.getElementById('activityDetailsModal');
        const modal = new bootstrap.Modal(modalEl);

        const modalSubtitle   = document.getElementById('modalSubtitle');
        const modalEventType  = document.getElementById('modalEventType');
        const modalDescription= document.getElementById('modalDescription');
        const modalCreatedAt  = document.getElementById('modalCreatedAt');
        const modalCauser     = document.getElementById('modalCauser');
        const modalSubject    = document.getElementById('modalSubject');
        const modalProperties = document.getElementById('modalProperties');

        document.querySelectorAll('.btn-view-details').forEach(button => {
            button.addEventListener('click', async function (e) {
                const buttonEl = e.currentTarget || this;
                const url = buttonEl?.dataset?.url || buttonEl?.getAttribute('data-url') || null;

                if (!url) {
                    console.warn('Activity details URL missing');
                    return;
                }

                modalSubtitle.textContent    = 'Loading...';
                modalEventType.textContent   = '-';
                modalDescription.textContent = '-';
                modalCreatedAt.textContent   = '-';
                modalCauser.textContent      = '-';
                modalSubject.textContent     = '-';
                modalProperties.textContent  = '{}';

                modal.show();

                try {
                    const response = await fetch(url, {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        }
                    });

                    if (!response.ok) {
                        throw new Error('Failed to load activity details.');
                    }

                    const data = await response.json();

                    modalSubtitle.textContent    = data.description ?? 'Activity details';
                    const eventValue = (data.event ?? '').toLowerCase();
                    modalEventType.className = 'badge rounded-pill';
                    if (eventValue === 'created') {
                        modalEventType.classList.add('bg-success');
                    } 
                    else if (eventValue === 'updated') {
                        modalEventType.classList.add('bg-warning', 'text-dark');
                    } 
                    else if (eventValue === 'deleted') {
                        modalEventType.classList.add('bg-danger');
                    } 
                    else if (eventValue === 'view') {
                        modalEventType.classList.add('bg-info', 'text-dark');
                    } 
                    else {
                        modalEventType.classList.add('bg-secondary');
                    }
                    modalEventType.textContent = (data.event ?? '-').toUpperCase();
                    modalDescription.textContent = data.description ?? '-';
                    modalCreatedAt.textContent   = data.created_at ?? '-';
                    modalCauser.textContent      = data.causer?.name ?? 'System';
                    modalSubject.textContent     = data.subject?.type
                        ? `${data.subject.type} #${data.subject.id ?? ''}`
                        : 'N/A';

                    modalProperties.textContent = JSON.stringify({
                        ...(data.properties ?? {}),
                        ...(data.attribute_changes ?? {}),
                    }, null, 4);

                } catch (error) {
                    modalSubtitle.textContent = 'Error loading details';
                    modalProperties.textContent = error.message;
                }
            });
        });
    });
</script>
@endsection
