<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkout_attempts', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->char('owner_hash', 64);
            $table->char('quote_hash', 64);
            $table->foreignId('order_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checkout_attempts');
    }
};
