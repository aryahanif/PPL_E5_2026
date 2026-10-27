@extends('layouts.app')

@section('title', 'Catat Risiko/Isu')

@section('content')
    <a class="back" href="/projects/{{ $project->projectId }}/risk-issues"><x-icon name="arrow-left" size="16" /> Risiko &amp; Isu</a>

    <div class="page-head">
        <div>
            <h1>Catat Risiko/Isu</h1>
            <p class="muted">Catat risiko atau isu proyek dan tunjuk PIC yang menanganinya.</p>
        </div>
    </div>

    @if (! $project->active)
        <div class="alert danger"><x-icon name="alert" /> Proyek tidak aktif, risiko/isu baru tidak dapat dicatat.</div>
    @else
        <div class="split">
            <form method="POST" action="/projects/{{ $project->projectId }}/risk-issues" class="card">
                @csrf

                @if ($errors->any())
                    <div class="alert danger">
                        <x-icon name="alert" />
                        <div>
                            Periksa kembali isian berikut.
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <div class="card-title" style="margin-bottom:16px"><span class="step">01</span><h2>Informasi Risiko/Isu</h2></div>

                @include('risk-issues._fields', ['item' => null])

                <div class="card-foot">
                    <button type="button" onclick="location.href='/projects/{{ $project->projectId }}/risk-issues'">Batal</button>
                    <button type="submit" class="primary"><x-icon name="check" size="16" /> Simpan</button>
                </div>
            </form>

            <aside class="stack">
                <div class="card">
                    <div class="card-title" style="margin-bottom:14px"><span class="kpi-icon tone-accent" style="width:36px; height:36px"><x-icon name="info" /></span><h2>Alur status</h2></div>
                    <ul class="timeline">
                        <li><strong>OPEN</strong><small>Risiko/isu tersimpan setelah Anda menekan Simpan.</small></li>
                        <li><strong>IN_PROGRESS</strong><small>PIC mulai menangani dan mengisi mitigasi.</small></li>
                        <li><strong>CLOSED</strong><small>Ditutup dengan catatan penyelesaian.</small></li>
                    </ul>
                </div>
                <div class="alert info"><x-icon name="users" /> Hanya anggota proyek yang bisa dipilih sebagai PIC.</div>
            </aside>
        </div>
    @endif
@endsection
