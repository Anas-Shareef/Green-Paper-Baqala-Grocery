<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50 text-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Baqqala — Grocery Management & POS' }}</title>
    
    <!-- Google Fonts: DM Sans, JetBrains Mono, Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300..800;1,9..40,300..800&family=JetBrains+Mono:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['DM Sans', 'Outfit', 'system-ui', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace'],
                    },
                    colors: {
                        emerald: {
                            50: '#ecfdf5',
                            100: '#d1fae5',
                            200: '#a7f3d0',
                            300: '#6ee7b7',
                            400: '#34d399',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            800: '#065f46',
                            900: '#064e3b',
                            950: '#022c22',
                        }
                    }
                }
            }
        }
    </script>
    @livewireStyles
    <style>
        [x-cloak] { display: none !important; }
        
        /* Custom Luca World Design System Classes (Emerald Green Light Theme) */
        :root {
            --glow-primary: #059669;
            --glow-secondary: #10b981;
            --sidebar-w: 260px;
            --topbar-h: 64px;
        }

        ::-webkit-scrollbar { width: 5px; height: 5px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 9999px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        /* Luca World Signature Top-Gradient KPI Card (Light Theme) */
        .kpi-card {
            position: relative;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 1.25rem;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px 0 rgba(0, 0, 0, 0.03);
            overflow: hidden;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .kpi-card::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            border-top-left-radius: 1rem;
            border-top-right-radius: 1rem;
            background: linear-gradient(90deg, #10b981, #059669);
        }
        .kpi-card:hover {
            border-color: rgba(5, 150, 105, 0.4);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05);
        }

        /* Luca World Emerald Action Button */
        .btn-glow {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 0.75rem;
            background: linear-gradient(135deg, #059669, #047857);
            padding: 0.625rem 1.25rem;
            font-size: 0.875rem;
            font-weight: 700;
            color: #ffffff;
            box-shadow: 0 4px 14px 0 rgba(5, 150, 105, 0.25);
            transition: all 0.15s ease-in-out;
            border: none;
            cursor: pointer;
        }
        .btn-glow:hover {
            background: linear-gradient(135deg, #10b981, #059669);
            box-shadow: 0 6px 20px 0 rgba(5, 150, 105, 0.35);
            transform: translateY(-1px);
        }
        .btn-glow:active {
            transform: scale(0.97);
        }

        /* Outline Button (Light Theme) */
        .btn-outline {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border-radius: 0.75rem;
            border: 1px solid #cbd5e1;
            background-color: #ffffff;
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
            font-weight: 600;
            color: #334155;
            transition: all 0.15s ease-in-out;
        }
        .btn-outline:hover {
            border-color: #059669;
            color: #059669;
            background-color: #f0fdf4;
        }

        /* Luca World Form Inputs (Light Theme) */
        .input-field, .select-field {
            width: 100%;
            border-radius: 0.75rem;
            border: 1px solid #cbd5e1;
            background-color: #f8fafc;
            padding: 0.625rem 0.875rem;
            font-size: 0.875rem;
            color: #0f172a;
            transition: all 0.15s ease-in-out;
            outline: none;
        }
        .input-field:focus, .select-field:focus {
            background-color: #ffffff;
            border-color: #059669;
            box-shadow: 0 0 0 3px rgba(5, 150, 105, 0.15);
        }

        /* Table Styling (Light Theme) */
        .tbl-head {
            background-color: #f8fafc;
            padding: 0.75rem 1rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #475569;
            border-bottom: 1px solid #e2e8f0;
        }
        .tbl-cell {
            border-bottom: 1px solid #f1f5f9;
            padding: 0.875rem 1rem;
            font-size: 0.875rem;
            color: #1e293b;
        }
        .tbl-row:hover {
            background-color: #f0fdf4;
        }

        /* Badge Pills (Light Theme) */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            border-radius: 9999px;
            padding: 0.25rem 0.625rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .badge-emerald {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid #a7f3d0;
        }
        .badge-amber {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid #fde68a;
        }
        .badge-rose {
            background-color: #fff1f2;
            color: #be123c;
            border: 1px solid #fecdd3;
        }
        .badge-slate {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }
    </style>
</head>
<body class="h-full font-sans antialiased bg-slate-50 text-slate-900 flex flex-col md:flex-row min-h-screen overflow-x-hidden">

    <!-- Mobile Header -->
    <header class="md:hidden bg-white border-b border-slate-200 p-4 flex items-center justify-between sticky top-0 z-50 shadow-sm">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center font-black text-xl text-white shadow-md shadow-emerald-600/20">
                B
            </div>
            <div>
                <h1 class="font-extrabold text-lg tracking-tight text-slate-900">Baqqala</h1>
                <p class="text-[10px] text-emerald-600 font-bold uppercase tracking-wider">Luca World Engine</p>
            </div>
        </div>
        <button x-data @click="$dispatch('toggle-mobile-sidebar')" class="p-2 text-slate-600 hover:text-slate-900 rounded-lg bg-slate-100">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
        </button>
    </header>

    <!-- Sidebar Navigation (Luca World Pure White Light Mode) -->
    <aside x-data="{ open: false }" 
           @toggle-mobile-sidebar.window="open = !open" 
           :class="open ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
           class="fixed md:static inset-y-0 left-0 w-[260px] bg-white border-r border-slate-200/80 z-50 transition-transform duration-300 flex flex-col justify-between shrink-0 shadow-sm">
        
        <div>
            <!-- Brand Logo Banner -->
            <div class="p-5 border-b border-slate-100 flex items-center justify-between">
                <a href="/admin/dashboard" class="flex items-center gap-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center font-black text-xl text-white shadow-md shadow-emerald-600/25 group-hover:scale-105 transition-transform">
                        B
                    </div>
                    <div>
                        <div class="font-black text-xl tracking-tight text-slate-900 flex items-center gap-1.5">
                            BAQQALA
                            <span class="text-[9px] uppercase tracking-widest bg-emerald-100 text-emerald-800 border border-emerald-200 px-1.5 py-0.5 rounded-full font-extrabold">ADMIN</span>
                        </div>
                        <p class="text-[11px] text-emerald-600 font-bold">Everyday Grocery Engine</p>
                    </div>
                </a>
            </div>

            <!-- Luca World Sidebar Navigation Links -->
            <nav class="p-3 space-y-1 text-xs font-semibold">
                <a href="/admin/dashboard" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('admin/dashboard') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 font-extrabold shadow-xs' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 00-1-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                    Dashboard Overview
                </a>

                <!-- POS Scanner Link -->
                <a href="/pos" class="flex items-center justify-between px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('pos') ? 'bg-emerald-600 text-white font-black shadow-md shadow-emerald-600/20' : 'bg-emerald-50/80 border border-emerald-200/80 text-emerald-800 hover:bg-emerald-100' }}">
                    <div class="flex items-center gap-3">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v1m6 11h2m-6 0h-2v4m0-11v3m0 0h.01M12 12h4.01M16 20h4M4 12h4m12 0h.01M5 8h2a1 1 0 001-1V5a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1zm12 0h2a1 1 0 001-1V5a1 1 0 00-1-1h-2a1 1 0 00-1 1v2a1 1 0 001 1zM5 20h2a1 1 0 001-1v-2a1 1 0 00-1-1H5a1 1 0 00-1 1v2a1 1 0 001 1z"></path></svg>
                        POS Scanner Counter
                    </div>
                    <span class="text-[9px] uppercase tracking-wider bg-emerald-600 text-white px-1.5 py-0.5 rounded font-black">FAST</span>
                </a>

                <div class="pt-3 pb-1 px-3.5 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Store Management</div>

                <a href="/admin/inventory" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('admin/inventory*') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                    Products & Inventory
                </a>

                <a href="/admin/receiving" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('admin/receiving*') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                    Stock Receiving Station
                </a>

                <a href="/admin/orders" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('admin/orders*') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                    Order Management
                </a>

                <a href="/admin/customers" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('admin/customers*') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Customer CRM & CSV
                </a>

                <div class="pt-3 pb-1 px-3.5 text-[10px] font-extrabold uppercase tracking-widest text-slate-400">Marketing & Analytics</div>

                <a href="/admin/whatsapp" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('admin/whatsapp*') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                    WhatsApp Broadcasts
                </a>

                <a href="/admin/expenses" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('admin/expenses*') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    Business Expenses
                </a>

                <a href="/admin/reports" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('admin/reports*') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                    <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Profit Statement Analytics
                </a>

                <a href="/admin/settings" class="flex items-center gap-3 px-3.5 py-2.5 rounded-xl transition-all {{ request()->is('admin/settings*') ? 'bg-emerald-50 text-emerald-700 border-l-4 border-emerald-600 font-extrabold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80' }}">
                    <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path></svg>
                    Settings & MOV
                </a>
            </nav>
        </div>

        <!-- User Profile Bar & Customer PWA Link -->
        <div class="p-4 border-t border-slate-100 space-y-3 bg-slate-50/50">
            <a href="http://localhost:5173" target="_blank" class="w-full py-2 px-3 rounded-xl bg-white hover:bg-slate-100 text-slate-700 border border-slate-200 text-xs font-semibold flex items-center justify-center gap-2 transition-all shadow-xs">
                <svg class="w-4 h-4 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                Customer React PWA
                <svg class="w-3.5 h-3.5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path></svg>
            </a>

            <div class="flex items-center justify-between pt-1">
                <div class="flex items-center gap-2.5 overflow-hidden">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-800 border border-emerald-200 flex items-center justify-center font-bold text-xs font-mono">
                        {{ strtoupper(substr(auth()->user()->name ?? 'A', 0, 1)) }}
                    </div>
                    <div class="truncate">
                        <div class="text-xs font-bold text-slate-900 truncate">{{ auth()->user()->name ?? 'Baqqala Admin' }}</div>
                        <div class="text-[10px] text-slate-500 uppercase font-bold">{{ auth()->user()->role ?? 'Super Admin' }}</div>
                    </div>
                </div>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="p-1.5 text-slate-400 hover:text-rose-600 hover:bg-slate-100 rounded-lg transition-colors" title="Logout">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <!-- Main Content Area (Light Slate Canvas) -->
    <main class="flex-1 flex flex-col min-w-0 bg-slate-50 overflow-y-auto">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
