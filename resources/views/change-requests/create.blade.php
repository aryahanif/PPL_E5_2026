@extends('layouts.app')

@section('title', 'Catat Perubahan')

@section('content')
    @php
        $types = [
            'SCOPE' => ['Ruang lingkup', 'Menambah atau mengurangi fitur'],
            'TIMELINE' => ['Jadwal', 'Menggeser tanggal atau rilis'],
            'TASK' => ['Tugas', 'Mengubah tugas yang sudah ada'],
            'RESOURCE' => ['Sumber daya', 'Orang, alat, atau biaya'],
        ];
    @endphp

    <a class="back" href="/projects/{{ $project->projectId }}/change-requests"><x-icon name="arrow-left" size="16" /> Change Request</a>

    <div class="page-head">
        <div>
            <h1>Catat Perubahan</h1>
            <p class="muted">Catat penyesuaian atau CR formal yang muncul dari MoM FINAL.</p>
        </div>
    </div>

    @if (! $project->active)
        <div class="alert danger"><x-icon name="alert" /> Proyek tidak aktif, perubahan baru tidak dapat dicatat.</div>
    @elseif ($moms->isEmpty())
        <div class="card empty">
            <span class="ring"><x-icon name="file" size="24" /></span>
            <h2>Belum ada MoM FINAL</h2>
            <p class="muted">Perubahan hanya dapat dicatat dari MoM yang sudah difinalkan TL.</p>
        </div>
    @else
        <div class="split">
            <form method="POST" action="/projects/{{ $project->projectId }}/change-requests" class="card">
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

                <div class="card-title" style="margin-bottom:16px"><span class="step">01</span><h2>Informasi Perubahan</h2></div>

                <div class="field @error('momId') invalid @enderror">
                    <label for="momId">MoM sumber <span class="req">*</span></label>
                    <select id="momId" name="momId">
                        <option value="" disabled @selected(! old('momId', $selectedMomId))>Pilih MoM FINAL</option>
                        @foreach ($moms as $mom)
                            <option value="{{ $mom->id }}" @selected(old('momId', $selectedMomId) === $mom->id)>{{ $mom->meeting->title }} · {{ \Carbon\Carbon::parse($mom->meeting_date)->locale('id')->translatedFormat('d M Y') }}</option>
                        @endforeach
                    </select>
                    @error('momId') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                </div>

                <div class="field @error('changeDescription') invalid @enderror">
                    <label for="changeDescription">Deskripsi perubahan <span class="req">*</span></label>
                    <textarea id="changeDescription" name="changeDescription" rows="3" placeholder="Contoh: Klien meminta tambahan ekspor laporan ke Excel">{{ old('changeDescription') }}</textarea>
                    @error('changeDescription') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                </div>

                <fieldset class="field @error('changeType') invalid @enderror">
                    <legend>Jenis perubahan <span class="req">*</span></legend>
                    <div class="choices">
                        @foreach ($types as $type => [$label, $hint])
                            <label class="choice">
                                <input type="radio" name="changeType" value="{{ $type }}" @checked(old('changeType') === $type)>
                                <span>{{ $label }}<br><span class="muted" style="font-size:12px">{{ $hint }}</span></span>
                                <span class="tick"><x-icon name="check" size="14" /></span>
                            </label>
                        @endforeach
                    </div>
                    @error('changeType') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                </fieldset>

                <div class="cols">
                    <div class="field @error('source') invalid @enderror">
                        <label for="source">Sumber <span class="req">*</span></label>
                        <select id="source" name="source">
                            <option value="" disabled @selected(! old('source'))>Pilih sumber</option>
                            <option value="USER" @selected(old('source') === 'USER')>USER · permintaan klien</option>
                            <option value="INTERNAL" @selected(old('source') === 'INTERNAL')>INTERNAL · dari tim</option>
                        </select>
                        @error('source') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </div>

                    <div class="field @error('isFormalCr') invalid @enderror">
                        <label for="isFormalCr">Bentuk <span class="req">*</span></label>
                        <select id="isFormalCr" name="isFormalCr">
                            <option value="" disabled @selected(old('isFormalCr') === null)>Pilih bentuk</option>
                            <option value="0" @selected(old('isFormalCr') === '0')>Penyesuaian (langsung RECORDED)</option>
                            <option value="1" @selected(old('isFormalCr') === '1')>CR formal (perlu analisis dan keputusan)</option>
                        </select>
                        @error('isFormalCr') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="card-foot">
                    <button type="button" onclick="location.href='/projects/{{ $project->projectId }}/change-requests'">Batal</button>
                    <button type="submit" class="primary"><x-icon name="check" size="16" /> Simpan</button>
                </div>
            </form>

            <aside class="stack">
                <div class="card">
                    <div class="card-title" style="margin-bottom:14px"><span class="kpi-icon tone-accent" style="width:36px; height:36px"><x-icon name="info" /></span><h2>Alur status</h2></div>
                    <ul class="timeline">
                        <li><strong>RECORDED</strong><small>Penyesuaian kecil, langsung masuk change log tanpa persetujuan.</small></li>
                        <li><strong>PENDING_ANALYSIS</strong><small>CR formal menunggu TL menganalisis dampaknya.</small></li>
                        <li><strong>ANALYZED</strong><small>Dampak sudah diisi, menunggu keputusan PM atau TL.</small></li>
                        <li><strong>APPROVED / REJECTED</strong><small>Diputuskan dengan alasan yang wajib diisi.</small></li>
                    </ul>
                </div>
            </aside>
        </div>
    @endif
@endsection
