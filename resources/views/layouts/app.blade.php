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
        .sidebar-link { @apply flex items-center gap-3 px-4 py-2.5 rounded-lg text-sm font-medium transition-all duration-200; }
        .sidebar-link:hover { @apply bg-white/10; }
        .sidebar-link.active { @apply bg-white/15 text-white shadow-sm; }
        .stat-card { @apply bg-white dark:bg-slate-800 rounded-xl shadow-sm border border-slate-200 dark:border-slate-700 p-6 transition-all duration-200 hover:shadow-md; }
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 3px; }
        .dark ::-webkit-scrollbar-thumb { background: #475569; }
    </style>
</head>
<body class="bg-slate-50 dark:bg-slate-900 text-slate-800 dark:text-slate-200 min-h-screen transition-colors duration-300">

    <div class="flex min-h-screen" x-data="{ sidebarOpen: true, notifOpen: false, profileOpen: false, unreadCount: 0 }"
         x-init="fetch('{{ route('notifications.unread-count') }}').then(r => r.json()).then(d => unreadCount = d.count).catch(() => {})">

        {{-- Sidebar --}}
        <aside class="fixed inset-y-0 left-0 z-30 flex flex-col bg-gradient-to-b from-primary-700 via-primary-800 to-primary-950 text-white transition-all duration-300"
               :class="sidebarOpen ? 'w-[260px]' : 'w-[72px]'">

            {{-- Logo --}}
            <div class="flex items-center gap-3 px-5 h-16 border-b border-white/10 shrink-0">
                <div class="flex items-center justify-center w-9 h-9 bg-white/15 rounded-lg shrink-0">
                    <i class="fas fa-layer-group text-lg"></i>
                </div>
                <span class="text-lg font-bold tracking-tight whitespace-nowrap overflow-hidden transition-all duration-300"
                      :class="sidebarOpen ? 'opacity-100 w-auto' : 'opacity-0 w-0'">OmniChannel</span>
            </div>

            {{-- Navigation --}}
            <nav class="flex-1 overflow-y-auto py-4 px-3 space-y-1">
                @php
                    $navItems = [
                        ['route' => 'dashboard', 'icon' => 'fas fa-chart-pie', 'label' => 'Dashboard'],
                        ['route' => 'pos.index', 'icon' => 'fas fa-cash-register', 'label' => 'POS'],
                        ['route' => 'orders.index', 'icon' => 'fas fa-shopping-bag', 'label' => 'Orders'],
                        ['route' => 'products.index', 'icon' => 'fas fa-box-open', 'label' => 'Products'],
                        ['route' => 'customers.index', 'icon' => 'fas fa-users', 'label' => 'Customers'],
                        ['route' => 'whatsapp.index', 'icon' => 'fab fa-whatsapp', 'label' => 'WhatsApp'],
                        ['route' => 'ads.index', 'icon' => 'fas fa-bullhorn', 'label' => 'Ads Analytics'],
                        ['route' => 'reports.index', 'icon' => 'fas fa-file-invoice', 'label' => 'Reports'],
                        ['route' => 'settings.index', 'icon' => 'fas fa-cog', 'label' => 'Settings'],
                    ];
                @endphp

                @foreach($navItems as $item)
                    <a href="{{ route($item['route']) }}"
                       class="sidebar-link {{ request()->routeIs($item['route'] . '*') || (isset($item['route']) && request()->routeIs(explode('.', $item['route'])[0] . '.*')) ? 'active' : 'text-white/70' }}"
                       title="{{ $item['label'] }}">
                        <i class="{{ $item['icon'] }} w-5 text-center text-base shrink-0"></i>
                        <span class="whitespace-nowrap overflow-hidden transition-all duration-300"
                              :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">{{ $item['label'] }}</span>
                    </a>
                @endforeach
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
                        <p class="text-xs text-white/50 truncate">{{ auth()->user()->email ?? '' }}</p>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}" class="mt-1">
                    @csrf
                    <button type="submit" class="sidebar-link text-white/60 hover:text-white w-full">
                        <i class="fas fa-sign-out-alt w-5 text-center shrink-0"></i>
                        <span class="whitespace-nowrap overflow-hidden transition-all duration-300"
                              :class="sidebarOpen ? 'opacity-100' : 'opacity-0 w-0'">Logout</span>
                    </button>
                </form>
            </div>
        </aside>

        {{-- Main Content --}}
        <div class="flex-1 flex flex-col transition-all duration-300" :class="sidebarOpen ? 'ml-[260px]' : 'ml-[72px]'">

            {{-- Top Header --}}
            <header class="sticky top-0 z-20 bg-white/80 dark:bg-slate-800/80 backdrop-blur-md border-b border-slate-200 dark:border-slate-700 h-16 flex items-center justify-between px-6 shrink-0">
                <div class="flex items-center gap-4">
                    <button @click="sidebarOpen = !sidebarOpen" class="text-slate-500 hover:text-slate-700 dark:hover:text-slate-300 transition">
                        <i class="fas fa-bars text-lg"></i>
                    </button>
                    <h1 class="text-lg font-semibold text-slate-800 dark:text-white hidden sm:block">@yield('title', 'Dashboard')</h1>
                </div>

                <div class="flex items-center gap-3">
                    {{-- Search --}}
                    <div class="hidden md:flex items-center bg-slate-100 dark:bg-slate-700/60 rounded-lg px-3 py-2 gap-2 w-64 focus-within:ring-2 focus-within:ring-primary-500 transition">
                        <i class="fas fa-search text-slate-400 text-sm"></i>
                        <input type="text" placeholder="Search..." class="bg-transparent border-none outline-none text-sm w-full text-slate-700 dark:text-slate-200 placeholder-slate-400">
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
                             class="absolute right-0 mt-2 w-80 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
                                <span class="font-semibold text-sm">Notifications</span>
                                <a href="{{ route('notifications.index') }}" class="text-xs text-primary-500 hover:underline">View all</a>
                            </div>
                            <div class="max-h-64 overflow-y-auto p-2">
                                <p class="text-sm text-slate-400 text-center py-4">Loading...</p>
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
                            <i class="fas fa-chevron-down text-xs text-slate-400 hidden sm:block"></i>
                        </button>
                        <div x-show="open" x-cloak x-transition
                             class="absolute right-0 mt-2 w-48 bg-white dark:bg-slate-800 rounded-xl shadow-xl border border-slate-200 dark:border-slate-700 py-2">
                            <a href="{{ route('profile') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-user w-4"></i> Profile
                            </a>
                            <a href="{{ route('settings.index') }}" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                                <i class="fas fa-cog w-4"></i> Settings
                            </a>
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
            <main class="flex-1 p-6">
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
        </div>
    </div>

    @stack('scripts')
</body>
</html>
