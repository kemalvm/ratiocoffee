@extends('layouts.app')
@section('title', '404 – Halaman Tidak Ditemukan')
@section('content')
<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;background:var(--bg-base)">
    <div class="text-center p-5">
        <div style="font-size:80px;line-height:1">🍵</div>
        <h1 style="font-size:72px;font-weight:900;color:var(--clr-primary);margin:0">404</h1>
        <h2 style="font-size:22px;font-weight:700;margin:12px 0 8px">Halaman Tidak Ditemukan</h2>
        <p style="color:var(--txt-muted);margin-bottom:28px">Sepertinya halaman yang kamu cari sudah pindah atau tidak ada.</p>
        <a href="/dashboard" class="btn btn-primary">← Kembali ke Dashboard</a>
    </div>
</div>
@endsection
