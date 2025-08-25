<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

Route::get('/', function () {
    return view('Test');
});

// WhatsApp Webhook routes
Route::get('/whatsapp/webhook', [WhatsAppController::class, 'verifyWebhook']);
Route::post('/whatsapp/webhook', [WhatsAppController::class, 'receiveMessage']);

// API routes for testing
Route::post('/api/whatsapp/send', [WhatsAppController::class, 'sendMessage']);
Route::post('/api/whatsapp/template', [WhatsAppController::class, 'sendTemplate']);
