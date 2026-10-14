@extends('layouts.app')

@section('title', 'Dev login')

@section('content')
    <h1>Masuk (mode pengembangan)</h1>
    <p class="muted">Akun tiruan dari F-1. Halaman ini diganti login F-1 saat integrasi.</p>

    <form method="POST" action="/dev-login">
        @csrf
        <table>
            <tr><th></th><th>Akun</th><th>Nama</th><th>Peran</th></tr>
            @foreach ($accounts as $account)
                <tr>
                    <td><input type="radio" name="accountId" value="{{ $account->accountId }}" id="{{ $account->accountId }}" @checked($loop->first)></td>
                    <td><label for="{{ $account->accountId }}">{{ $account->accountId }}</label></td>
                    <td>{{ $account->displayName }}</td>
                    <td><span class="badge">{{ $account->role->value }}</span></td>
                </tr>
            @endforeach
        </table>
        <p><button class="primary" type="submit">Masuk</button></p>
    </form>
@endsection
