@php
    $value = old($name, $arrayval?->$name ?? null);

    if ($type === 'date' && $value) {
        try {
            $value = \Carbon\Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            $value = null;
        }
    }
@endphp

<div class="col-md-{{ $col }} mb-3"> 
    <div>
        <label class="form-label">{{ $label }}</label>
        <input type="{{ $type }}" class="form-control" name="{{ $name }}" 
            value="{{ $value }}">
    </div>
</div>