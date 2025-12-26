<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserFacebookPagesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('user_facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->string('facebook_id');
            $table->string('page_id');
            $table->string('name');
            $table->text('cover_url')->nullable();
            $table->string('email')->nullable();
            $table->string('username')->nullable();
            $table->text('t_customer')->nullable();
            $table->text('t_address')->nullable();
            $table->text('t_order')->nullable();
            $table->text('t_invoice')->nullable();
            $table->text('t_shipped')->nullable();
            $table->text('t_comment')->nullable();
            $table->text('page_access_token');
            $table->boolean('bot_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
    }
}

