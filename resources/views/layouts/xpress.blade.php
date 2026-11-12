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

        <script src="{{ asset('xpress/js/all.js') }}" crossorigin="anonymous"></script>

        <link href="{{ asset('xpress/css/custom.css') }}" rel="stylesheet" />

        <!-- UI overrides to harmonize button/dropdown spacing (non-invasive) -->
        <link href="{{ asset('xpress/css/override-ui.css') }}" rel="stylesheet" />
        <link href="{{ asset('xpress/css/bootstrap-icons.css') }}" rel="stylesheet" />
        <link rel="preload" href="{{ asset('admin/vendors/font-awesome/fonts/fontawesome-webfont.woff2') }}?v=4.7.0" as="font" type="font/woff2" crossorigin>
        <link href="{{ asset('xpress/css/font-awesome.min.css') }}" rel="stylesheet" />
        <link href="{{ asset('xpress/css/font-awesome-fix.css') }}" rel="stylesheet" />
        <link href="{{ asset('xpress/css/datatables.css') }}" rel="stylesheet" />

        <!-- Fieldt360 brand theme (loaded last on purpose) -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600,700" rel="stylesheet" />
        <link href="{{ asset('xpress/css/t360-theme.css') }}" rel="stylesheet" />

        @livewireStyles

        @vite('resources/js/app.js')

    </head>
    <!-- <body class="sb-nav-fixed sb-sidenav-toggled"> -->
    <body class="sb-nav-fixed">

        <!-- NAVBAR -->
        @auth
        <nav class="sb-topnav navbar navbar-expand navbar-dark bg-dark">

            <a class="navbar-brand" href="{{ route('home') }}">
                <img src="{{ asset('admin/images/GMBLogo.png') }}" alt="GMB" height="32">
            </a>

            <!-- Sidebar Toggle-->
            <button class="btn btn-link btn-sm order-1 order-lg-0 toggle-after-sidebar" id="sidebarToggle"> <i class="fas fa-bars"></i> </button>

            <!-- Navbar Brand-->
            <div class="container-fluid">
                <div class="collapse navbar-collapse" id="navbarNavDarkDropdown">
                    <ul class="navbar-nav">
                        <li class="nav-item dropdown">
                        <button class="btn btn-dark dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                            Dropdown
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark color: #fff">
                            <li class="dropdown-submenu">
                                <a class="dropdown-item dropdown-toggle" href="#">Source Table</a>
                                <ul class="dropdown-menu dropdown-menu-dark">
                                    <li><a class="dropdown-item" href="{{ route('home') }}">Dashboard</a></li>
                                </ul>
                            </li>
                            @can('manage-users')
                            <li class="dropdown-submenu">
                                <a class="dropdown-item dropdown-toggle" href="#">User Pages</a>
                                <ul class="dropdown-menu dropdown-menu-dark">
                                    <li class="dropdown-submenu">
                                        <a class="dropdown-item dropdown-toggle" href="#">Users</a>
                                        <ul class="dropdown-menu dropdown-menu-dark">
                                            <li><a class="dropdown-item" href="{{ url('admin/users') }}">View Users</a></li>
                                            <li><a class="dropdown-item" href="{{ url('admin/users/create') }}">Add User</a></li>
                                        </ul>
                                    </li>
                                </ul>
                            </li>
                            @endcan
                            @can('manage-vehicles')
                            <li class="dropdown-submenu">
                                <a class="dropdown-item dropdown-toggle" href="#">Vehicles</a>
                                <ul class="dropdown-menu dropdown-menu-dark">
                                    <li><a class="dropdown-item" href="{{ route('xpress.vehicles.index') }}">View Vehicles</a></li>
                                    <li><a class="dropdown-item" href="{{ route('xpress.vehicles.create') }}">Add Vehicle</a></li>
                                </ul>
                            </li>
                            @endcan
                            <li><a class="dropdown-item" href="{{ route('settings') }}">Settings</a></li>
                        </ul>
                        </li>
                    </ul>
                    <h4 class="text-white">@yield('subTitle')</h4>
                    <h6 class="text-white">@yield('subTitle')</h6>
                </div>
            
                <!-- Navbar Search-->
                <form class="w-50 xpress-navbar-search">
                    <div class="input-group">
                        <input class="form-control" type="text" id="searchBox" name="searchBox" 
                            value="{{Request::get('searchBox') ?? '' }}"
                            placeholder="@yield('searchBox')" aria-label="Search for..." aria-describedby="btnNavbarSearch" />
                        <button class="btn btn-primary" type="submit"><i class="fas fa-search"></i></button>
                        <button type="submit" name="clear" value="1" class="btn btn-warning">
                            <i class="fa fa-times-circle" aria-hidden="true"></i>
                        </button>
                    </div>
                </form>
            </div>
        
            <ul class="navbar-nav ms-auto ms-md-0 me-3 me-lg-4">
                <li class="nav-item dropdown">
                    <li class="nav-item nav-profile dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown" id="profileDropdown">
                        <span class="nav-profile-name">{{ Auth::user()->name ?? '' }}</span>
                        </a>
                        <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
                            <a class="dropdown-item" href="{{ route('xpress.profile.edit') }}">
                                <i class="mdi mdi-cog text-primary"></i>
                                User Profile
                            </a>
                            <a class="dropdown-item" href="{{ route('settings') }}">
                                <i class="mdi mdi-cog text-primary"></i>
                                Site Settings
                            </a>
                            <a class="dropdown-item" href="{{ route('logout') }}"
                                onclick="event.preventDefault();
                                                document.getElementById('logout-form').submit();">
                            <i class="mdi mdi-logout text-primary"></i>{{ __('Logout') }}
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                                @csrf 
                            </form>
                        </div>
                    </li>
                </li>
            </ul>
        </nav>
        @endauth

        <!-- SIDEBAR -->
        <div id="layoutSidenav">
            @auth
            <div id="layoutSidenav_nav">
                <nav class="sb-sidenav accordion sb-sidenav-dark" id="sidenavAccordion">
                    <div class="sb-sidenav-menu">
                        <div class="nav">
                            <div class="sb-sidenav-menu-heading">Interface</div>

                            <!-- Static Menu Items (No Collapse) -->
                            <li class="nav-item">
                                <a class="nav-link {{ Request::is('admin/dashboard') ? 'active' : '' }}"
                                href="{{ route('home') }}" aria-expanded="false" aria-controls="ui-basic">
                                <i class="fas fa-home"></i>&nbsp;&nbsp;&nbsp;&nbsp;
                                <span class="menu-title">Dashboard</span>
                                </a>
                            </li>
                            
                            @can('manage-users')
                            <x-sidebar-collapse-item
                                id="auth"
                                title="User Pages"
                                icon="fa fa-user"
                                :patterns="[
                                    'admin/users',
                                    'admin/users/*',
                                ]">

                                <li class="nav-item">
                                    <a class="nav-link {{ Request::is('admin/users') ? 'active' : '' }}"
                                    href="{{ url('admin/users') }}">
                                        View Users
                                    </a>
                                </li>
                            </x-sidebar-collapse-item>
                            @endcan
                            @can('manage-vehicles')
                            <x-sidebar-collapse-item id="vehicles" title="Vehicles" icon="fa fa-motorcycle" :patterns="['admin/vehicles', 'admin/vehicles/*']">
                                <li class="nav-item">
                                    <a class="nav-link {{ Request::is('admin/vehicles') ? 'active' : '' }}" href="{{ route('xpress.vehicles.index') }}">View Vehicles</a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link {{ Request::is('admin/vehicles/create') ? 'active' : '' }}" href="{{ route('xpress.vehicles.create') }}">Add Vehicle</a>
                                </li>
                            </x-sidebar-collapse-item>
                            @endcan
                        </div>
                    </div>
                    <div class="sb-sidenav-footer">
                        <div class="small">Created By:</div>
                        Mohamed Salih
                    </div>
                </nav>
            </div>
            @endauth

            <div id="layoutSidenav_content">
                        
                <main>
                    @yield('content')
                </main>

                <footer class="py-4 bg-light mt-auto">
                    <div class="container-fluid px-4">
                        <div class="d-flex align-items-center justify-content-between small">
                            <!-- <div class="text-muted">Copyright &copy; Your Website 2026</div> -->
                            @if($appSetting?->website_name)
                            <div><i class="fas fa-globe"></i> {{ $appSetting->website_name }}</div>
                            @endif

                            @if($appSetting?->website_url)
                            <div><x-safe-link :href="$appSetting->website_url"><i class="fas fa-link"></i> Visit Website</x-safe-link></div>
                            @endif

                            @if($appSetting?->address)
                            <div><a href="https://maps.google.com/?q={{ urlencode($appSetting->address) }}" style="text-decoration: none;"><i class="fas fa-map-marker-alt"></i> {{ $appSetting->address }}</a></div>
                            @endif

                            @if($appSetting?->phone_number_1)
                            <div><a href="tel:{{ $appSetting->phone_number_1 }}" style="text-decoration: none;"><i class="fas fa-phone"></i> {{ $appSetting->phone_number_1 }}</a></div>
                            @endif

                            @if($appSetting?->email_1)
                            <div><a href="mailto:{{ $appSetting->email_1 }}" style="text-decoration: none;"><i class="fas fa-envelope"></i> {{ $appSetting->email_1 }}</a></div>
                            @endif

                            @if($appSetting?->facebook)
                            <div><x-safe-link :href="$appSetting->facebook"><i class="fab fa-facebook"></i> Facebook</x-safe-link></div>
                            @endif

                            @if($appSetting?->twitter)
                            <div><x-safe-link :href="$appSetting->twitter"><i class="fab fa-twitter"></i> Twitter</x-safe-link></div>
                            @endif

                            @if($appSetting?->instagram)
                            <div><x-safe-link :href="$appSetting->instagram"><i class="fab fa-instagram"></i> Instagram</x-safe-link></div>
                            @endif

                            @if($appSetting?->linkedin)
                            <div><x-safe-link :href="$appSetting->linkedin"><i class="fab fa-linkedin"></i> LinkedIn</x-safe-link></div>
                            @endif

                            @if($appSetting?->youtube)
                            <div><x-safe-link :href="$appSetting->youtube"><i class="fab fa-youtube"></i> YouTube</x-safe-link></div>
                            @endif
                        
                            <div>
                                <!-- <a href="#">Privacy Policy</a> &middot; -->
                                <a href="#" style="text-decoration: none;">Terms &amp; Conditions</a>
                            </div>
                        </div>
                    </div>
                </footer>
            </div>
        </div>
        
        <script src="{{ asset('xpress/js/jquery-3.7.1.min.js') }}"></script>

        <script src="{{ asset('xpress/js/bootstrap.bundle.min.js') }}" crossorigin="anonymous"></script>
        <script src="{{ asset('xpress/js/scripts.js') }}"></script>

        <script src="{{ asset('xpress/js/simple-datatables.min.js') }}" crossorigin="anonymous"></script>
        <script src="{{ asset('xpress/js/datatables-simple-demo.js') }}"></script>

        <script src="{{ asset('xpress/js/select2.min.js') }}"></script>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            $('.mySelect2').each(function() {
                let placeholder = $(this).data('placeholder') || 'Select an option';
                $(this).select2({
                    placeholder: placeholder,
                    allowClear: true,
                    width: '100%'
                });

                // Ensure pre-selected value is properly set for Laravel
                let selectedVal = $(this).find('option[selected]').val();
                if (selectedVal) {
                    $(this).val(selectedVal).trigger('change');
                }
            });
        });
        </script>
        <script src="{{ asset('xpress/js/sweetalert.min.js') }}"></script>

        <script src="{{ asset('xpress/js/alertify.min.js') }}"></script>
        <script src="{{ asset('xpress/js/custom.js') }}"></script>
        <script src="{{ asset('xpress/js/datatables.js') }}"></script>
        <script src="{{ asset('xpress/js/datatables.min.js') }}"></script>

        @yield('scripts')


        @livewireScripts

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

            document.addEventListener('DOMContentLoaded', function () {
                document.querySelectorAll('.collapse').forEach(function (collapseEl) {

                    collapseEl.addEventListener('show.bs.collapse', function () {
                        const trigger = document.querySelector(`[href="#${this.id}"]`);
                        const icon = trigger?.querySelector('.toggle-icon');

                        if (icon) {
                            icon.classList.remove('fa-chevron-down');
                            icon.classList.add('fa-chevron-up');
                        }
                    });

                    collapseEl.addEventListener('hide.bs.collapse', function () {
                        const trigger = document.querySelector(`[href="#${this.id}"]`);
                        const icon = trigger?.querySelector('.toggle-icon');

                        if (icon) {
                            icon.classList.remove('fa-chevron-up');
                            icon.classList.add('fa-chevron-down');
                        }
                    });
                });
            });
        </script>
        
        @stack('scripts')
    </body>
</html>
