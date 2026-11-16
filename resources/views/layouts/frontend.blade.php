<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8" />
        <meta http-equiv="X-UA-Compatible" content="IE=edge" />
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
        <meta name="description" content="" />
        <meta name="author" content="" />
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <link rel="icon" type="image/png" href="{{ asset('admin/images/GMBSmall.png') }}">
        <title>@yield('title')</title>
        
        <!-- Bootstrap 5 FIRST -->
        <link href="{{ asset('xpress/css/bootstrap.min.css') }}" rel="stylesheet">

        <link href="{{ asset('xpress/css/style.min.css') }}" rel="stylesheet" />
        <link href="{{ asset('xpress/css/styles.css') }}" rel="stylesheet" />
        <link href="{{ asset('xpress/css/select2.min.css') }}" rel="stylesheet" />
        <link rel="stylesheet" href="{{ asset('xpress/css/alertify.min.css') }}"/>
        <link rel="stylesheet" href="{{ asset('xpress/css/default.min.css') }}"/>
        <script src="{{ asset('xpress/js/jquery-3.7.1.min.js') }}"></script>

        <script src="{{ asset('xpress/js/all.js') }}" crossorigin="anonymous"></script>

        <!--SALIH to refresh custom style and remove cache-->
        <link href="{{ asset('xpress/css/custom.css') }}" rel="stylesheet" />

        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">        
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />
        <link href="{{ asset('xpress/css/fleet-colors.css') }}" rel="stylesheet" />

    </head>
    <body class="sb-nav-fixed">
        <main>
            @yield('content')
        </main>
        <footer class="py-4 bg-light mt-auto">
            <div class="container-fluid px-4">
                <div class="d-flex align-items-center justify-content-between small">
                    <div class="text-muted">Copyright &copy; Your Website 2026</div>
                    <div>
                        <a href="#">Privacy Policy</a><!-- SIDEBAR -->
                        &middot;
                        <a href="#">Terms &amp; Conditions</a>
                    </div>
                </div>
            </div>
        </footer>
    </body>

    <script src="{{ asset('xpress/js/select2.min.js') }}"></script>
        <script>
            $(document).ready(function() {
                $('.mySelect2').select2();
            });
    </script>

    <script>
        (function () {
            var tokenMeta = document.querySelector('meta[name="csrf-token"]');
            var token = tokenMeta ? tokenMeta.getAttribute('content') : null;
            if (!token) return;
            try {
                if (window.axios) {
                    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;
                }
            } catch (e) {}
            try {
                if (window.$ && $.ajaxSetup) {
                    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': token } });
                }
            } catch (e) {}
        })();
    </script>

    @stack('scripts')
    
</html>
