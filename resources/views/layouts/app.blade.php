<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>POSO Portal — @yield('title', 'Dashboard')</title>
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap/bootstrap.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/bootstrap-icons/bootstrap-icons.css') }}">
    <link href="{{ asset('vendor/fonts/fonts.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/portal-base.css') }}?v={{ filemtime(public_path('css/portal-base.css')) }}">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v={{ filemtime(public_path('css/portal.css')) }}">
    @stack('styles')
</head>
<body>
<script>
// Runs before paint: a collapsed sidebar must not flash open on every load.
try { if (localStorage.getItem('poso.sidebar') === 'collapsed') document.body.classList.add('sb-collapsed'); } catch (e) {}
</script>

<a class="skip-link" href="#workspace-content">Skip to content</a>

<div id="sidebar">
    <div class="sb-top">
        <div class="sb-brand">
            <div class="sb-brand-seal"><img src="{{ $__posoSeal }}" alt="Municipal Seal"></div>
            <div class="sb-brand-text">
                <div class="sb-brand-name">POSO</div>
                <div class="sb-brand-sub">Management Portal</div>
            </div>
            <button type="button" class="sb-collapse-btn" id="sidebarCollapseBtn"
                    aria-controls="sidebar" aria-expanded="true"
                    title="Collapse sidebar" aria-label="Collapse sidebar">
                <i class="bi bi-chevron-double-left" aria-hidden="true"></i>
            </button>
        </div>
        <p class="sb-location">Municipality of Luna, Apayao</p>
        @php
            $parts    = explode(' ', auth()->user()->name);
            $initials = strtoupper(substr($parts[0],0,1)) . (isset($parts[1]) ? strtoupper(substr($parts[1],0,1)) : '');
            $avColors = ['admin'=>['#E9F0F9','#0F3D73'],'enforcer'=>['#FBF3E1','#B8862E']];
            $av = $avColors[auth()->user()->role] ?? ['#E9F0F9','#0F3D73'];
        @endphp
        <a href="{{ route('profile.edit') }}" class="sb-user {{ request()->routeIs('profile.*') ? 'active' : '' }}"
           title="{{ auth()->user()->name }} — my profile">
            <div class="sb-av" style="background:{{ $av[0] }};color:{{ $av[1] }}">{{ $initials }}</div>
            <div class="sb-user-text">
                <div class="sb-uname">{{ auth()->user()->name }}</div>
                <div class="sb-urole">{{ ucfirst(auth()->user()->role) }}</div>
            </div>
        </a>
    </div>

    <div class="sb-nav">
        @if(auth()->user()->isEnforcer())
            <div class="sb-sec">Main Menu</div>
            <a href="{{ route('enforcer.create') }}" class="sb-link {{ request()->routeIs('violations.*', 'enforcer.*') ? 'active' : '' }}" title="Record Violator">
                <i class="bi bi-ticket-perforated"></i> Record Violator
            </a>
        @else
            <div class="sb-sec">Main Menu</div>
            <a href="{{ route('dashboard') }}" class="sb-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard">
                <i class="bi bi-grid-1x2"></i> Dashboard
            </a>
            <a href="{{ route('violators.index') }}" class="sb-link {{ request()->routeIs('violators.*') ? 'active' : '' }}" title="Violators">
                <i class="bi bi-people"></i> Violators
            </a>
            <a href="{{ route('violations.index') }}" class="sb-link {{ request()->routeIs('violations.*') ? 'active' : '' }}" title="Violations">
                <i class="bi bi-exclamation-octagon"></i> Violations
            </a>
            
            @if(auth()->user()->isAdmin())
            <a href="{{ route('reports.index') }}" class="sb-link {{ request()->routeIs('reports.*') ? 'active' : '' }}" title="Reports">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>
            @endif
        @endif
        @if(auth()->user()->isAdmin())
        <div class="sb-sec">Administration</div>
        <a href="{{ route('admin.users') }}" class="sb-link {{ request()->routeIs('admin.users*') ? 'active' : '' }}" title="User Management">
            <i class="bi bi-person-badge"></i> User Management
        </a>
        <a href="{{ route('admin.violation-types') }}" class="sb-link {{ request()->routeIs('admin.violation-types*') ? 'active' : '' }}" title="Offense Types">
            <i class="bi bi-list-check"></i> Offense Types
        </a>
        <a href="{{ route('admin.audit-logs') }}" class="sb-link {{ request()->routeIs('admin.audit-logs*') ? 'active' : '' }}" title="Audit Logs">
            <i class="bi bi-clock-history"></i> Audit Logs
        </a>
        @endif
    </div>

    <div class="sb-footer">
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="sb-signout" title="Sign out">
                <i class="bi bi-box-arrow-right"></i> <span class="sb-signout-label">Sign out</span>
            </button>
        </form>
    </div>
</div>

<div id="sidebarBackdrop"></div>

