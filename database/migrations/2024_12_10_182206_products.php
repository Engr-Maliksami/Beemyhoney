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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->decimal('weight', 8, 2)->default(0.00)->nullable();
            $table->integer('stock_quantity')->default(0);
            $table->string('sku')->nullable();
            $table->string('image_url')->nullable();
            $table->string('ean')->nullable();
            $table->string('batch_number')->nullable();
            $table->date('expire_date')->nullable();
            $table->integer('quantity_box')->nullable();
            $table->integer('quantity_pallet')->nullable();
            $table->integer('net_weight')->nullable();
            $table->integer('gross_weight')->nullable();
            $table->integer('length')->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('quantity_per_box')->nullable();
            $table->integer('quantity_per_pallet')->nullable();
            $table->text('comment')->nullable();
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
