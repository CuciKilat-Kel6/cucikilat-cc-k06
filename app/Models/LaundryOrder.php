<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LaundryOrder extends Model
{
    use HasFactory;

    public const STATUSES = ['DITERIMA', 'DICUCI', 'DIKERINGKAN', 'DISETRIKA', 'SIAP_DIAMBIL', 'DIAMBIL'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['history' => 'array', 'due_at' => 'datetime', 'paid_at' => 'datetime', 'total' => 'integer', 'billable_grams' => 'integer'];
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class)->withTrashed();
    }
}
