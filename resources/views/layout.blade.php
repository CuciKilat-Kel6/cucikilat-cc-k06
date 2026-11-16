<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title','CuciKilat — Laundry kos, beres!')</title><link rel="stylesheet" href="{{ asset('app.css') }}?v={{ filemtime(public_path('app.css')) }}"><link rel="icon" href="{{ asset('bolt.svg') }}" type="image/svg+xml"></head>
<body><div class="topline">CUCI BERSIH. PAKAI LAGI. ULANGI. <span>LAUNDRY UNTUK ANAK KOS</span></div>
<header><a class="brand" href="{{ route('home') }}"><img src="{{ asset('bolt.svg') }}" alt="" width="35" height="44"> CUCI<span>KILAT</span><small>LAUNDRY CLUB</small></a><nav><a href="{{ route('track') }}" @class(['selected'=>request()->routeIs('track')])>Lacak cucian</a>@auth<a href="{{ route('dashboard') }}" @class(['selected'=>request()->routeIs('dashboard','orders.*')])>Order</a><a href="{{ route('services') }}" @class(['selected'=>request()->routeIs('services*')])>Layanan</a><a href="{{ route('report') }}" @class(['selected'=>request()->routeIs('report')])>Laporan</a><form method="post" action="{{ route('logout') }}">@csrf<button class="nav-button">Keluar ↗</button></form>@else<a class="nav-button" href="{{ route('login') }}">Masuk petugas ↗</a>@endauth</nav></header>
<main>@if(session('success'))<div role="status" class="notice">{{ session('success') }}</div>@endif @if($errors->any())<div role="alert" class="notice error"><strong>Cek isianmu dulu.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif @yield('content')</main>
<footer><a class="footer-brand" href="{{ route('home') }}">⚡ CUCIKILAT</a><span>Cucian beres. Urusan kampus, gas terus.</span><span>KELOMPOK 06 · CLOUD COMPUTING</span></footer><dialog id="action-confirm" aria-labelledby="confirm-title" aria-describedby="confirm-description">
    <div class="eyebrow"><img src="{{ asset('bolt.svg') }}" alt="" width="20" height="26"> Konfirmasi</div>
    <h2 id="confirm-title">LANJUTKAN?</h2>
    <p id="confirm-description"></p>
    <form method="dialog" class="confirm-actions">
        <button class="btn light" value="cancel" autofocus>Batal</button>
        <button class="btn" id="confirm-accept" value="confirm">Ya, lanjutkan</button>
    </form>
</dialog>
<script src="{{ asset('actions.js') }}?v={{ filemtime(public_path('actions.js')) }}" defer></script>@stack('scripts')</body></html>