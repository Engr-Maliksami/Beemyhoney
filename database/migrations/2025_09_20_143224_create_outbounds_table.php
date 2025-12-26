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
        Schema::create('outbounds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_ext_id'); // external order ID
            $table->string('order_ext_number')->nullable();

            $table->string('recipient_name');
            $table->string('recipient_company_code')->nullable();
            $table->string('recipient_street');
            $table->string('recipient_city');
            $table->string('recipient_post_code');
            $table->string('recipient_country');
            $table->string('recipient_county')->nullable();
            $table->string('recipient_parish')->nullable();
            $table->string('recipient_house_name')->nullable();
            $table->string('recipient_email');
            $table->string('recipient_tel');

            $table->string('carrier_name')->nullable();
            $table->string('carrier_transport_no')->nullable();

            $table->string('delivery_type_code'); // SD, WL, CT
            $table->text('order_comment')->nullable();

            $table->date('delivery_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outbounds');
    }
};
