<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('laundry_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->string('customer_name');
            $table->string('phone', 30);
            $table->text('notes')->nullable();
            $table->string('service_name');
            $table->string('service_type');
            $table->unsignedInteger('unit_price');
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('billable_grams')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->unsignedBigInteger('total');
            $table->string('status')->default('DITERIMA')->index();
            $table->dateTime('due_at');
            $table->dateTime('paid_at')->nullable()->index();
            $table->json('history');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laundry_orders');
    }
};
