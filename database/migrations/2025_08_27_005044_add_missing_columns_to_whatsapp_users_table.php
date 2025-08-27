<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('whatsapp_users', function (Blueprint $table) {
            // add missing columns
            if (!Schema::hasColumn('whatsapp_users', 'session_data')) {
                $table->json('session_data')->nullable();
            }
            if (!Schema::hasColumn('whatsapp_users', 'current_step')) {
                $table->string('current_step')->default('welcome');
            }
            if (!Schema::hasColumn('whatsapp_users', 'first_message_at')) {
                $table->timestamp('first_message_at')->nullable();
            }
            if (!Schema::hasColumn('whatsapp_users', 'last_message_at')) {
                $table->timestamp('last_message_at')->nullable();
            }
            if (!Schema::hasColumn('whatsapp_users', 'message_count')) {
                $table->integer('message_count')->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('whatsapp_users', function (Blueprint $table) {
            $table->dropColumn([
                'session_data',
                'current_step',
                'first_message_at',
                'last_message_at',
                'message_count'
            ]);
        });
    }
};
