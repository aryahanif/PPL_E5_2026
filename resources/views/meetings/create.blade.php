@extends('layouts.app')

@section('title', 'Jadwalkan Rapat')

@section('content')
    <a class="back" href="/projects/{{ $project->projectId }}/meetings"><x-icon name="arrow-left" size="16" /> Rapat</a>

    <div class="page-head">
        <div>
            <h1>Jadwalkan Rapat</h1>
            <p class="muted">Buat jadwal rapat untuk proyek berstatus ACTIVE.</p>
        </div>
    </div>

    @if (! $project->active)
        <div class="alert danger"><x-icon name="alert" /> Proyek tidak ditemukan atau tidak aktif, pengajuan rapat ditolak.</div>
    @else
        <div class="split">
            <form method="POST" action="/projects/{{ $project->projectId }}/meetings" class="card">
                @csrf

                @if ($errors->any())
                    <div class="alert danger">
                        <x-icon name="alert" />
                        <div>
                            Harap isi tanggal dan peserta rapat.
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif

                <div class="card-title" style="margin-bottom:16px"><span class="step">01</span><h2>Informasi Rapat</h2></div>

                <div class="cols">
                    <div class="field">
                        <label for="project">Proyek <span class="req">*</span></label>
                        <div class="input"><x-icon name="folder" size="16" /><input id="project" type="text" value="{{ $project->name }}" readonly></div>
                    </div>

                    <div class="field @error('title') invalid @enderror">
                        <label for="title">Judul rapat <span class="req">*</span></label>
                        <input id="title" name="title" type="text" maxlength="150" value="{{ old('title') }}" placeholder="Contoh: Weekly Meeting Minggu 5">
                        @error('title') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </div>

                    <div class="field @error('date') invalid @enderror">
                        <label for="date">Tanggal rapat <span class="req">*</span></label>
                        <input id="date" name="date" type="date" value="{{ old('date') }}">
                        @error('date') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </div>

                    <div class="field @error('time') invalid @enderror">
                        <label for="time">Jam rapat (WIB) <span class="req">*</span></label>
                        <input id="time" name="time" type="time" value="{{ old('time') }}">
                        @error('time') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="field @error('location') invalid @enderror">
                    <label for="location">Lokasi</label>
                    <div class="input"><x-icon name="pin" size="16" /><input id="location" name="location" type="text" maxlength="200" value="{{ old('location') }}" placeholder="Ruangan atau tautan rapat daring (opsional)"></div>
                    @error('location') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                </div>

                <div class="card-title" style="margin:6px 0 16px"><span class="step">02</span><h2>Peserta</h2></div>

                <fieldset class="field @if ($errors->has('participants') || $errors->has('participants.*')) invalid @endif">
                    <legend>Peserta internal <span class="req">*</span></legend>
                    <div class="choices">
                        @foreach ($members as $member)
                            <label class="choice">
                                <input type="checkbox" name="participants[]" value="{{ $member->accountId }}"
                                    @checked(in_array($member->accountId, old('participants', []), true))>
                                <x-avatar :name="$member->displayName" size="34" />
                                <span>{{ $member->displayName }}<br><span class="role">{{ $member->role->value }}</span></span>
                                <span class="tick"><x-icon name="check" size="14" /></span>
                            </label>
                        @endforeach
                    </div>
                    @error('participants') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                    @error('participants.*') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
                </fieldset>

                <div class="field">
                    <label for="clientNames">Peserta klien / user eksternal</label>
                    <div class="input"><x-icon name="user" size="16" /><input id="clientNames" name="clientNames" type="text" value="{{ old('clientNames') }}" placeholder="Contoh: Nadia Putri, Budi Hartono"></div>
                    <div class="hint">Klien tidak memiliki akun, dicatat nama saja. Pisahkan beberapa nama dengan koma.</div>
                </div>

                <div class="card-foot">
                    <button type="button" onclick="location.href='/projects/{{ $project->projectId }}/meetings'">Batal</button>
                    <button type="submit" class="primary"><x-icon name="check" size="16" /> Simpan Jadwal</button>
                </div>
            </form>

            <aside class="stack">
                <div class="card">
                    <div class="card-title" style="margin-bottom:14px"><span class="kpi-icon tone-accent" style="width:36px; height:36px"><x-icon name="info" /></span><h2>Alur rapat</h2></div>
                    <ul class="timeline">
                        <li><strong>SCHEDULED</strong><small>Rapat tersimpan setelah Anda menekan Simpan Jadwal.</small></li>
                        <li><strong>DONE atau CANCELLED</strong><small>PM menandai rapat selesai atau batal di halaman detail.</small></li>
                        <li><strong>MoM</strong><small>TW menulis MoM setelah rapat berstatus DONE, lalu TL mereview.</small></li>
                    </ul>
                </div>
                <div class="alert info"><x-icon name="users" /> Hanya anggota proyek yang bisa dipilih sebagai peserta internal.</div>
            </aside>
        </div>
    @endif
@endsection
