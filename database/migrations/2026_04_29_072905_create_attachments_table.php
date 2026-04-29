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
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')
                  ->constrained()
                  ->cascadeOnDelete();
            $table->string('file_url');
            $table->string('file_name');
            $table->string('file_type');
            $table->string('file_extension', 20);
            $table->unsignedBigInteger('file_size')->nullable();
            $table->boolean('is_image')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
