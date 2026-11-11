@extends('layouts.app')

@section('title', 'Change Request')

@section('content')
    @php
        $tz = config('e5.display_timezone');
        $name = fn ($accountId) => $people[$accountId]->displayName ?? $accountId;
        $counts = $all->countBy(fn ($item) => $item->status->value);
        $url = fn (?string $s) => '/projects/'.$project->projectId.'/change-requests'.($s ? '?status='.$s : '');
        $tabs = [
            '' => ['Semua', $all->count()],
            'RECORDED' => ['Penyesuaian', $counts['RECORDED'] ?? 0],
            'PENDING_ANALYSIS' => ['Menunggu analisis', $counts['PENDING_ANALYSIS'] ?? 0],
            'ANALYZED' => ['Menunggu keputusan', $counts['ANALYZED'] ?? 0],
            'APPROVED' => ['Disetujui', $counts['APPROVED'] ?? 0],
            'REJECTED' => ['Ditolak', $counts['REJECTED'] ?? 0],
        ];
        $typeLabels = ['SCOPE' => 'Ruang lingkup', 'TIMELINE' => 'Jadwal', 'TASK' => 'Tugas', 'RESOURCE' => 'Sumber daya'];
    @endphp

    <section class="hero">
        <div>
            <span class="tag"><i></i> {{ $project->active ? 'ACTIVE' : 'INACTIVE' }} · {{ $project->projectId }}</span>
            <h1 style="margin-top:10px">Change Request · {{ $project->name }}</h1>
            <p>Penyesuaian dan CR formal dari MoM FINAL, beserta analisis dampak dan keputusannya.</p>
        </div>
        <div class="hero-side">
            @can('create', App\Models\ChangeRequest::class)
                <form method="GET" action="/projects/{{ $project->projectId }}/change-requests/create">
                    <button type="submit" class="primary"><x-icon name="plus" size="16" /> Catat Perubahan</button>
                </form>
            @endcan
        </div>
    </section>

    <div class="kpis">
        <div class="kpi"><span class="kpi-icon tone-accent"><x-icon name="change" size="20" /></span><div><strong>{{ $all->count() }}</strong><span>Total perubahan</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-warning"><x-icon name="clock" size="20" /></span><div><strong>{{ $counts['PENDING_ANALYSIS'] ?? 0 }}</strong><span>Menunggu analisis TL</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-info"><x-icon name="info" size="20" /></span><div><strong>{{ $counts['ANALYZED'] ?? 0 }}</strong><span>Menunggu keputusan</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-success"><x-icon name="check" size="20" /></span><div><strong>{{ $counts['APPROVED'] ?? 0 }}</strong><span>Disetujui</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-danger"><x-icon name="x" size="20" /></span><div><strong>{{ $counts['REJECTED'] ?? 0 }}</strong><span>Ditolak</span></div></div>
    </div>

    <div class="card-head" style="flex-wrap:wrap">
        <nav class="tabs" aria-label="Filter status">
            @foreach ($tabs as $value => [$label, $total])
                <a href="{{ $url($value) }}" @class(['active' => ($status?->value ?? '') === $value])>{{ $label }} <span class="count">{{ $total }}</span></a>
            @endforeach
        </nav>
    </div>

    @if ($items->isEmpty())
        <div class="card empty">
            <span class="ring"><x-icon name="change" size="24" /></span>
            <h2>Belum ada perubahan</h2>
            <p class="muted">
                @if ($all->isEmpty())
                    Perubahan yang dicatat PM atau TL dari MoM FINAL akan muncul di sini.
                @else
                    Tidak ada perubahan yang cocok dengan filter ini.
                @endif
            </p>
        </div>
    @else
        <div class="table-card">
            <table>
                <thead>
                    <tr><th>Perubahan</th><th>Jenis</th><th>Bentuk</th><th>Dicatat oleh</th><th>Status</th><th>Dicatat (WIB)</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td style="max-width:360px">
                                <a href="/change-requests/{{ $item->id }}" style="color:var(--ink)"><strong>{{ \Illuminate\Support\Str::limit($item->change_description, 70) }}</strong></a>
                                <div class="sub">Dari {{ $item->mom?->meeting?->title ?? 'MoM' }} · {{ $item->source->value === 'USER' ? 'Klien' : 'Internal' }}</div>
                            </td>
                            <td><span class="badge">{{ $typeLabels[$item->change_type->value] }}</span></td>
                            <td>{{ $item->is_formal_cr ? 'CR formal' : 'Penyesuaian' }}</td>
                            <td><div class="sub" style="display:flex; align-items:center; gap:8px"><x-avatar :name="$name($item->created_by)" size="28" /> {{ $name($item->created_by) }}</div></td>
                            <td><x-status-badge :value="$item->status->value" /></td>
                            <td>{{ $item->created_at->timezone($tz)->locale('id')->translatedFormat('d M Y H:i') }}</td>
                            <td><a href="/change-requests/{{ $item->id }}">Lihat &rarr;</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
