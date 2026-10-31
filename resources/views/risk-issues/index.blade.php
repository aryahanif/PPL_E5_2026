@extends('layouts.app')

@section('title', 'Risiko & Isu')

@section('content')
    @php
        $tz = config('e5.display_timezone');
        $name = fn ($accountId) => $people[$accountId]->displayName ?? $accountId;
        $counts = $all->countBy(fn ($item) => $item->status->value);
        $types = $all->countBy(fn ($item) => $item->type->value);
        $highOpen = $all->filter(fn ($item) => $item->impact_level?->value === 'HIGH' && $item->status->value !== 'CLOSED')->count();
        $url = fn (?string $s, ?string $t) => '/projects/'.$project->projectId.'/risk-issues'
            .(($query = http_build_query(array_filter(['status' => $s, 'type' => $t]))) ? '?'.$query : '');
        $statusTabs = [
            '' => ['Semua', $all->count()],
            'OPEN' => ['Terbuka', $counts['OPEN'] ?? 0],
            'IN_PROGRESS' => ['Ditangani', $counts['IN_PROGRESS'] ?? 0],
            'CLOSED' => ['Ditutup', $counts['CLOSED'] ?? 0],
        ];
        $typeTabs = ['' => 'Semua jenis', 'RISK' => 'Risiko', 'ISSUE' => 'Isu'];
        $impactTone = ['HIGH' => 'danger', 'MEDIUM' => 'warning', 'LOW' => 'info'];
    @endphp

    <section class="hero">
        <div>
            <span class="tag"><i></i> {{ $project->active ? 'ACTIVE' : 'INACTIVE' }} · {{ $project->projectId }}</span>
            <h1 style="margin-top:10px">Risiko &amp; Isu · {{ $project->name }}</h1>
            <p>Risiko yang mungkin terjadi dan isu yang sedang dihadapi proyek, beserta mitigasinya.</p>
        </div>
        <div class="hero-side">
            @can('create', App\Models\RiskIssue::class)
                <form method="GET" action="/projects/{{ $project->projectId }}/risk-issues/create">
                    <button type="submit" class="primary"><x-icon name="plus" size="16" /> Catat Risiko/Isu</button>
                </form>
            @endcan
        </div>
    </section>

    <div class="kpis">
        <div class="kpi"><span class="kpi-icon tone-accent"><x-icon name="layers" size="20" /></span><div><strong>{{ $all->count() }}</strong><span>Total ({{ $types['RISK'] ?? 0 }} risiko, {{ $types['ISSUE'] ?? 0 }} isu)</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-warning"><x-icon name="alert" size="20" /></span><div><strong>{{ $counts['OPEN'] ?? 0 }}</strong><span>Terbuka</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-info"><x-icon name="clock" size="20" /></span><div><strong>{{ $counts['IN_PROGRESS'] ?? 0 }}</strong><span>Ditangani</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-success"><x-icon name="check" size="20" /></span><div><strong>{{ $counts['CLOSED'] ?? 0 }}</strong><span>Ditutup</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-danger"><x-icon name="alert" size="20" /></span><div><strong>{{ $highOpen }}</strong><span>Dampak HIGH belum ditutup</span></div></div>
    </div>

    <div class="card-head" style="flex-wrap:wrap">
        <nav class="tabs" aria-label="Filter status">
            @foreach ($statusTabs as $value => [$label, $total])
                <a href="{{ $url($value, $type?->value) }}" @class(['active' => ($status?->value ?? '') === $value])>{{ $label }} <span class="count">{{ $total }}</span></a>
            @endforeach
        </nav>
        <nav class="tabs" aria-label="Filter jenis">
            @foreach ($typeTabs as $value => $label)
                <a href="{{ $url($status?->value, $value) }}" @class(['active' => ($type?->value ?? '') === $value])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    @if ($items->isEmpty())
        <div class="card empty">
            <span class="ring"><x-icon name="alert" size="24" /></span>
            <h2>Belum ada risiko atau isu</h2>
            <p class="muted">
                @if ($all->isEmpty())
                    Risiko dan isu yang dicatat PM akan muncul di sini.
                @else
                    Tidak ada risiko atau isu yang cocok dengan filter ini.
                @endif
            </p>
        </div>
    @else
        <div class="table-card">
            <table>
                <thead>
                    <tr><th>Risiko / isu</th><th>Jenis</th><th>Dampak</th><th>PIC</th><th>Status</th><th>Dicatat (WIB)</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td style="max-width:360px">
                                <a href="/risk-issues/{{ $item->id }}" style="color:var(--ink)"><strong>{{ $item->title }}</strong></a>
                                <div class="sub">{{ \Illuminate\Support\Str::limit($item->description, 80) }}</div>
                            </td>
                            <td><span class="badge">{{ $item->type->value === 'RISK' ? 'Risiko' : 'Isu' }}</span></td>
                            <td>
                                @if ($item->impact_level)
                                    <span class="badge {{ $impactTone[$item->impact_level->value] }}"><i></i>{{ $item->impact_level->value }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td><div class="sub" style="display:flex; align-items:center; gap:8px"><x-avatar :name="$name($item->pic_account_id)" size="28" /> {{ $name($item->pic_account_id) }}</div></td>
                            <td><x-status-badge :value="$item->status->value" /></td>
                            <td>{{ $item->created_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') }}</td>
                            <td>
                                <a href="/risk-issues/{{ $item->id }}">
                                    @can('update', $item) Perbarui @else Lihat @endcan &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
