@can('create', \App\Models\User::class)
    <div class="modal fade" id="quick-add-customer-modal" tabindex="-1" aria-labelledby="quick-add-customer-title" aria-hidden="true">
        <div class="modal-dialog">
            <form id="quick-add-customer-form" action="{{ route('xpress.booking-customers.store') }}" method="POST" class="modal-content">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="quick-add-customer-title">Quick Add Customer</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Create a customer and select them for this booking. Your booking details will be kept.</p>
                    <div id="quick-add-errors" class="alert alert-danger d-none" role="alert" tabindex="-1"></div>
                    @foreach (['name' => ['Name', 'text', 255], 'phone' => ['Phone', 'tel', 30], 'aadhaar' => ['Aadhaar', 'text', 14]] as $field => [$label, $type, $maxLength])
                        <div class="mb-3">
                            <label for="quick-add-{{ $field }}" class="form-label">{{ $label }} (required)</label>
                            <input id="quick-add-{{ $field }}" name="{{ $field }}" type="{{ $type }}" class="form-control" maxlength="{{ $maxLength }}" required @if ($field === 'aadhaar') inputmode="numeric" aria-describedby="quick-add-aadhaar-help" @endif>
                            @if ($field === 'aadhaar') <div id="quick-add-aadhaar-help" class="form-text">Enter 12 digits, with or without spaces.</div> @endif
                        </div>
                    @endforeach
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="quick-add-save">Add Customer</button>
                </div>
            </form>
        </div>
    </div>
@endcan

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const customerSelect = $('#customer_id');
            customerSelect.select2({
                placeholder: 'Search Aadhaar or customer name',
                allowClear: true,
                width: '100%',
                ajax: {
                    url: customerSelect.data('lookup-url'),
                    dataType: 'json',
                    delay: 250,
                    cache: false,
                    data: function (params) { return {q: params.term || '', page: params.page || 1}; }
                }
            });

            const form = document.getElementById('quick-add-customer-form');
            if (!form) return;
            const modalElement = document.getElementById('quick-add-customer-modal');
            const saveButton = document.getElementById('quick-add-save');
            const errors = document.getElementById('quick-add-errors');
            let saving = false;

            modalElement.addEventListener('shown.bs.modal', function () {
                document.getElementById('quick-add-name').focus();
            });
            modalElement.addEventListener('hide.bs.modal', function (event) {
                if (saving) event.preventDefault();
            });
            form.addEventListener('submit', async function (event) {
                event.preventDefault();
                if (saving) return;
                saving = true;
                saveButton.disabled = true;
                saveButton.textContent = 'Adding customer…';
                errors.classList.add('d-none');
                errors.textContent = '';
                try {
                    const response = await fetch(form.action, {
                        method: 'POST', body: new FormData(form), credentials: 'same-origin',
                        headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
                    });
                    const data = await response.json().catch(function () { return {}; });
                    if (!response.ok) {
                        const messages = data.errors ? Object.values(data.errors).flat().join(' ') : '';
                        throw new Error(messages || (response.status === 419 ? 'Your session expired. Copy your unsaved booking details before reloading this page and signing in again.' : 'Customer could not be added. Check your connection and try again.'));
                    }
                    if (!data.id || typeof data.text !== 'string') throw new Error('Unexpected response. Search for the customer before retrying.');
                    customerSelect.append(new Option(data.text, data.id, true, true)).trigger('change');
                    form.reset();
                    const notice = document.getElementById('booking-customer-added');
                    notice.textContent = 'Customer added and selected. Continue with the booking.';
                    notice.classList.remove('d-none');
                    saving = false;
                    bootstrap.Modal.getOrCreateInstance(modalElement).hide();
                } catch (error) {
                    errors.textContent = error.message;
                    errors.classList.remove('d-none');
                    errors.focus();
                } finally {
                    saving = false;
                    saveButton.disabled = false;
                    saveButton.textContent = 'Add Customer';
                }
            });
        });
    </script>
@endpush
