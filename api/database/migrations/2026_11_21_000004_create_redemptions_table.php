<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
            $table->string('order_id', 100);
            $table->string('user_identifier', 150);
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->decimal('order_amount', 15, 2);
            $table->decimal('discount_amount', 15, 2);
            $table->decimal('final_amount', 15, 2);
            $table->dateTime('redeemed_at');
            $table->timestamps();

            $table->unique(['voucher_id', 'order_id']);
            $table->index(['voucher_id', 'user_identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redemptions');
    }
};
