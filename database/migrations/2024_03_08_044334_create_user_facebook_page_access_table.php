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
        Schema::create('user_facebook_page_access', function (Blueprint $table) {
            $table->id();
            $table->string('facebook_id');
            $table->string('page_id');
            $table->boolean('app_has_leads_permission')->default(false);
            $table->boolean('can_access_lead')->default(false);
            $table->boolean('enabled_lead_access_manager')->default(false);
            $table->string('failure_reason')->nullable();
            $table->string('failure_resolution')->nullable();
            $table->boolean('is_page_admin')->default(false);
            $table->boolean('user_has_leads_permission')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
    }
};
