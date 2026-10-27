{{-- Risk/issue fields a PM fills in. $item is null when recording a new one. --}}
@php
    $value = fn (string $field, $current = null) => old($field, $current);
    $types = [
        'RISK' => ['Risiko', 'Kemungkinan masalah yang belum terjadi'],
        'ISSUE' => ['Isu', 'Masalah yang sedang terjadi'],
    ];
@endphp

<fieldset class="field @error('type') invalid @enderror">
    <legend>Jenis <span class="req">*</span></legend>
    <div class="choices">
        @foreach ($types as $type => [$label, $hint])
            <label class="choice">
                <input type="radio" name="type" value="{{ $type }}" @checked($value('type', $item?->type->value) === $type)>
                <span class="kpi-icon tone-{{ $type === 'RISK' ? 'warning' : 'danger' }}" style="width:34px; height:34px"><x-icon name="alert" size="18" /></span>
                <span>{{ $label }}<br><span class="muted" style="font-size:12px">{{ $hint }}</span></span>
                <span class="tick"><x-icon name="check" size="14" /></span>
            </label>
        @endforeach
    </div>
    @error('type') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
</fieldset>

<div class="field @error('title') invalid @enderror">
    <label for="title">Judul <span class="req">*</span></label>
    <input id="title" name="title" type="text" maxlength="150" value="{{ $value('title', $item?->title) }}" placeholder="Contoh: Server uji tidak dapat diakses">
    @error('title') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
</div>

<div class="field @error('description') invalid @enderror">
    <label for="description">Deskripsi <span class="req">*</span></label>
    <textarea id="description" name="description" rows="3" placeholder="Apa yang terjadi atau mungkin terjadi, dan dampaknya ke proyek">{{ $value('description', $item?->description) }}</textarea>
    @error('description') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
</div>

<div class="cols">
    <div class="field @error('impactLevel') invalid @enderror">
        <label for="impactLevel">Tingkat dampak</label>
        <select id="impactLevel" name="impactLevel">
            <option value="">Belum ditentukan</option>
            @foreach (['LOW' => 'LOW · rendah', 'MEDIUM' => 'MEDIUM · sedang', 'HIGH' => 'HIGH · tinggi'] as $level => $label)
                <option value="{{ $level }}" @selected($value('impactLevel', $item?->impact_level?->value) === $level)>{{ $label }}</option>
            @endforeach
        </select>
        @error('impactLevel') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
    </div>

    <div class="field @error('picAccountId') invalid @enderror">
        <label for="picAccountId">PIC <span class="req">*</span></label>
        <select id="picAccountId" name="picAccountId">
            <option value="" disabled @selected(! $value('picAccountId', $item?->pic_account_id))>Pilih anggota proyek</option>
            @foreach ($members as $member)
                <option value="{{ $member->accountId }}" @selected($value('picAccountId', $item?->pic_account_id) === $member->accountId)>{{ $member->displayName }} ({{ $member->role->value }})</option>
            @endforeach
        </select>
        <div class="hint">TL yang menjadi PIC dapat memperbarui status dan mitigasinya.</div>
        @error('picAccountId') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
    </div>
</div>

<div class="field @error('sourceMomId') invalid @enderror">
    <label for="sourceMomId">Sumber MoM</label>
    <select id="sourceMomId" name="sourceMomId">
        <option value="">Tidak dari MoM</option>
        @foreach ($moms as $mom)
            <option value="{{ $mom->id }}" @selected($value('sourceMomId', $item?->source_mom_id) === $mom->id)>{{ $mom->meeting->title }} · {{ \Carbon\Carbon::parse($mom->meeting_date)->locale('id')->translatedFormat('d M Y') }}</option>
        @endforeach
    </select>
    @error('sourceMomId') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
</div>

<div class="field @error('mitigation') invalid @enderror">
    <label for="mitigation">Mitigasi</label>
    <textarea id="mitigation" name="mitigation" rows="2" placeholder="Langkah untuk mencegah atau mengatasi (opsional)">{{ $value('mitigation', $item?->mitigation) }}</textarea>
    @error('mitigation') <div class="field-error"><x-icon name="alert" size="14" /> {{ $message }}</div> @enderror
</div>
