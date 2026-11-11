@if (session('message'))
    <div class="alert alert-success alert-dismissible fade show alert-sm" role="alert">
        {{ session('message') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
@if ($errors ->any())
    <div class="alert alert-danger alert-dismissible fade show alert-sm" role="alert">
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        @endforeach
    </div>
@endif
@if (session()->has('import_failures'))
    <div class="alert alert-danger alert-dismissible fade show alert-sm" role="alert">
        @foreach (session()->get('import_failures') as $validation)
            <li>Row {{ $validation->row() }}: {{ implode(', ', $validation->errors()) }}</li>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        @endforeach
    </div>
@endif