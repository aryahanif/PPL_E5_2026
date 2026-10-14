@extends('layouts.app')

@section('title', 'Rapat')

@section('content')
    <p><a href="/projects">&larr; Proyek</a></p>
    <h1>Rapat · {{ $project->name }}</h1>

    {{-- TODO US-1: tombol "Jadwalkan rapat" untuk PM --}}

    @if ($meetings->isEmpty())
        <p class="muted">Belum ada rapat.</p>
    @else
        <table>
            <tr><th>Jadwal (WIB)</th><th>Judul</th><th>Status rapat</th><th>MoM</th></tr>
            @foreach ($meetings as $meeting)
                <tr>
                    <td>{{ $meeting->scheduled_at->timezone(config('e5.display_timezone'))->format('d M Y H:i') }}</td>
                    <td>{{ $meeting->title }}</td>
                    <td><span class="badge">{{ $meeting->status->value }}</span></td>
                    <td>{{ $meeting->mom?->status->value ?? '—' }}</td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
