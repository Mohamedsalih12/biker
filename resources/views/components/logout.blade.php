<a href="{{ route('logout') }}"
    onclick="event.preventDefault();
        document.getElementById('logout-form').submit();">
    <i class="fa fa-sign-out" aria-hidden="true" style="color: #ff0000;"></i>
</a>
<form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
    @csrf 
</form>