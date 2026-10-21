@extends('layouts.app')

@section('title', 'Dev login')

@section('content')
    @php $roleNames = ['PM' => 'Project Manager', 'TL' => 'Technical Lead', 'TW' => 'Technical Writer']; @endphp

    <div class="guest-card">
        <div class="brand" style="justify-content:center; margin-bottom:18px">
            <span class="brand-mark"><x-icon name="layers" size="18" /></span>
            <div>ProjectFlow<small>E-5 Pengawasan, Rapat, Risiko &amp; Perubahan</small></div>
        </div>

        <form method="POST" action="/dev-login" class="card">
            @csrf
            <div class="card-head">
                <div>
                    <h1>Masuk</h1>
                    <p class="muted" style="margin:6px 0 0">Mode pengembangan: pilih akun tiruan dari F-1. Halaman ini diganti login F-1 saat integrasi.</p>
                </div>
            </div>

            <fieldset class="field">
                <legend>Pilih akun</legend>
                <div class="choices">
                    @foreach ($accounts as $account)
                        <label class="choice">
                            <input type="radio" name="accountId" value="{{ $account->accountId }}" @checked($loop->first)>
                            <x-avatar :name="$account->displayName" size="36" />
                            <span>
                                <strong style="display:block">{{ $account->displayName }}</strong>
                                <span class="muted mono">{{ $account->accountId }}</span>
                                <span class="role">{{ $account->role->value }}</span>
                                <span class="muted" style="display:block; font-size:12px">{{ $roleNames[$account->role->value] }}</span>
                            </span>
                            <span class="tick"><x-icon name="check" size="14" /></span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="card-foot">
                <button class="primary" type="submit">Masuk <x-icon name="arrow-right" size="16" /></button>
            </div>
        </form>
    </div>
@endsection
