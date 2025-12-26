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
        Schema::create('inbound_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inbound_id')->constrained('inbounds')->onDelete('cascade');
            $table->foreignId('attachment_id')->constrained('attachments')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inbound_attachments');
    }
};
