<div class="col-md-{{ $col }} mb-3">
    <label class="form-label">{{ $label }}</label>
    <input type="text" readonly class="form-control" name="{{ $name }}" 
        value="{{ $arrayval?->$name ?? null }}"
    >
</div>