<div id="main">
    <div class="topbar">
        <div style="display:flex;align-items:center;min-width:0">
            <button type="button" class="hamburger-btn" id="hamburgerBtn" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><i class="bi bi-list"></i></button>
            <span class="workspace-label me-3">Workspace &nbsp; /</span>
            <span class="topbar-title text-truncate">@yield('title', 'Dashboard')</span>
        </div>
        <div class="topbar-right">
            <div class="topbar-date"><i class="bi bi-calendar3 me-1"></i>{{ now()->format('M d, Y') }}</div>
            {{-- Bell: opens the settlement work queue. Notification only — it never navigates by itself. --}}
            @if(auth()->user()->isAdmin())
            @php
                $alertCount = $__posoAlerts['count'];
                // Noun and verb both have to agree, so build the phrase once.
                $alertPhrase = $alertCount === 1
                    ? '1 settlement needs your attention'
                    : $alertCount.' settlements need your attention';
            @endphp
            <div class="position-relative" id="notifWrap">
                <button type="button" class="topbar-btn topbar-bell" id="notifBtn"
                        aria-controls="notifPanel" aria-expanded="false" aria-haspopup="true"
                        title="{{ $alertCount > 0 ? $alertPhrase : 'Nothing needs your attention' }}"
                        aria-label="Notifications: {{ $alertCount > 0 ? $alertPhrase : 'none' }}">
                    <i class="bi bi-bell" aria-hidden="true"></i>
                    @if($alertCount > 0)
                        <span class="topbar-bell-count" aria-hidden="true">{{ $alertCount > 99 ? '99+' : $alertCount }}</span>
                    @endif
                </button>
                @include('partials.notifications')
            </div>
            @endif

            {{-- Gear: settings dropdown --}}
            <div class="position-relative" id="gearWrap">
                <button type="button" class="topbar-btn" id="gearBtn" title="Settings" aria-label="Account settings" aria-controls="gearMenu" aria-expanded="false">
                    <i class="bi bi-gear"></i>
                </button>
                <div id="gearMenu" style="display:none;position:absolute;top:calc(100% + 8px);right:0;width:220px;background:#fff;border:1px solid #DFE4EA;border-radius:12px;box-shadow:0 8px 32px rgba(17,37,63,.16);z-index:999;overflow:hidden;">
                    <div style="padding:12px 14px;border-bottom:1px solid #EAEEF2">
                        <div style="font-size:12px;font-weight:700;color:#11253F">{{ auth()->user()->name }}</div>
                        <div style="font-size:11px;color:#93A0B3;margin-top:1px">{{ ucfirst(auth()->user()->role) }} &nbsp;·&nbsp; POSO Luna</div>
                    </div>
                    <div style="padding:6px">
                        @if(auth()->user()->isAdmin())
                        <a href="{{ route('admin.users') }}" class="gear-item"><i class="bi bi-person-gear"></i> Manage Users</a>
                        <a href="{{ route('admin.violation-types') }}" class="gear-item"><i class="bi bi-list-check"></i> Offense Types</a>
                        <a href="{{ route('admin.audit-logs') }}" class="gear-item"><i class="bi bi-clock-history"></i> Audit Logs</a>
                        <div style="border-top:1px solid #EAEEF2;margin:4px 0"></div>
                        @endif
                        @if(auth()->user()->isAdmin())
                        <a href="{{ route('reports.index') }}" class="gear-item"><i class="bi bi-bar-chart-line"></i> Reports</a>
                        <div style="border-top:1px solid #EAEEF2;margin:4px 0"></div>
                        @endif
                        <a href="{{ route('profile.edit') }}" class="gear-item"><i class="bi bi-person-circle"></i> My Profile</a>
                        <div style="border-top:1px solid #EAEEF2;margin:4px 0"></div>
                        <div class="gear-item" style="cursor:default;pointer-events:none;opacity:.5"><i class="bi bi-info-circle"></i> POSO Portal v1.0</div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="gear-item" style="width:100%;text-align:left;background:none;border:none;cursor:pointer;color:#9B1C1C">
                                <i class="bi bi-box-arrow-right" style="color:#9B1C1C"></i> Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <main class="content-area" id="workspace-content" tabindex="-1">
        @unless(request()->routeIs('dashboard'))
        <div class="page-heading"><div><h1>@yield('title')</h1><p>{{ match (true) {
            request()->routeIs('violators.*') => 'Manage profiles and retained violation history.',
            request()->routeIs('violations.*') => 'Review incident details and verify Treasury settlements.',
            request()->routeIs('reports.*') => 'Review, filter and print your records.',
            request()->routeIs('admin.users*') => 'Manage staff accounts, roles and access.',
            request()->routeIs('admin.violation-types*') => 'Maintain offense descriptions and fine amounts.',
            request()->routeIs('admin.audit-logs*') => 'Review recorded changes and staff activity.',
            default => 'Public Order & Safety Office, Luna, Apayao',
        } }}</p></div></div>
        @endunless
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show mb-3">
                <i class="bi bi-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
            </div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show mb-3">
                <i class="bi bi-x-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
            </div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show mb-3">
                <i class="bi bi-x-circle me-2"></i>
                <ul class="mb-0 ps-3 mt-1">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Dismiss message"></button>
            </div>
        @endif
        @yield('content')
    </main>
    <footer class="workspace-footer">POSO &middot; Public Order &amp; Safety Office &middot; Municipality of Luna</footer>
