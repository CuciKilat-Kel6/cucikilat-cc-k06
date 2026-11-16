<?php

namespace App\Services;

use App\Models\Service;

class OrderPricing
{
    public function calculate(Service $service, ?string $weight, ?int $quantity): array
    {
        if ($service->type === 'satuan') {
            return ['weight_grams' => null, 'billable_grams' => null, 'quantity' => $quantity, 'total' => $service->price * $quantity];
        }
        $grams = (int) bcmul($weight, '1000', 0);
        $billable = max(2000, intdiv($grams + 499, 500) * 500);
        $numerator = $billable * $service->price * ($service->type === 'express' ? 3 : 2);

        return ['weight_grams' => $grams, 'billable_grams' => $billable, 'quantity' => null, 'total' => intdiv($numerator + 1999, 2000)];
    }
}
