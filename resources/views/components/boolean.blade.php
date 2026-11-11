<div class="mb-3">
    <label class="form-label">{{ $label }}</label>
    <select name="{{ $name }}" class="form-control" required>
        <option value="">Select</option>
        <option value="{{ $option1 }}" 
            {{ isset($arrayval) && $arrayval->{$name} == $option1 ? 'selected' : '' }}>{{ $option1 }}
        </option>
        <option value="{{ $option2 }}"
            {{ isset($arrayval) && $arrayval->{$name} == $option2 ? 'selected' : '' }}>{{ $option2 }}
        </option>
    </select>
</div>