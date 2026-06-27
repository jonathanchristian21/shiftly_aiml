<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Shiftly') - Hospital Scheduling System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: { 
                    sans: ['Inter', 'system-ui', 'sans-serif'], 
                    mono: ['JetBrains Mono', 'monospace'] 
                },
                colors: {
                    ink: { DEFAULT: '#0D1117', mute: '#6B7280', dim: '#9CA3AF' },
                    sky: { DEFAULT: '#1B6EF5', hover: '#1558D6', soft: '#EFF5FF' },
                    sheet: '#F8F9FA',
                },
            }
        }
    }
    </script>
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            background: #F8F9FA;
            color: #0D1117;
        }
        
        .mono { font-family: 'JetBrains Mono', 'Courier New', monospace; }

        /* ═══════════════════════════════════════════
           EMPLOYEE SIDEBAR — Clean White / Personal
        ═══════════════════════════════════════════ */
        .sidebar-employee {
            background: #FFFFFF;
            border-right: 1px solid #E5E7EB;
        }

        .sidebar-employee .brand-title { color: #0D1117; font-weight: 700; }
        .sidebar-employee .brand-sub   { color: #6B7280; font-size: 11px; }

        .sidebar-employee .nav-item {
            color: #6B7280;
            font-size: 13px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .sidebar-employee .nav-item:hover {
            background: #F0FDF4;
            color: #059669;
        }
        .sidebar-employee .nav-item.active {
            background: #ECFDF5;
            color: #059669;
            font-weight: 600;
            border-left: 3px solid #10B981;
            padding-left: calc(12px - 3px);
        }
        .sidebar-employee .role-badge {
            background: #F0FDF4;
            border: 1px solid #BBF7D0;
            border-radius: 6px;
            padding: 8px 12px;
            margin-bottom: 12px;
        }
        .sidebar-employee .role-badge .role-label { color: #059669; font-size: 10px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; }
        .sidebar-employee .role-badge .role-name  { color: #0D1117; font-size: 13px; font-weight: 600; }
        .sidebar-employee .role-badge .role-email { color: #6B7280; font-size: 11px; }
        .sidebar-employee .divider-nav { height: 1px; background: #F3F4F6; margin: 10px 0; }
        .sidebar-employee .logout-btn {
            background: #F9FAFB;
            border: 1px solid #E5E7EB;
            color: #6B7280;
            border-radius: 8px;
            padding: 9px 14px;
            font-size: 13px;
            font-weight: 500;
            display: flex; align-items: center; gap: 8px; width: 100%; cursor: pointer; transition: all 0.2s;
        }
        .sidebar-employee .logout-btn:hover { background: #FEF2F2; border-color: #FECACA; color: #DC2626; }

        /* Shared nav-item base */
        .nav-item { transition: all 0.2s ease; text-decoration: none; }

        /* Cards */
        .card {
            background: #FFFFFF;
            border-radius: 12px;
            border: 1px solid #E5E7EB;
            box-shadow: 0 1px 2px rgba(0,0,0,0.05);
        }
        .card-hover { transition: all 0.2s ease; }
        .card-hover:hover { box-shadow: 0 4px 6px rgba(0,0,0,0.07); transform: translateY(-2px); }
        
        /* Buttons */
        .btn {
            display: inline-flex; align-items: center; justify-content: center;
            gap: 8px; border-radius: 8px; font-size: 13px; font-weight: 600;
            padding: 10px 18px; transition: all 0.2s ease; cursor: pointer;
            border: none; text-decoration: none;
        }
        .btn-primary { background: #1B6EF5; color: white; }
        .btn-primary:hover { background: #1558D6; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(27,110,245,0.3); }
        .btn-secondary { background: white; color: #0D1117; border: 1px solid #E5E7EB; }
        .btn-secondary:hover { background: #F8F9FA; border-color: #D1D5DB; }
        .btn-success { background: #10B981; color: white; }
        .btn-success:hover { background: #059669; transform: translateY(-1px); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        
        /* Badges */
        .badge {
            display: inline-flex; align-items: center;
            padding: 4px 10px; border-radius: 6px;
            font-size: 11px; font-weight: 600;
            letter-spacing: 0.03em; text-transform: uppercase;
        }
        .badge-primary   { background: #EFF5FF; color: #1B6EF5; }
        .badge-success   { background: #D1FAE5; color: #059669; }
        .badge-warning   { background: #FEF3C7; color: #D97706; }
        .badge-danger    { background: #FEE2E2; color: #DC2626; }
        .badge-secondary { background: #F3F4F6; color: #6B7280; }
        
        /* Forms */
        input, select, textarea {
            width: 100%; border: 1px solid #E5E7EB;
            border-radius: 8px; padding: 10px 14px;
            font-size: 14px; font-family: 'Inter', sans-serif;
            transition: all 0.2s ease; background: white; color: #0D1117;
        }
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: #1B6EF5;
            box-shadow: 0 0 0 3px #EFF5FF;
        }
        label { display: block; font-size: 13px; font-weight: 600; color: #0D1117; margin-bottom: 6px; }
        
        /* Tables */
        .table-minimal { width: 100%; border-collapse: collapse; }
        .table-minimal thead { background: #F9FAFB; position: sticky; top: 0; z-index: 10; }
        .table-minimal thead th { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.08em; color: #6B7280; padding: 12px 16px; text-align: left; border-bottom: 1px solid #E5E7EB; }
        .table-minimal tbody td { font-size: 13px; padding: 12px 16px; border-bottom: 1px solid #F3F4F6; vertical-align: middle; }
        .table-minimal tbody tr { transition: background 0.15s ease; }
        .table-minimal tbody tr:hover { background: #F8FAFF; }
        
        /* Stat Cards */
        .stat-card { background: white; border-radius: 12px; padding: 20px; border: 1px solid #E5E7EB; }
        .stat-value { font-size: 32px; font-weight: 700; letter-spacing: -0.02em; line-height: 1.1; margin-top: 8px; }
        .stat-label { font-size: 11px; font-weight: 700; color: #6B7280; text-transform: uppercase; letter-spacing: 0.08em; }
        
        /* Alerts */
        .alert { border-radius: 8px; padding: 12px 16px; font-size: 13px; font-weight: 500; border: 1px solid; }
        .alert-success { background: #D1FAE5; border-color: #A7F3D0; color: #065F46; }
        .alert-error   { background: #FEE2E2; border-color: #FECACA; color: #991B1B; }
        .alert-info    { background: #DBEAFE; border-color: #BFDBFE; color: #1E40AF; }
        
        /* Utilities */
        .icon-box { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
        .divider { height: 1px; background: #E5E7EB; margin: 12px 0; }
        
        /* Typography */
        .text-display  { font-size: 28px; font-weight: 700; letter-spacing: -0.02em; color: #0D1117; }
        .text-title    { font-size: 20px; font-weight: 600; letter-spacing: -0.01em; color: #0D1117; }
        .text-headline { font-size: 16px; font-weight: 600; color: #0D1117; }
        .text-body     { font-size: 14px; font-weight: 400; line-height: 1.6; color: #0D1117; }
        .text-caption  { font-size: 12px; font-weight: 400; color: #6B7280; }
        .text-tiny     { font-size: 11px; font-weight: 500; color: #9CA3AF; text-transform: uppercase; letter-spacing: 0.08em; }
        
        /* Animations */
        @keyframes fadeIn { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }
        .fade-in { animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        
        /* Scrollbar */
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #D1D5DB; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #9CA3AF; }
    </style>
</head>
<body>
    @auth
    <div class="flex h-screen">
        <!-- Sidebar -->
        <div class="sidebar-employee w-64 fixed h-full overflow-y-auto flex flex-col">
            <div class="p-6 flex flex-col h-full">

                {{-- Brand --}}
                <div class="flex items-center gap-3 mb-8">
                    <div class="icon-box flex-shrink-0" style="background: linear-gradient(135deg, #10B981 0%, #059669 100%);">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <div class="brand-title">Shiftly</div>
                        <div class="brand-sub">Hospital Scheduler</div>
                    </div>
                </div>

                {{-- Navigation --}}
                <nav class="space-y-0.5 flex-1">
                    <a href="{{ route('employee.schedule') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('employee.schedule') ? 'active' : '' }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" /></svg>
                        <span>My Schedule</span>
                    </a>
                    <a href="{{ route('employee.profile') }}" class="nav-item flex items-center gap-3 px-3 py-2.5 rounded-lg {{ request()->routeIs('employee.profile') ? 'active' : '' }}">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>
                        <span>My Profile</span>
                    </a>
                </nav>

                {{-- User Info + Logout --}}
                <div class="mt-6 pt-6" style="border-top: 1px solid #F3F4F6;">
                    <div class="role-badge mb-3">
                        <div class="role-label">Employee</div>
                        <div class="role-name truncate">{{ auth()->user()->name }}</div>
                        <div class="role-email truncate">{{ auth()->user()->email }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="logout-btn">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <span>Logout</span>
                        </button>
                    </form>
                </div>

            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 ml-64 overflow-y-auto">
            <div class="p-8">
                @if(session('success'))
                    <div class="alert alert-success mb-6 flex items-center gap-3 fade-in">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-error mb-6 flex items-center gap-3 fade-in">
                        <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>{{ session('error') }}</span>
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-error mb-6 fade-in">
                        <div class="flex items-center gap-3 mb-2">
                            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                            </svg>
                            <span class="font-semibold">Please fix the following errors:</span>
                        </div>
                        <ul class="list-disc list-inside ml-6 space-y-1 text-caption">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                @yield('content')
            </div>
        </div>
    </div>
    @else
        @yield('content')
    @endauth
    
    <script>
        $(document).ready(function() {
            $('.fade-in').css('opacity', 0).animate({ opacity: 1 }, 300);
        });
    </script>
</body>
</html>
