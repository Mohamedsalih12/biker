<div class="col-md-{{ $col }} mb-3">
    <label for="{{ $column }}" class="form-label">{{ $label }}</label>
    <select 
        name="{{ $column }}" 
        id="{{ $column }}" 
        class="form-control mySelect2"
        data-placeholder="-- Select --"
    >
        <option value="">-- Select {{ $label }} --</option>
        @foreach($items as $item)
            <option value="{{ $item->{$column} }}"
                {{ (old( $column, $arrayval ?->{$column}) == $item->{$column}) ? 'selected' : '' }}>
                {{ $item->{$column} }}
            </option>
        @endforeach
    </select>
    @error($column)
        <small class="text-danger">{{ $message }}</small>
    @enderror
</div>