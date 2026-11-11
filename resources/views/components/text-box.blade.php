<div class="mb-3">
    <label class="form-label">{{ $label }}</label>
    <input type="text" class="form-control" name="{{ $name }}" value="{{ $arrayval?->$name ?? null }}" required>
</div>