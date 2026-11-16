<?php

namespace App\Http\Controllers;

use App\Models\LaundryOrder;
use App\Services\OrderReceipt;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    public function __invoke(LaundryOrder $order, OrderReceipt $receipt): Response
    {
        return response()->view('receipt', array_merge(['order' => $order], $receipt->details($order)))
            ->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
