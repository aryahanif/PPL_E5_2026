@extends('layouts.app')

@section('title', 'MoM')

@section('content')
    @php
        $tabs = [
            '' => ['Semua', $counts->sum()],
            'DRAFT' => ['Draf', $counts['DRAFT'] ?? 0],
            'IN_REVIEW' => ['Direview', $counts['IN_REVIEW'] ?? 0],
            'REVISION' => ['Revisi', $counts['REVISION'] ?? 0],
            'FINAL' => ['Final', $counts['FINAL'] ?? 0],
        ];
    @endphp

    <section class="hero">
        <div>
            <span class="tag"><i></i> {{ $project->active ? 'ACTIVE' : 'INACTIVE' }} · {{ $project->projectId }}</span>
            <h1 style="margin-top:10px">MoM · {{ $project->name }}</h1>
            <p>Notulen rapat proyek dan status reviewnya.</p>
        </div>
    </section>

    <div class="kpis">
        <div class="kpi"><span class="kpi-icon tone-accent"><x-icon name="file" size="20" /></span><div><strong>{{ $counts->sum() }}</strong><span>Total MoM</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-warning"><x-icon name="edit" size="20" /></span><div><strong>{{ ($counts['DRAFT'] ?? 0) + ($counts['REVISION'] ?? 0) }}</strong><span>Draf &amp; revisi</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-info"><x-icon name="clock" size="20" /></span><div><strong>{{ $counts['IN_REVIEW'] ?? 0 }}</strong><span>Direview</span></div></div>
        <div class="kpi"><span class="kpi-icon tone-success"><x-icon name="check" size="20" /></span><div><strong>{{ $counts['FINAL'] ?? 0 }}</strong><span>Final</span></div></div>
    </div>

    <div class="card-head">
        <nav class="tabs">
            @foreach ($tabs as $value => [$label, $total])
                <a href="/projects/{{ $project->projectId }}/moms{{ $value ? '?status='.$value : '' }}"
                   @class(['active' => ($status?->value ?? '') === $value])>{{ $label }} <span class="count">{{ $total }}</span></a>
            @endforeach
        </nav>
    </div>

    @if ($moms->isEmpty())
        <div class="card empty">
            <span class="ring"><x-icon name="file" size="24" /></span>
            <h2>Belum ada MoM</h2>
            <p class="muted">MoM ditulis TW dari <a href="/projects/{{ $project->projectId }}/meetings">rapat</a> yang sudah selesai.</p>
        </div>
    @else
        <div class="table-card">
            <table>
                <thead>
                    <tr><th>Tanggal rapat</th><th>Judul rapat</th><th>Penulis</th><th>Status MoM</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($moms as $mom)
                        <tr>
                            <td>{{ $mom->meeting_date->locale('id')->translatedFormat('d M Y') }}</td>
                            <td><a href="/moms/{{ $mom->id }}" style="color:var(--ink)"><strong>{{ $mom->meeting->title }}</strong></a></td>
                            <td>{{ $people[$mom->written_by]->displayName ?? $mom->written_by }}</td>
                            <td><x-status-badge :value="$mom->status->value" /></td>
                            <td><a href="/moms/{{ $mom->id }}">Lihat &rarr;</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
