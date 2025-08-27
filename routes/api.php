<?php
// routes/api.php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

// Home route - Simple status check
Route::get('/', function () {
    return response()->json([
        'service' => 'WhatsApp Business API',
        'status' => 'Active',
        'version' => '1.0',
        'timestamp' => now()->toISOString()
    ]);
});

// WhatsApp Webhook routes
Route::get('/whatsapp/webhook', [WhatsAppController::class, 'verifyWebhook']);
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'receiveMessage']);

// WhatsApp API routes
Route::prefix('whatsapp')->group(function () {
    Route::post('send', [WhatsAppController::class, 'sendMessage']);
    Route::post('template', [WhatsAppController::class, 'sendTemplate']);
    Route::get('stats', [WhatsAppController::class, 'getStats']);
    Route::get('test', [WhatsAppController::class, 'testPage']);
});
