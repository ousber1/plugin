<!DOCTYPE html>
<html lang="en" x-data="{ darkMode: localStorage.getItem('darkMode') === 'true' }" x-init="$watch('darkMode', val => localStorage.setItem('darkMode', val))" :class="{ 'dark': darkMode }">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - OmniChannel</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eef2ff', 100: '#e0e7ff', 200: '#c7d2fe', 300: '#a5b4fc',
                            400: '#818cf8', 500: '#6366f1', 600: '#4f46e5', 700: '#4338ca',
                            800: '#3730a3', 900: '#312e81', 950: '#1e1b4b',
                        }
                    }
                }
            }
        }
    </script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            color: rgba(255,255,255,0.7);
            transition: all 0.2s;
            text-decoration: none;
            white-space: nowrap;
        }
        .sidebar-link:hover {
            background: rgba(255,255,255,0.1);
            color: #fff;
        }
        .sidebar-link.active {
            background: rgba(255,255,255,0.15);
            color: #fff;
            box-shadow: 0 1px 2px rgba(0,0,0,0.1);
        }
        .sidebar-section-title {
            display: block;
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255,255,255,0.35);
            padding: 0.75rem 1rem 0.375rem;
            margin-top: 0.25rem;
        }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #475569; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 min-h-screen transition-colors duration-300">

    <div class="flex min-h-screen" x-data="{ sidebarOpen: true, mobileMenu: false, notifOpen: false, profileOpen: false, unreadCount: 0 }"
         x-init="fetch('{{ route('notifications.unread-count') }}').then(r => r.json()).then(d => unreadCount = d.count).catch(() => {})">

        {{-- Mobile Overlay --}}
        <div x-show="mobileMenu" x-cloak @click="mobileMenu = false"
             class="fixed inset-0 bg-black/50 z-40 lg:hidden" x-transition.opacity></div>

        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-50 flex flex-col bg-gradient-to-b from-primary-700 via-primary-800 to-primary-950 text-white transition-all duration-300 overflow-hidden"
               :class="[
                   sidebarOpen ? 'w-[260px]' : 'w-[72px]',
                   mobileMenu ? 'translate-x-0' : '-translate-x-full lg:translate-x-0'
               ]">

            {{-- Logo --}}
            <div class="flex items-center gap-3 px-5 h-16 border-b border-white/10 shrink-0">
                <div class="flex items-center justify-center w-9 h-9 bg-white/15 rounded-lg shrink-0">
                    <i class="fas fa-layer-group text-lg"></i>
                </div>
                <span class="text-lg font-bold tracking-tight whitespace-nowrap overflow-hidden transition-all duration-300"
                      :class="sidebarOpen ? 'opacity-100 w-auto' : 'opacity-0 w-0'">OmniChannel</span>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 overflow-y-auto py-3 px-3" style="display:flex;flex-direction:column;gap:2px;">

                <span class="sidebar-section-title" x-show="sidebarOpen" x-transition>Main</span>

                <a href="{{ route('dashboard') }}"
                   class="sidebar-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard">
                    <i class="fas fa-chart-pie w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Dashboard</span>
                </a>

                <span class="sidebar-section-title" x-show="sidebarOpen" x-transition>Sales</span>

                <a href="{{ route('pos.index') }}"
                   class="sidebar-link {{ request()->routeIs('pos.*') ? 'active' : '' }}" title="POS Terminal">
                    <i class="fas fa-cash-register w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">POS Terminal</span>
                </a>

                <a href="{{ route('orders.index') }}"
                   class="sidebar-link {{ request()->routeIs('orders.*') ? 'active' : '' }}" title="Orders">
                    <i class="fas fa-shopping-bag w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Orders</span>
                </a>

                <span class="sidebar-section-title" x-show="sidebarOpen" x-transition>Inventory</span>

                <a href="{{ route('products.index') }}"
                   class="sidebar-link {{ request()->routeIs('products.*') ? 'active' : '' }}" title="Products">
                    <i class="fas fa-box-open w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Products</span>
                </a>

                <a href="{{ route('products.index', ['stock' => 'low']) }}"
                   class="sidebar-link {{ request()->is('products*') && request('stock') === 'low' ? 'active' : '' }}" title="Low Stock">
                    <i class="fas fa-exclamation-triangle w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Low Stock Alerts</span>
                </a>

                <span class="sidebar-section-title" x-show="sidebarOpen" x-transition>CRM</span>

                <a href="{{ route('customers.index') }}"
                   class="sidebar-link {{ request()->routeIs('customers.*') ? 'active' : '' }}" title="Customers">
                    <i class="fas fa-users w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Customers</span>
                </a>

                <a href="{{ route('whatsapp.index') }}"
                   class="sidebar-link {{ request()->routeIs('whatsapp.*') ? 'active' : '' }}" title="WhatsApp">
                    <i class="fab fa-whatsapp w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">WhatsApp CRM</span>
                </a>

                <span class="sidebar-section-title" x-show="sidebarOpen" x-transition>Marketing</span>

                <a href="{{ route('ads.index') }}"
                   class="sidebar-link {{ request()->routeIs('ads.*') ? 'active' : '' }}" title="Ads">
                    <i class="fas fa-bullhorn w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Ads Intelligence</span>
                </a>

                <span class="sidebar-section-title" x-show="sidebarOpen" x-transition>Analytics</span>

                <a href="{{ route('reports.index') }}"
                   class="sidebar-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" title="Reports">
                    <i class="fas fa-chart-bar w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Reports</span>
                </a>

                <a href="{{ route('reports.sales') }}"
                   class="sidebar-link {{ request()->routeIs('reports.sales') ? 'active' : '' }}" title="Sales Report">
                    <i class="fas fa-file-invoice-dollar w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Sales Report</span>
                </a>

                <a href="{{ route('reports.profit') }}"
                   class="sidebar-link {{ request()->routeIs('reports.profit') ? 'active' : '' }}" title="Profit Report">
                    <i class="fas fa-coins w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Profit & Loss</span>
                </a>

                <span class="sidebar-section-title" x-show="sidebarOpen" x-transition>System</span>

                <a href="{{ route('notifications.index') }}"
                   class="sidebar-link {{ request()->routeIs('notifications.*') ? 'active' : '' }}" title="Notifications">
                    <i class="fas fa-bell w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Notifications</span>
                    <span x-show="unreadCount > 0" x-text="unreadCount" x-cloak
                          class="ml-auto bg-red-500 text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full"
                          :class="sidebarOpen ? '' : 'hidden'"></span>
                </a>

                <a href="{{ route('settings.index') }}"
                   class="sidebar-link {{ request()->routeIs('settings.*') ? 'active' : '' }}" title="Settings">
                    <i class="fas fa-cog w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Settings</span>
                </a>

                <a href="{{ route('profile') }}"
                   class="sidebar-link {{ request()->routeIs('profile') ? 'active' : '' }}" title="Profile">
                    <i class="fas fa-user-circle w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">My Profile</span>
                </a>

                @if(auth()->user()->role === 'admin')
                <a href="{{ route('admin.users') }}"
                   class="sidebar-link {{ request()->routeIs('admin.*') ? 'active' : '' }}" title="User Management">
                    <i class="fas fa-user-shield w-5 text-center text-base shrink-0"></i>
                    <span class="overflow-hidden transition-all duration-300"
                          :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">User Management</span>
                </a>
                @endif
            </nav>

            {{-- User Info --}}
            <div class="border-t border-white/10 p-3 shrink-0">
                <div class="flex items-center gap-3 px-2 py-2">
                    <div class="w-9 h-9 rounded-full bg-white/20 flex items-center justify-center shrink-0 text-sm font-bold">
                        {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                    </div>
                    <div class="overflow-hidden transition-all duration-300"
                         :class="sidebarOpen ? 'opacity-100 w-auto' : 'opacity-0 w-0'">
                        <p class="text-sm font-medium truncate">{{ auth()->user()->name ?? 'User' }}</p>
                        <p class="text-xs text-white/50 truncate">{{ ucfirst(auth()->user()->role ?? 'staff') }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="sidebar-link" style="color:rgba(255,255,255,0.6);width:100%">
                        <i class="fas fa-sign-out-alt w-5 text-center shrink-0"></i>
                        <span class="overflow-hidden transition-all duration-300"
                              :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main Content --}}
        <div class="flex-1 flex flex-col transition-all duration-300 min-w-0"
             :class="sidebarOpen ? 'lg:ml-[260px]' : 'lg:ml-[72px]'">

            {{-- Top Header --}}
            <header class="sticky top-0 z-20 bg-white/80 dark:bg-slate-800/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-700 h-16 flex items-center justify-between px-4 sm:px-6 shrink-0">
                <div class="flex items-center gap-3">
                    {{-- Mobile menu button --}}
                    <button @click="mobileMenu = !mobileMenu" class="lg:hidden text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition p-1">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    {{-- Desktop sidebar toggle --}}
                    <button @click="sidebarOpen = !sidebarOpen" class="hidden lg:block text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition p-1">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-slate-800 dark:text-white hidden sm:block">@yield('title', 'Dashboard')</h1>
                </div>

                <div class="flex items-center gap-2 sm:gap-3">
                    {{-- Search --}}
                    <div class="hidden md:flex items-center bg-slate-100 dark:bg-slate-700/60 rounded-lg px-3 py-2 gap-2 w-56 focus-within:ring-2 focus-within:ring-primary-500 transition">
                        <i class="fas fa-search text-slate-400 text-sm"></i>
                        <input type="text" placeholder="Search..." class="bg-transparent border-none outline-none text-sm w-full text-slate-700 dark:text-slate-200 placeholder-slate-400">
                    </div>

                    {{-- Quick Actions --}}
                    <div class="relative hidden sm:block" x-data="{ open: false }" @click.away="open = false">
                        <button @click="open = !open" class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition" title="Quick Actions">
                            <i class="fas fa-bolt text-lg"></i>
                        </button>
                        <div x-show="open" x-cloak x-transition
                             class="absolute right-0 mt-2 w-56 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-2 z-50">
                            <p class="px-4 py-1.5 text-xs font-semibold text-slate-400 uppercase">Quick Actions</p>
                            <a href="{{ route('pos.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-cash-register w-4 text-primary-500"></i> New POS Sale
                            </a>
                            <a href="{{ route('orders.create') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-plus w-4 text-emerald-500"></i> New Order
                            </a>
                            <a href="{{ route('products.create') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-box w-4 text-amber-500"></i> Add Product
                            </a>
                            <a href="{{ route('customers.create') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-user-plus w-4 text-blue-500"></i> Add Customer
                            </a>
                            <hr class="my-1 border-slate-200 dark:border-slate-700">
                            <a href="{{ route('reports.sales') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-chart-line w-4 text-purple-500"></i> Sales Report
                            </a>
                        </div>
                    </div>

                    {{-- Notifications --}}
                    <div class="relative" x-data="{ open: false }" @click.away="open = false">
                        <button @click="open = !open" class="relative p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                            <i class="fas fa-bell text-lg"></i>
                            <span x-show="unreadCount > 0" x-text="unreadCount"
                                  class="absolute -top-0.5 -right-0.5 bg-red-500 text-white text-[10px] font-bold w-5 h-5 rounded-full flex items-center justify-center"
                                  x-cloak></span>
                        </button>
                        <div x-show="open" x-cloak x-transition
                             class="absolute right-0 mt-2 w-80 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden z-50">
                            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                <span class="font-semibold text-sm">Notifications</span>
                                <a href="{{ route('notifications.index') }}" class="text-xs text-primary-500 hover:underline">View all</a>
                            </div>
                            <div class="max-h-64 overflow-y-auto p-2">
                                <p class="text-sm text-slate-400 text-center py-4">No new notifications</p>
                            </div>
                        </div>
                    </div>

                    {{-- Dark Mode Toggle --}}
                    <button @click="darkMode = !darkMode" class="p-2 text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                        <i class="fas fa-moon text-lg" x-show="!darkMode"></i>
                        <i class="fas fa-sun text-lg" x-show="darkMode" x-cloak></i>
                    </button>

                    {{-- Profile Dropdown --}}
                    <div class="relative" x-data="{ open: false }" @click.away="open = false">
                        <button @click="open = !open" class="flex items-center gap-2 p-1.5 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition">
                            <div class="w-8 h-8 rounded-full bg-primary-500 flex items-center justify-center text-white text-sm font-bold">
                                {{ substr(auth()->user()->name ?? 'U', 0, 1) }}
                            </div>
                            <span class="text-sm font-medium text-slate-700 dark:text-slate-300 hidden sm:block">{{ auth()->user()->name ?? 'User' }}</span>
                            <i class="fas fa-chevron-down text-xs text-slate-400 hidden sm:block"></i>
                        </button>
                        <div x-show="open" x-cloak x-transition
                             class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-2 z-50">
                            <div class="px-4 py-2 border-b border-slate-200 dark:border-slate-700">
                                <p class="text-sm font-medium">{{ auth()->user()->name ?? 'User' }}</p>
                                <p class="text-xs text-slate-400">{{ auth()->user()->email ?? '' }}</p>
                            </div>
                            <a href="{{ route('profile') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-user w-4"></i> My Profile
                            </a>
                            <a href="{{ route('settings.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-cog w-4"></i> Settings
                            </a>
                            @if(auth()->user()->role === 'admin')
                            <a href="{{ route('admin.users') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-user-shield w-4"></i> Users
                            </a>
                            @endif
                            <hr class="my-1 border-slate-200 dark:border-slate-700">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50 dark:hover:bg-slate-700 w-full text-left">
                                    <i class="fas fa-sign-out-alt w-4"></i> Logout
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            {{-- Page Content --}}
            <main class="flex-1 p-4 sm:p-6">
                {{-- Flash Messages --}}
                @if(session('success'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition
                         class="mb-4 bg-emerald-50 dark:bg-emerald-900/30 border border-emerald-200 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 px-4 py-3 rounded-lg flex items-center justify-between">
                        <span class="text-sm"><i class="fas fa-check-circle mr-2"></i>{{ session('success') }}</span>
                        <button @click="show = false" class="text-emerald-500 hover:text-emerald-700"><i class="fas fa-times"></i></button>
                    </div>
                @endif

                @if(session('error'))
                    <div x-data="{ show: true }" x-show="show" x-transition
                         class="mb-4 bg-red-50 dark:bg-red-900/30 border border-red-200 dark:border-red-800 text-red-700 dark:text-red-400 px-4 py-3 rounded-lg flex items-center justify-between">
                        <span class="text-sm"><i class="fas fa-exclamation-circle mr-2"></i>{{ session('error') }}</span>
                        <button @click="show = false" class="text-red-500 hover:text-red-700"><i class="fas fa-times"></i></button>
                    </div>
                @endif

                @yield('content')
            </main>

            {{-- Footer --}}
            <footer class="border-t border-slate-200 dark:border-slate-700 px-6 py-3 text-center">
                <p class="text-xs text-slate-400">OmniChannel Business Management System v1.0 &copy; {{ date('Y') }}</p>
            </footer>
        </div>
    </div>

    @stack('scripts')
</body>
</html>
