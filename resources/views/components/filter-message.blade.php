@if($message)
    <div class="alert alert-{{ $style }} alert-dismissible fade show alert-sm" role="alert">
        {{ $message }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        <!-- @if($clearUrl) -->
            <!-- <a href="{{-- route('agreement.contract.index', ['clear' => 1]) --}}"> -->
            <!-- <i class="fa-solid fa-xmark float-end" aria-hidden="true" style="color: #dc3545;"></i> -->
            <!-- </a> -->
        <!-- @endif -->
    </div>
@endif