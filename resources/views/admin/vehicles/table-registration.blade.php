<div class="vehicle-registration">
    <span>{{ $vehicle->registration_number }}</span>
    @if ($alert !== null)
        <a href="{{ route('xpress.maintenance-logs.show', $alert['log_id']) }}"
           class="vehicle-maintenance-alert vehicle-maintenance-alert-{{ $alert['severity'] }}"
           aria-label="{{ ucfirst($alert['severity']).': '.$alert['message'].'. View maintenance log for '.$vehicle->registration_number }}">
            <span class="d-inline-flex align-items-center gap-1 fw-semibold">
                <i class="bi {{ $alert['severity'] === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-exclamation-triangle-fill' }}" aria-hidden="true"></i>
                {{ ucfirst($alert['severity']) }}
            </span>
            <span class="d-block small">{{ $alert['message'] }}</span>
        </a>
    @endif
</div>
