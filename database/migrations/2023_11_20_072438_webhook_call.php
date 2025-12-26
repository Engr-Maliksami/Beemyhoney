<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_call', function (Blueprint $table) {
            $table->id();
            $table->string('page_id')->nullable();
            $table->string('post_id')->nullable();
            $table->string('comment_id')->nullable();
            $table->string('message')->nullable();
            $table->string('cus_fb_id')->nullable();
            $table->string('cus_fb_name')->nullable();
            $table->string('item_type')->nullable();
            $table->string('field')->nullable();
            $table->json('from')->nullable();
            $table->json('post')->nullable();
            $table->json('value')->nullable();
            $table->json('headers')->nullable();
            $table->ipAddress('ip')->nullable();
            $table->string('order_id')->nullable();
            $table->string('user_agent')->nullable();
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->json('raw_json')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_call');
    }
};
