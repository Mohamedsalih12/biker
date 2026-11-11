<div class="col-md-{{ $col }} mb-3">
    <label for="{{ $fk }}" class="form-label">{{ $label }}</label>
    <select 
        name="{{ $fk }}" 
        id="{{ $fk }}" 
        class="form-control mySelect2"
        data-placeholder="-- Select {{ $label }} --"
    >
        <option value="">-- Select {{ $label }} --</option>
        @foreach($items as $item)
            <option value="{{ $item->id }}"
                {{ (old( $fk, $arrayval ?->{$fk}) == $item->id ) ? 'selected' : '' }}
                >
                {{ $item->{$column} }}
            </option>
        @endforeach
    </select>
</div>
    @error($fk)
        <small class="text-danger">{{ $message }}</small>
    @enderror