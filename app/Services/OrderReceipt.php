<?php

namespace App\Services;

use App\Models\LaundryOrder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class OrderReceipt
{
    public function trackingUrl(LaundryOrder $order): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if (! in_array(parse_url($base, PHP_URL_SCHEME), ['http', 'https'], true) || ! parse_url($base, PHP_URL_HOST)) {
            throw new \LogicException('APP_URL harus berupa alamat HTTP atau HTTPS yang valid.');
        }

        return $base.route('track', ['code' => $order->code], false);
    }

    public function details(LaundryOrder $order): array
    {
        $url = $this->trackingUrl($order);
        $qr = new QrCode(data: $url, encoding: new Encoding('ISO-8859-1'), errorCorrectionLevel: ErrorCorrectionLevel::Medium, size: 360, margin: 32);
        $base = $order->service_type === 'satuan' ? $order->unit_price * $order->quantity * 100 : intdiv($order->unit_price * $order->billable_grams, 10);
        $extra = $order->service_type === 'express' ? intdiv($base, 2) : 0;

        return ['trackingUrl' => $url, 'qrDataUri' => (new PngWriter)->write($qr)->getDataUri(), 'baseAmount' => $base / 100, 'expressAmount' => $extra / 100, 'roundingAmount' => ($order->total * 100 - $base - $extra) / 100];
    }
}
