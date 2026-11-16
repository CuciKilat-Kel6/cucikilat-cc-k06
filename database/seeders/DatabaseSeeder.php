<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([['name' => 'Cuci Reguler', 'type' => 'reguler', 'price' => 7000, 'description' => 'Cuci, kering, setrika. Estimasi 3 hari.'], ['name' => 'Cuci Express', 'type' => 'express', 'price' => 7000, 'description' => 'Tarif dasar per kg + 50%. Estimasi 1 hari.'], ['name' => 'Sepatu Satuan', 'type' => 'satuan', 'price' => 30000, 'description' => 'Harga per pasang. Estimasi 3 hari.']] as $service) {
            Service::withTrashed()->firstOrCreate(['name' => $service['name']], $service);
        }
    }
}