</div>

<script src="{{ asset('vendor/bootstrap/bootstrap.bundle.min.js') }}"></script>
@include('partials.payment_dialog')
@stack('scripts')

<script>
// Desktop sidebar collapse. Remembered per browser; the pre-paint script in
// <body> applies the stored choice before anything renders.
const collapseBtn = document.getElementById('sidebarCollapseBtn');
if (collapseBtn) {
    const sync = () => {
        const collapsed = document.body.classList.contains('sb-collapsed');
        collapseBtn.setAttribute('aria-expanded', String(!collapsed));
        collapseBtn.setAttribute('title', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
        collapseBtn.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
    };
    sync();
    collapseBtn.addEventListener('click', function () {
        const collapsed = document.body.classList.toggle('sb-collapsed');
        try { localStorage.setItem('poso.sidebar', collapsed ? 'collapsed' : 'expanded'); } catch (e) {}
        sync();
    });
}

// Notification panel toggle. The bell never navigates on its own — it opens
// this panel, and the rows inside it are the links to individual records.
const notifBtn   = document.getElementById('notifBtn');
const notifPanel = document.getElementById('notifPanel');
function closeNotif() {
    if (!notifPanel) return;
    notifPanel.hidden = true;
    notifBtn.setAttribute('aria-expanded', 'false');
}
if (notifBtn && notifPanel) {
    notifBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = !notifPanel.hidden;
        if (!isOpen && typeof closeGear === 'function') closeGear();
        notifPanel.hidden = isOpen;
        notifBtn.setAttribute('aria-expanded', String(!isOpen));
        if (!isOpen) notifPanel.querySelector('a')?.focus();
    });
    document.addEventListener('click', function(e) {
        if (!document.getElementById('notifWrap').contains(e.target)) closeNotif();
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && !notifPanel.hidden) { closeNotif(); notifBtn.focus(); }
    });
}

// Gear dropdown toggle
const gearBtn  = document.getElementById('gearBtn');
const gearMenu = document.getElementById('gearMenu');
function closeGear() {
    if (!gearMenu) return;
    gearMenu.style.display = 'none';
    gearBtn.setAttribute('aria-expanded', 'false');
}
if (gearBtn && gearMenu) {
    gearBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        const isOpen = gearMenu.style.display === 'block';
        if (!isOpen) closeNotif();
        gearMenu.style.display = isOpen ? 'none' : 'block';
        gearBtn.setAttribute('aria-expanded', String(!isOpen));
    });
    document.addEventListener('click', function(e) {
        if (!document.getElementById('gearWrap').contains(e.target)) {
            gearMenu.style.display = 'none';
            gearBtn.setAttribute('aria-expanded', 'false');
        }
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') { gearMenu.style.display = 'none'; gearBtn.setAttribute('aria-expanded', 'false'); }
    });
}

// Mobile/tablet sidebar drawer
const hamburgerBtn    = document.getElementById('hamburgerBtn');
const sidebarEl       = document.getElementById('sidebar');
const sidebarBackdrop = document.getElementById('sidebarBackdrop');

function openSidebar() {
    sidebarEl.classList.add('open'); sidebarBackdrop.classList.add('show');
    document.body.classList.add('sidebar-open'); hamburgerBtn.setAttribute('aria-expanded', 'true');
    sidebarEl.querySelector('a, button')?.focus();
}
function closeSidebar() {
    const wasOpen = sidebarEl.classList.contains('open');
    sidebarEl.classList.remove('open'); sidebarBackdrop.classList.remove('show');
    document.body.classList.remove('sidebar-open'); hamburgerBtn.setAttribute('aria-expanded', 'false');
    if (wasOpen) hamburgerBtn.focus();
}

if (hamburgerBtn && sidebarEl && sidebarBackdrop) {
    hamburgerBtn.addEventListener('click', function(e) {
        e.stopPropagation();
        sidebarEl.classList.contains('open') ? closeSidebar() : openSidebar();
    });
    sidebarBackdrop.addEventListener('click', closeSidebar);
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });
    window.addEventListener('resize', function() { if (window.innerWidth > 900) closeSidebar(); });
    sidebarEl.addEventListener('keydown', function(e) {
        if (e.key !== 'Tab' || !sidebarEl.classList.contains('open')) return;
        const items = sidebarEl.querySelectorAll('a[href], button');
        const first = items[0], last = items[items.length - 1];
        if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
        else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
    });
    // Close the drawer after tapping a nav link (mobile UX)
    sidebarEl.querySelectorAll('.sb-link, .sb-dept-item').forEach(function(link) {
        link.addEventListener('click', closeSidebar);
    });
}
</script>
</body>
</html>
