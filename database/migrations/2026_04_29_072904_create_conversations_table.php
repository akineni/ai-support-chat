<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->index();
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('session_token')->unique()->index();
            $table->enum('status', ['open', 'pending_handover', 'closed'])->default('open');
            $table->enum('mode', ['ai', 'human'])->default('ai');
            $table->foreignId('assigned_agent_id')
                  ->nullable()
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('taken_over_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
