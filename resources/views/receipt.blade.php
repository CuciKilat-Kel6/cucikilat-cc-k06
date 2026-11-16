<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Struk {{ $order->code }} — CuciKilat</title>
    <link rel="stylesheet" href="{{ asset('receipt.css') }}">
    <link rel="icon" href="{{ asset('bolt.svg') }}" type="image/svg+xml">
    <script src="{{ asset('receipt.js') }}" defer></script>
</head>
<body>
    <div class="receipt-toolbar">
        <a href="{{ route('orders.show',$order) }}">← Kembali ke order</a>
        <button type="button" id="print-receipt">Cetak / simpan PDF ↗</button>
    </div>
    <div class="screen-note">
        <strong>Satu struk, semua info cucian.</strong>
        <p>Cetak untuk pelanggan atau pilih “Save as PDF” di dialog cetak. Cocok untuk kertas struk 80 mm maupun A4. Matikan header/footer browser agar hasil rapi. Jika dialog cetak tidak muncul di browser dalam aplikasi, buka struk ini di Chrome, Edge, Brave, atau Safari.</p>
        @if($localTracking)
            <p class="local-note">QR ini masih memakai alamat lokal <b>{{ parse_url($trackingUrl, PHP_URL_HOST) }}</b>. HP pelanggan belum bisa membukanya. Saat deploy, sesuaikan APP_URL ke alamat web yang bisa diakses pelanggan, lalu cetak ulang.</p>
        @endif
    </div>
    <main class="receipt" aria-label="Struk laundry">
        <header class="receipt-brand">
            <img src="{{ asset('bolt.svg') }}" width="29" height="38" alt="Logo petir">
            <div><h1>CUCIKILAT</h1><p>LAUNDRY CLUB · NOTA CUCIAN</p></div>
        </header>
        <section class="receipt-code"><span>KODE ORDER</span><strong>{{ $order->code }}</strong><span>{{ str_replace('_',' ',$order->status) }}</span></section>
        <dl>
            <div><dt>Masuk</dt><dd>{{ $order->created_at->format('d/m/Y H:i') }} WIB</dd></div>
            <div><dt>Pelanggan</dt><dd>{{ $order->customer_name }}</dd></div>
            <div><dt>Telepon</dt><dd>{{ $order->phone }}</dd></div>
            <div><dt>Layanan</dt><dd>{{ $order->service_name }}</dd></div>
            @if($order->service_type==='satuan')
                <div><dt>Jumlah</dt><dd>{{ $order->quantity }} item</dd></div>
            @else
                <div><dt>Berat aktual</dt><dd>{{ number_format($order->weight_grams/1000,3,',','.') }} kg</dd></div>
                <div><dt>Berat tagihan</dt><dd>{{ number_format($order->billable_grams/1000,1,',','.') }} kg</dd></div>
            @endif
        </dl>
        <section class="bill">
            <h2>RINCIAN HARGA</h2>
            <dl>
                <div><dt>Tarif dasar / {{ $order->service_type==='satuan'?'item':'kg' }}</dt><dd>Rp{{ number_format($order->unit_price,0,',','.') }}</dd></div>
                <div><dt>Subtotal</dt><dd>Rp{{ number_format($baseAmount,2,',','.') }}</dd></div>
                @if($order->service_type==='express')<div><dt>Express +50%</dt><dd>Rp{{ number_format($expressAmount,2,',','.') }}</dd></div>@endif
                @if($roundingAmount>0)<div><dt>Pembulatan rupiah</dt><dd>Rp{{ number_format($roundingAmount,2,',','.') }}</dd></div>@endif
                <div class="bill-total"><dt>TOTAL</dt><dd>Rp{{ number_format($order->total,0,',','.') }}</dd></div>
            </dl>
            <div class="payment">{{ $order->paid_at?'LUNAS':'BELUM DIBAYAR' }}@if($order->paid_at)<span>{{ $order->paid_at->format('d/m/Y H:i') }} WIB</span>@endif</div>
        </section>
        <section class="promise"><strong>ESTIMASI SELESAI</strong><p>{{ $order->due_at->format('d/m/Y H:i') }} WIB</p><small>{{ $order->service_type==='express'?'Express 1 hari':($order->service_type==='satuan'?'Satuan 3 hari':'Reguler 3 hari') }} sejak order diterima.</small></section>
        @if($order->notes)<section class="customer-note"><strong>CATATAN</strong><p>{{ $order->notes }}</p></section>@endif
        <section class="tracking"><h2>SCAN UNTUK LACAK CUCIAN</h2><img id="tracking-qr" class="qr" src="{{ $qrDataUri }}" width="180" height="180" alt="QR tautan pelacakan {{ $order->code }}"><p>Scan kamera HP, atau buka:</p><a id="tracking-url" href="{{ $trackingUrl }}">{{ $trackingUrl }}</a><p class="keep-note">Simpan struk atau foto QR ini.<br>Bawa saat mengambil cucian. Kode lacak bukan pengganti pemeriksaan identitas oleh petugas.</p></section>
        <div class="receipt-bottom">CUCI BERSIH. PAKAI LAGI. ULANGI.<br><b>TERIMA KASIH SUDAH TITIP CUCIAN!</b></div>
    </main>
    <div class="share-tools"><label for="copy-url">Tautan pelacakan pelanggan</label><div><input id="copy-url" readonly value="{{ $trackingUrl }}"><button type="button" id="copy-link">Salin tautan</button></div><p id="copy-status" role="status" aria-live="polite">Tautan ini langsung menampilkan progres, tanpa login pelanggan.</p></div>
</body>
</html>
