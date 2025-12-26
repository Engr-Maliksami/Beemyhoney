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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_customer_id')->constrained('user_customers')->onDelete('cascade');
            $table->foreignId('shipping_method_id')->nullable()->constrained('shipping_methods')->onDelete('set null');
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->onDelete('set null');
            $table->decimal('subtotal', 10, 2)->nullable();
            $table->decimal('discount', 10, 2)->nullable();
            $table->decimal('total_amount', 10, 2)->nullable();
            $table->decimal('delivery_fee', 10, 2)->nullable();
            $table->decimal('tax', 10, 2)->nullable();

            $table->enum('status', ['pending','confirmed','completed', 'cancelled', 'shipped', 'delivered'])->default('pending');
            $table->enum('source', ['auto', 'manual'])->default('manual');
            
            $table->string('order_number')->unique();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();


            $table->timestamp('order_date')->useCurrent();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
