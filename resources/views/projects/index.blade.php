@extends('layouts.app')

@section('title', 'Proyek')

@section('content')
    <h1>Proyek saya</h1>

    @if (empty($projects))
        <p class="muted">Tidak ada proyek aktif untuk akun ini.</p>
    @else
        <table>
            <tr><th>ID</th><th>Nama proyek</th><th></th></tr>
            @foreach ($projects as $project)
                <tr>
                    <td>{{ $project->projectId }}</td>
                    <td>{{ $project->name }}</td>
                    <td><a href="/projects/{{ $project->projectId }}/meetings">Rapat</a></td>
                </tr>
            @endforeach
        </table>
    @endif
@endsection
