<div class="col-md-12 mb-3">
    <label for="{{ $name }}" class="form-label">{{ $label }}</label>
    <input type="number" class="form-control" id="{{ $name }}" name="{{ $name }}" value="{{ $arrayval?->$name ?? null }}" required>
</div>