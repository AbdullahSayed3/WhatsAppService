<?php
// database/migrations/xxxx_xx_xx_create_whatsapp_messages_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('message_id')->unique(); // WhatsApp message ID
            $table->string('phone_number');
            $table->enum('direction', ['inbound', 'outbound']);
            $table->enum('type', ['text', 'image', 'document', 'audio', 'template']);
            $table->text('content');
            $table->json('metadata')->nullable(); // for images, files, etc.
            $table->enum('status', ['sent', 'delivered', 'read', 'failed'])->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index('phone_number');
            $table->index('direction');
            $table->index('created_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
