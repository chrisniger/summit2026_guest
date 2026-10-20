@props(['title' => 'Overview'])
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title }} · SUMMIT 2026</title>
    <x-theme-init />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<div class="app-frame" x-data="{ menuOpen: false }">
    <aside class="sidebar" :class="{ 'is-open': menuOpen }" id="primary-navigation">
        <a class="brand" href="{{ route('dashboard') }}" aria-label="SUMMIT 2026 home">
            <img src="{{ asset('brand/summit-logo.png') }}" alt="Summit Bible Church" width="1920" height="610">
        </a>
        <div class="event-wordmark">SUMMIT <span>2026</span></div>
        <p class="nav-caption">CONFERENCE OPERATIONS</p>
        <nav aria-label="Main navigation">
            @can('platform.manage')
                <a href="{{ route('admin') }}" @class(['nav-link', 'active' => request()->routeIs('admin')])><x-icon name="grid" /> Overview</a>
            @endcan
            @can('info.access')
                <p class="nav-caption">INFO COM OPERATIONS</p>
                <a href="{{ route('info.check-in') }}" @class(['nav-link', 'active' => request()->routeIs('info.check-in')])><x-icon name="people" /> Attendance check-in</a>
                <a href="{{ route('info.attendees.create') }}" @class(['nav-link', 'active' => request()->routeIs('info.attendees.*')])><x-icon name="edit" /> Register attendee</a>
                <a href="{{ route('info.reports') }}" @class(['nav-link', 'active' => request()->routeIs('info.reports*')])><x-icon name="grid" /> Attendance reports</a>
            @endcan
            @can('info.import')
                <a href="{{ route('info.imports') }}" @class(['nav-link', 'active' => request()->routeIs('info.imports*')])><x-icon name="edit" /> Registration CSV imports</a>
            @endcan
            @can('workspace.follow_up_admin')
                <a href="{{ route('follow-up') }}" @class(['nav-link', 'active' => request()->routeIs('follow-up')])><x-icon name="heart" /> Guest Service</a>
            @endcan
            @can('workspace.data_entry')
                <a href="{{ route('guest-entry') }}" @class(['nav-link', 'active' => request()->routeIs('guest-entry')])><x-icon name="edit" /> Guest entry workspace</a>
            @endcan
            @can('workspace.officer')
                <a href="{{ route('my-guests') }}" @class(['nav-link', 'active' => request()->routeIs('my-guests')])><x-icon name="heart" /> My assigned guests</a>
            @endcan
            @can('guest.access')
                <p class="nav-caption">GUEST SERVICE OPERATIONS</p>
                <a href="{{ route('guests.index') }}" @class(['nav-link', 'active' => request()->routeIs('guests.index', 'guests.show')])><x-icon name="people" /> Guest records</a>
                @can('create', \App\Models\GuestRecord::class)
                    <a href="{{ route('guests.create') }}" @class(['nav-link', 'active' => request()->routeIs('guests.create', 'guests.edit')])><x-icon name="edit" /> Enter guest</a>
                @endcan
                @can('guest.import')
                    <a href="{{ route('guest-imports.index') }}" @class(['nav-link', 'active' => request()->routeIs('guest-imports.*')])><x-icon name="edit" /> Guest CSV / XLSX imports</a>
                @endcan
            @endcan
            @can('follow-up.reports')
                <a href="{{ route('follow-up.reports') }}" @class(['nav-link', 'active' => request()->routeIs('follow-up.reports*')])><x-icon name="grid" /> Follow-up reports</a>
            @endcan
            @can('platform.manage')
                <p class="nav-caption">ADMINISTRATION</p>
                <a href="{{ route('admin.sessions') }}" @class(['nav-link', 'active' => request()->routeIs('admin.sessions')])><x-icon name="calendar" /> Conference sessions</a>
                <a href="{{ route('admin.staff') }}" @class(['nav-link', 'active' => request()->routeIs('admin.staff*')])><x-icon name="people" /> Staff & roles</a>
                <a href="{{ route('admin.audit') }}" @class(['nav-link', 'active' => request()->routeIs('admin.audit')])><x-icon name="shield" /> Audit trail</a>
                <a href="{{ route('admin.operations') }}" @class(['nav-link', 'active' => request()->routeIs('admin.operations')])><x-icon name="shield" /> Local operations</a>
            @endcan
        </nav>
        <div class="sidebar-footer"><span class="dot"></span> 27 OCT — 01 NOV 2026<br><small>Africa/Lagos · WAT</small></div>
    </aside>
    <div class="main-frame">
        <header class="topbar">
            <div class="topbar-left">
                <button class="icon-button mobile-toggle" @click="menuOpen = !menuOpen" :aria-expanded="menuOpen" aria-controls="primary-navigation" aria-label="Toggle navigation"><x-icon name="menu" /></button>
                <span class="breadcrumb">SUMMIT 2026 <span>/</span> {{ $title }}</span>
            </div>
            <div class="account">
                <x-theme-switch />
                <span class="avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <span class="account-details"><strong>{{ auth()->user()->name }}</strong><small>{{ auth()->user()->roleLabel() }}</small></span>
                <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-button" aria-label="Sign out" title="Sign out"><x-icon name="logout" /></button></form>
            </div>
        </header>
        <main id="main" class="page-content" tabindex="-1">
            <div id="connection-status" class="notice error" role="alert" tabindex="-1" hidden>Connection lost. Your entered values are still here. Reconnect before saving.</div>
            @if(session('status'))<div class="notice success" role="status">{{ session('status') }}</div>@endif
            @if(isset($errors) && $errors->any())<div class="notice error" role="alert"><strong>Please review the following:</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
            {{ $slot }}
        </main>
        <footer class="page-footer">SUMMIT 2026 · Summit Bible Church <span>Conference operations platform</span></footer>
    </div>
</div>
@livewireScripts
</body>
</html>

