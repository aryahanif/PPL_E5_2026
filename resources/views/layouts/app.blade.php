<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'E-5') · Pengawasan Proyek</title>
    <style>
        :root { --ink:#1f2933; --muted:#616e7c; --line:#d9dee3; --accent:#1d4ed8; --bg:#f7f8fa; }
        * { box-sizing: border-box; }
        body { margin:0; font: 15px/1.5 system-ui, -apple-system, "Segoe UI", sans-serif; color:var(--ink); background:var(--bg); }
        header { display:flex; justify-content:space-between; align-items:center; padding:12px 24px; background:#fff; border-bottom:1px solid var(--line); }
        header a { color:var(--ink); text-decoration:none; font-weight:600; }
        main { max-width:960px; margin:24px auto; padding:0 16px; }
        table { width:100%; border-collapse:collapse; background:#fff; border:1px solid var(--line); }
        th, td { text-align:left; padding:10px 12px; border-bottom:1px solid var(--line); }
        th { font-size:13px; color:var(--muted); font-weight:600; }
        a { color:var(--accent); }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:600; background:#e5e7eb; }
        .muted { color:var(--muted); }
        button { font:inherit; padding:6px 14px; border:1px solid var(--line); border-radius:6px; background:#fff; cursor:pointer; }
        button.primary { background:var(--accent); border-color:var(--accent); color:#fff; }
    </style>
</head>
<body>
<header>
    <a href="/projects">E-5 · Pengawasan, Rapat, Risiko &amp; Perubahan</a>
    @auth
        <form method="POST" action="/logout">
            @csrf
            <span class="muted">{{ auth()->user()->displayName }} ({{ auth()->user()->role->value }})</span>
            <button type="submit">Keluar</button>
        </form>
    @endauth
</header>
<main>
    @yield('content')
</main>
</body>
</html>
