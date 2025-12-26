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
        Schema::create('outbound_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('outbound_id')
                ->constrained('outbounds')
                ->onDelete('cascade');

            $table->foreignId('attachment_id')
                ->constrained('attachments')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbound_attachments');
    }
};
