<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'E-5') · ProjectFlow</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <style>
        :root {
            --ink:#0f172a; --muted:#64748b; --line:#e2e8f0; --bg:#f1f5f9; --surface:#ffffff;
            --accent:#4f46e5; --accent-strong:#4338ca; --accent-soft:#eef2ff; --accent-ring:#c7d2fe;
            --success:#15803d; --success-soft:#dcfce7; --danger:#b91c1c; --danger-soft:#fee2e2;
            --warning:#b45309; --warning-soft:#fef3c7; --info:#1d4ed8; --info-soft:#dbeafe;
            --nav:#0f172a; --nav-2:#1e293b; --nav-ink:#cbd5e1;
            --radius:14px; --shadow:0 1px 2px rgba(15,23,42,.06), 0 1px 3px rgba(15,23,42,.04);
            --shadow-lg:0 12px 32px -12px rgba(15,23,42,.25);
        }
        * { box-sizing:border-box; }
        html, body { margin:0; }
        body { font:14px/1.55 Inter, system-ui, -apple-system, "Segoe UI", sans-serif; color:var(--ink); background:var(--bg); -webkit-font-smoothing:antialiased; }
        a { color:var(--accent); text-decoration:none; }
        a:hover { color:var(--accent-strong); }
        h1 { font-size:26px; line-height:1.2; letter-spacing:-.02em; margin:0; font-weight:700; }
        h2 { font-size:15px; margin:0; font-weight:600; letter-spacing:-.01em; }
        .icon { flex:none; vertical-align:middle; }
        .muted { color:var(--muted); }
        .mono { font-family:ui-monospace, SFMono-Regular, Menlo, monospace; font-size:12px; }

        /* ---------- App shell ---------- */
        .shell { display:grid; grid-template-columns:256px 1fr; min-height:100vh; }
        .sidebar { position:sticky; top:0; height:100vh; display:flex; flex-direction:column; gap:20px; padding:20px 14px; background:var(--nav); color:var(--nav-ink); overflow-y:auto; }
        .brand { display:flex; align-items:center; gap:10px; padding:0 8px; color:#fff; font-weight:700; font-size:16px; }
        .brand-mark { width:32px; height:32px; border-radius:9px; display:grid; place-items:center; background:linear-gradient(135deg,#6366f1,#a855f7); color:#fff; }
        .brand small { display:block; font-size:11px; font-weight:500; color:#94a3b8; }
        .nav-label { padding:0 10px; font-size:11px; font-weight:600; letter-spacing:.08em; text-transform:uppercase; color:#64748b; }
        .nav { display:flex; flex-direction:column; gap:2px; }
        .nav a, .nav span { display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:9px; color:var(--nav-ink); font-weight:500; }
        .nav a:hover { background:var(--nav-2); color:#fff; }
        .nav a.active { background:linear-gradient(90deg, rgba(99,102,241,.28), rgba(99,102,241,.08)); color:#fff; box-shadow:inset 3px 0 0 #818cf8; }
        .nav span.soon { color:#64748b; cursor:default; }
        .nav .chip { margin-left:auto; font-size:10px; padding:1px 7px; border-radius:999px; background:var(--nav-2); color:#94a3b8; }
        .project-pill { display:flex; align-items:center; gap:10px; padding:10px; border-radius:10px; background:var(--nav-2); color:#e2e8f0; }
        .project-pill .dot { width:8px; height:8px; border-radius:50%; background:#22c55e; box-shadow:0 0 0 3px rgba(34,197,94,.2); }
        .project-pill small { display:block; color:#94a3b8; font-size:11px; }
        .me { margin-top:auto; display:flex; align-items:center; gap:10px; padding:12px 10px; border-top:1px solid var(--nav-2); }
        .me div { min-width:0; flex:1; }
        .me strong { display:block; color:#fff; font-size:13px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
        .me small { color:#94a3b8; font-size:12px; }
        .me form { margin:0; }
        .me button { background:transparent; border:0; color:#94a3b8; padding:6px; }
        .me button:hover { color:#fff; background:var(--nav-2); }

        .topbar { position:sticky; top:0; z-index:5; display:flex; align-items:center; gap:16px; height:60px; padding:0 28px; background:rgba(255,255,255,.85); backdrop-filter:blur(8px); border-bottom:1px solid var(--line); }
        .crumbs { display:flex; align-items:center; gap:6px; color:var(--muted); font-weight:500; white-space:nowrap; overflow:hidden; }
        .crumbs a { color:var(--muted); } .crumbs a:hover { color:var(--ink); }
        .crumbs strong { color:var(--ink); font-weight:600; }
        .search { margin-left:auto; display:flex; align-items:center; gap:8px; width:min(340px, 40vw); padding:8px 12px; border:1px solid var(--line); border-radius:10px; background:var(--bg); color:var(--muted); }
        .search kbd { margin-left:auto; font:600 11px Inter, sans-serif; padding:1px 6px; border:1px solid var(--line); border-radius:6px; background:#fff; }
        .bell { position:relative; display:grid; place-items:center; width:36px; height:36px; border-radius:10px; border:1px solid var(--line); background:#fff; color:var(--muted); }
        .bell::after { content:""; position:absolute; top:7px; right:8px; width:7px; height:7px; border-radius:50%; background:#ef4444; border:2px solid #fff; }
        .content { padding:28px; max-width:1200px; width:100%; margin:0 auto; }
        #nav-toggle, .nav-open { display:none; }

        @media (max-width:900px) {
            .shell { grid-template-columns:1fr; }
            .sidebar { position:fixed; inset:0 auto 0 0; width:264px; z-index:20; transform:translateX(-100%); transition:transform .2s; }
            #nav-toggle:checked ~ .shell .sidebar { transform:none; box-shadow:var(--shadow-lg); }
            .nav-open { display:grid; place-items:center; width:36px; height:36px; border-radius:10px; border:1px solid var(--line); background:#fff; cursor:pointer; }
            .topbar { padding:0 16px; } .search { display:none; }
            .content { padding:20px 16px; }
        }

        /* ---------- Page building blocks ---------- */
        .back { display:inline-flex; align-items:center; gap:6px; margin-bottom:14px; color:var(--muted); font-weight:500; }
        .page-head { display:flex; justify-content:space-between; align-items:flex-end; gap:16px; flex-wrap:wrap; margin-bottom:22px; }
        .page-head p { margin:6px 0 0; }
        .card { background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow); padding:20px; }
        .card + .card, .stack > * + * { margin-top:16px; }
        .card-head { display:flex; justify-content:space-between; align-items:center; gap:12px; margin-bottom:16px; }
        .card-title { display:flex; align-items:center; gap:10px; }
        .card-foot { display:flex; justify-content:flex-end; gap:10px; margin:20px -20px -20px; padding:14px 20px; background:#f8fafc; border-top:1px solid var(--line); border-radius:0 0 var(--radius) var(--radius); }
        .step { display:inline-grid; place-items:center; min-width:28px; height:24px; border-radius:7px; background:var(--accent-soft); color:var(--accent); font-size:12px; font-weight:700; }
        .count { display:inline-grid; place-items:center; min-width:22px; height:20px; padding:0 6px; border-radius:999px; background:var(--bg); color:var(--muted); font-size:11px; font-weight:700; }
        .grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(300px, 1fr)); gap:16px; }
        .cols { display:grid; grid-template-columns:1fr 1fr; gap:0 18px; }
        .split { display:grid; grid-template-columns:minmax(0, 2fr) minmax(280px, 1fr); gap:16px; align-items:start; }
        @media (max-width:760px) { .cols, .split { grid-template-columns:1fr; } }

        /* Gradient hero for the current project */
        .hero { position:relative; overflow:hidden; display:flex; justify-content:space-between; align-items:center; gap:20px; flex-wrap:wrap; padding:22px 24px; margin-bottom:20px; border-radius:18px; color:#fff;
                background:radial-gradient(circle at 85% -20%, rgba(255,255,255,.25), transparent 45%), linear-gradient(120deg, #4338ca, #6d28d9 55%, #9333ea); box-shadow:var(--shadow-lg); }
        .hero h1 { color:#fff; }
        .hero p { margin:6px 0 0; color:#e0e7ff; }
        .hero .tag { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px; background:rgba(255,255,255,.16); font-size:12px; font-weight:600; }
        .hero .tag i { width:7px; height:7px; border-radius:50%; background:#4ade80; }
        .hero-side { display:flex; align-items:center; gap:14px; }
        .hero button.primary { background:#fff; color:var(--accent-strong); border-color:#fff; }
        .hero button.primary:hover { background:#eef2ff; }

        /* KPI tiles */
        .kpis { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:14px; margin-bottom:20px; }
        .kpi { display:flex; align-items:center; gap:14px; padding:16px; background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow); }
        .kpi-icon { display:grid; place-items:center; width:42px; height:42px; border-radius:12px; }
        .kpi strong { display:block; font-size:22px; line-height:1.1; letter-spacing:-.02em; }
        .kpi span { color:var(--muted); font-size:12px; font-weight:500; }
        .tone-accent { background:var(--accent-soft); color:var(--accent); }
        .tone-info { background:var(--info-soft); color:var(--info); }
        .tone-success { background:var(--success-soft); color:var(--success); }
        .tone-danger { background:var(--danger-soft); color:var(--danger); }
        .tone-warning { background:var(--warning-soft); color:var(--warning); }

        /* Tabs */
        .tabs { display:flex; gap:4px; padding:4px; border-radius:11px; background:#e2e8f0; width:max-content; max-width:100%; overflow-x:auto; }
        .tabs a { display:flex; align-items:center; gap:6px; padding:6px 12px; border-radius:8px; color:var(--muted); font-weight:600; white-space:nowrap; }
        .tabs a.active { background:#fff; color:var(--ink); box-shadow:var(--shadow); }
        .tabs a .count { background:transparent; }
        .tabs a.active .count { background:var(--accent-soft); color:var(--accent); }

        /* Tables */
        .table-card { background:var(--surface); border:1px solid var(--line); border-radius:var(--radius); box-shadow:var(--shadow); overflow:auto; }
        table { width:100%; border-collapse:collapse; }
        th, td { text-align:left; padding:14px 16px; border-bottom:1px solid var(--line); vertical-align:middle; }
        tr:last-child td { border-bottom:0; }
        tbody tr:hover td { background:#f8fafc; }
        th { font-size:11px; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); font-weight:600; background:#f8fafc; white-space:nowrap; }
        .date-chip { display:flex; align-items:center; gap:12px; }
        .date-chip b { display:grid; place-items:center; width:46px; height:46px; border-radius:11px; background:var(--accent-soft); color:var(--accent); font-size:16px; line-height:1; text-align:center; }
        .date-chip b small { display:block; font-size:10px; font-weight:600; text-transform:uppercase; }
        .sub { display:flex; align-items:center; gap:5px; margin-top:2px; color:var(--muted); font-size:12px; }
        .stack-avatars { display:flex; }
        .stack-avatars .avatar { margin-left:-8px; border:2px solid #fff; }
        .stack-avatars .avatar:first-child { margin-left:0; }
        .stack-avatars .more { display:grid; place-items:center; width:28px; height:28px; margin-left:-8px; border-radius:50%; border:2px solid #fff; background:var(--bg); color:var(--muted); font-size:11px; font-weight:700; }

        /* Avatars and badges */
        .avatar { --size:32px; display:inline-grid; place-items:center; width:var(--size); height:var(--size); border-radius:50%; color:#fff; font-size:calc(var(--size) * .38); font-weight:700; flex:none; }
        .badge { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:999px; font-size:11px; font-weight:700; letter-spacing:.02em; background:#f1f5f9; color:#475569; white-space:nowrap; }
        .badge i { width:6px; height:6px; border-radius:50%; background:currentColor; }
        .badge.info { background:var(--info-soft); color:var(--info); }
        .badge.success { background:var(--success-soft); color:var(--success); }
        .badge.danger { background:var(--danger-soft); color:var(--danger); }
        .badge.warning { background:var(--warning-soft); color:var(--warning); }
        .role { display:inline-block; padding:1px 7px; border-radius:6px; background:var(--bg); color:var(--muted); font-size:11px; font-weight:700; }

        /* Key-value lists */
        dl.kv { margin:0; display:grid; gap:14px; }
        dl.kv dt { font-size:11px; font-weight:600; letter-spacing:.06em; text-transform:uppercase; color:var(--muted); }
        dl.kv dd { margin:3px 0 0; font-weight:500; }

        /* People list */
        .people { list-style:none; margin:0; padding:0; }
        .people li { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--line); }
        .people li:last-child { border-bottom:0; }
        .people .who { flex:1; min-width:0; }
        .people .who strong { display:block; }

        /* Timeline */
        .timeline { list-style:none; margin:0; padding:0; position:relative; }
        .timeline li { position:relative; padding:0 0 18px 26px; }
        .timeline li::before { content:""; position:absolute; left:6px; top:18px; bottom:0; width:2px; background:var(--line); }
        .timeline li:last-child::before { display:none; }
        .timeline li::after { content:""; position:absolute; left:0; top:4px; width:14px; height:14px; border-radius:50%; background:var(--accent-soft); border:3px solid var(--accent); }
        .timeline small { display:block; color:var(--muted); }

        /* Stepper (MoM flow) */
        .stepper { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
        .stepper span { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:9px; background:var(--bg); color:var(--muted); font-size:12px; font-weight:700; }
        .stepper span.on { background:var(--accent-soft); color:var(--accent); box-shadow:inset 0 0 0 1px var(--accent-ring); }
        .stepper .icon { color:#94a3b8; }

        /* Alerts and toast */
        .alert { display:flex; gap:10px; align-items:flex-start; padding:12px 14px; border-radius:11px; margin-bottom:16px; font-size:13px; font-weight:500; }
        .alert ul { margin:4px 0 0; padding-left:18px; font-weight:400; }
        .alert.success { background:var(--success-soft); color:var(--success); }
        .alert.danger { background:var(--danger-soft); color:var(--danger); }
        .alert.info { background:var(--accent-soft); color:var(--accent-strong); }
        .toast { position:fixed; right:24px; bottom:24px; z-index:30; display:flex; align-items:center; gap:10px; padding:12px 16px; border-radius:12px; background:var(--ink); color:#fff; font-weight:500; box-shadow:var(--shadow-lg); animation:toast 5s forwards; }
        .toast .icon { color:#4ade80; }
        @keyframes toast { 0% { opacity:0; transform:translateY(8px); } 6%, 85% { opacity:1; transform:none; } 100% { opacity:0; visibility:hidden; } }

        /* Empty state */
        .empty { display:grid; justify-items:center; gap:8px; padding:48px 20px; text-align:center; }
        .empty .ring { display:grid; place-items:center; width:56px; height:56px; border-radius:50%; background:var(--accent-soft); color:var(--accent); }
        .empty p { margin:0; }

        /* Forms */
        .field { margin-bottom:18px; }
        .field label, .field legend { display:block; font-size:13px; font-weight:600; margin-bottom:7px; padding:0; }
        .req { color:var(--danger); }
        .hint { margin-top:6px; color:var(--muted); font-size:12px; }
        .input { position:relative; }
        .input .icon { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; pointer-events:none; }
        .input input { padding-left:38px; }
        input[type=text], input[type=date], input[type=time], select, textarea {
            width:100%; font:inherit; padding:10px 12px; border:1px solid var(--line); border-radius:10px; background:#fff; color:var(--ink); transition:border-color .15s, box-shadow .15s;
        }
        input:focus, select:focus, textarea:focus { outline:0; border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-ring); }
        input[readonly] { background:#f8fafc; color:var(--muted); }
        .invalid input, .invalid .choices { border-color:#f87171; box-shadow:0 0 0 3px var(--danger-soft); }
        .field-error { display:flex; align-items:center; gap:5px; margin-top:6px; color:var(--danger); font-size:12px; font-weight:500; }
        fieldset { border:0; margin:0; padding:0; min-width:0; }
        .choices { display:grid; grid-template-columns:repeat(auto-fill, minmax(210px, 1fr)); gap:10px; border-radius:12px; }
        .choice { position:relative; display:flex; align-items:center; gap:10px; padding:12px; border:1px solid var(--line); border-radius:12px; background:#fff; font-weight:500; cursor:pointer; transition:border-color .15s, box-shadow .15s; }
        .choice:hover { border-color:var(--accent-ring); }
        .choice input { position:absolute; opacity:0; }
        .choice .tick { margin-left:auto; display:grid; place-items:center; width:20px; height:20px; border-radius:6px; border:1.5px solid #cbd5e1; color:transparent; }
        .choice:has(input:checked) { border-color:var(--accent); box-shadow:0 0 0 3px var(--accent-soft); }
        .choice:has(input:checked) .tick { background:var(--accent); border-color:var(--accent); color:#fff; }
        .choice:has(input:focus-visible) { box-shadow:0 0 0 3px var(--accent-ring); }

        button { display:inline-flex; align-items:center; gap:7px; font:inherit; font-weight:600; padding:9px 16px; border:1px solid var(--line); border-radius:10px; background:#fff; color:var(--ink); cursor:pointer; transition:background .15s, border-color .15s; }
        button:hover { background:#f8fafc; }
        button.primary { background:var(--accent); border-color:var(--accent); color:#fff; box-shadow:0 1px 2px rgba(79,70,229,.35); }
        button.primary:hover { background:var(--accent-strong); }
        button.danger { color:var(--danger); border-color:#fecaca; }
        button.danger:hover { background:var(--danger-soft); }
        .actions { display:flex; gap:10px; flex-wrap:wrap; }
        .actions form { margin:0; }

        /* Guest pages (dev login) */
        .guest { min-height:100vh; display:grid; place-items:center; padding:24px 16px;
                 background:radial-gradient(circle at 15% 10%, #6366f1 0, transparent 35%), radial-gradient(circle at 90% 90%, #a855f7 0, transparent 35%), var(--nav); }
        .guest-card { width:min(640px, 100%); }
    </style>
</head>
<body>
@auth
    @php
        $me = auth()->user();
        $roleNames = ['PM' => 'Project Manager', 'TL' => 'Technical Lead', 'TW' => 'Technical Writer'];
        $current = isset($project) ? $project : null;
    @endphp
    <input type="checkbox" id="nav-toggle" hidden>
    <div class="shell">
        <aside class="sidebar">
            <div class="brand">
                <span class="brand-mark"><x-icon name="layers" size="18" /></span>
                <div>ProjectFlow<small>E-5 Pengawasan</small></div>
            </div>

            @if ($current)
                <a class="project-pill" href="/projects">
                    <span class="dot"></span>
                    <div>{{ $current->name }}<small class="mono">{{ $current->projectId }}</small></div>
                </a>
            @endif

            <div>
                <div class="nav-label">Menu</div>
                <nav class="nav">
                    <a href="/projects" @class(['active' => request()->is('projects')])><x-icon name="folder" /> Proyek</a>
                    @if ($current)
                        <a href="/projects/{{ $current->projectId }}/meetings" @class(['active' => ! request()->is('projects', 'projects/*/action-items')])><x-icon name="calendar" /> Rapat</a>
                    @else
                        <span class="soon"><x-icon name="calendar" /> Rapat <em class="chip">Pilih proyek</em></span>
                    @endif
                    <span class="soon"><x-icon name="file" /> MoM <em class="chip">Dari rapat</em></span>
                    @if ($current)
                        <a href="/projects/{{ $current->projectId }}/action-items" @class(['active' => request()->is('projects/*/action-items')])><x-icon name="check-square" /> Action Items</a>
                    @else
                        <span class="soon"><x-icon name="check-square" /> Action Items <em class="chip">Pilih proyek</em></span>
                    @endif
                    <span class="soon"><x-icon name="change" /> Change Request <em class="chip">Slice 2</em></span>
                    <span class="soon"><x-icon name="alert" /> Risiko &amp; Isu <em class="chip">Slice 3</em></span>
                </nav>
            </div>

            <div class="me">
                <x-avatar :name="$me->displayName" size="34" />
                <div>
                    <strong>{{ $me->displayName }}</strong>
                    <small>{{ $roleNames[$me->role->value] }}</small>
                </div>
                <form method="POST" action="/logout">
                    @csrf
                    <button type="submit" title="Keluar" aria-label="Keluar"><x-icon name="logout" /></button>
                </form>
            </div>
        </aside>

        <div>
            <header class="topbar">
                <label for="nav-toggle" class="nav-open" aria-label="Buka menu"><x-icon name="menu" /></label>
                <nav class="crumbs">
                    <a href="/projects">Proyek</a>
                    @if ($current)
                        <x-icon name="chevron-right" size="14" /><span>{{ $current->name }}</span>
                    @endif
                    <x-icon name="chevron-right" size="14" /><strong>@yield('title', 'E-5')</strong>
                </nav>
                <div class="search"><x-icon name="search" size="16" /> Cari rapat, MoM, action item… <kbd>Ctrl K</kbd></div>
                <span class="bell" title="Notifikasi"><x-icon name="bell" size="17" /></span>
            </header>

            <main class="content">
                @yield('content')
            </main>
        </div>
    </div>

    @if (session('status'))
        <div class="toast" role="status"><x-icon name="check" /> {{ session('status') }}</div>
    @endif
@else
    <main class="guest">
        @yield('content')
    </main>
@endauth
</body>
</html>
