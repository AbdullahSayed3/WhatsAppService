<?php
// app/Http/Controllers/WhatsAppController.php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    private $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Verify Webhook
     */
    public function verifyWebhook(Request $request)
    {
        $verifyToken = config('services.whatsapp.webhook_token');
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        Log::info('Webhook verification attempt', [
            'mode' => $mode,
            'token' => $token,
            'expected_token' => $verifyToken
        ]);

        if ($mode && $token === $verifyToken) {
            Log::info('Webhook verified successfully');
            return response($challenge, 200);
        }

        Log::warning('Webhook verification failed');
        return response('Forbidden', 403);
    }

    /**
     * Receive messages from WhatsApp
     */
    public function receiveMessage(Request $request)
    {
        $body = $request->all();
        Log::info('WhatsApp Webhook received: ' . json_encode($body));

        // Verify incoming messages
        if (isset($body['entry'][0]['changes'][0]['value']['messages'])) {
            foreach ($body['entry'][0]['changes'][0]['value']['messages'] as $message) {
                $this->processIncomingMessage($message, $body['entry'][0]['changes'][0]['value']);
            }
        }

        // Verify message status updates
        if (isset($body['entry'][0]['changes'][0]['value']['statuses'])) {
            foreach ($body['entry'][0]['changes'][0]['value']['statuses'] as $status) {
                $this->processMessageStatus($status);
            }
        }

        return response('OK', 200);
    }

    /**
     * Process Incoming Message
     */
    private function processIncomingMessage($message, $value)
    {
        $from = $message['from'];
        $messageId = $message['id'];
        $timestamp = $message['timestamp'];

        // Mark message as read
        $this->whatsAppService->markAsRead($messageId);

        // Verify the type of message
        switch ($message['type']) {
            case 'text':
                $textBody = $message['text']['body'];
                Log::info("Received text message from {$from}: {$textBody}");
                $this->handleTextMessage($from, $textBody);
                break;

            case 'image':
                Log::info("Received image message from {$from}");
                $this->handleImageMessage($from, $message['image']);
                break;

            case 'document':
                Log::info("Received document message from {$from}");
                $this->handleDocumentMessage($from, $message['document']);
                break;

            case 'audio':
                Log::info("Received audio message from {$from}");
                $this->handleAudioMessage($from, $message['audio']);
                break;

            default:
                Log::info("Received {$message['type']} message from {$from}");
                $this->whatsAppService->sendTextMessage($from, 'عذراً، هذا النوع من الرسائل غير مدعوم حالياً.');
        }
    }

    /**
     * Process Message Status
     */
    private function processMessageStatus($status)
    {
        $messageId = $status['id'];
        $statusType = $status['status'];
        $recipient = $status['recipient_id'];

        Log::info("Message {$messageId} status: {$statusType} for {$recipient}");
    }

    /**
     * Handle Text Message
     */
    private function handleTextMessage($from, $text)
    {
        $text = trim(strtolower($text));

        switch ($text) {
            case 'مرحبا':
            case 'السلام عليكم':
            case 'hello':
                $this->whatsAppService->sendTextMessage($from, '🌟 أهلاً وسهلاً بك!\n\nيمكنك كتابة:\n• "مساعدة" للحصول على المساعدة\n• "معلومات" للحصول على معلومات\n• "خدمات" لمعرفة خدماتنا');
                break;

            case 'مساعدة':
            case 'help':
                $this->whatsAppService->sendTextMessage($from, "📋 قائمة الأوامر المتاحة:\n\n• مرحبا - للترحيب\n• معلومات - معلومات عامة\n• خدمات - خدماتنا\n• وقت - الوقت الحالي\n• مساعدة - هذه القائمة");
                break;

            case 'معلومات':
            case 'info':
                $this->whatsAppService->sendTextMessage($from, "ℹ️ معلومات عن خدمتنا:\n\nنحن نقدم خدمة واتساب بيزنس متطورة باستخدام Laravel وMeta Business API.\n\nيمكنك التواصل معنا في أي وقت!");
                break;

            case 'خدمات':
            case 'services':
                $this->whatsAppService->sendTextMessage($from, "🛎️ خدماتنا:\n\n✅ الرد الآلي السريع\n✅ معالجة الرسائل النصية\n✅ استقبال الصور والملفات\n✅ إشعارات فورية\n✅ دعم فني 24/7");
                break;

            case 'وقت':
            case 'time':
                $currentTime = now()->format('Y-m-d H:i:s');
                $this->whatsAppService->sendTextMessage($from, "⏰ الوقت الحالي:\n{$currentTime}");
                break;

            default:
                $this->whatsAppService->sendTextMessage($from, "🤔 لم أفهم رسالتك.\n\nاكتب 'مساعدة' للحصول على قائمة الأوامر المتاحة.");
        }
    }

    /**
     * Handle Image Message
     */
    private function handleImageMessage($from, $image)
    {
        $caption = isset($image['caption']) ? $image['caption'] : '';
        $this->whatsAppService->sendTextMessage($from, "📸 تم استلام صورتك بنجاح!\n\n" . ($caption ? "التعليق: {$caption}" : ""));
    }

    /**
     * Handle Document Message
     */
    private function handleDocumentMessage($from, $document)
    {
        $filename = isset($document['filename']) ? $document['filename'] : 'ملف غير معروف';
        $this->whatsAppService->sendTextMessage($from, "📄 تم استلام المستند بنجاح!\n\nاسم الملف: {$filename}");
    }

    /**
     * Handle Audio Message
     */
    private function handleAudioMessage($from, $audio)
    {
        $this->whatsAppService->sendTextMessage($from, "🎵 تم استلام الرسالة الصوتية بنجاح!");
    }

    /**
     * Send Message (API for testing)
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'to' => 'required',
            'message' => 'required_if:type,text',
            'type' => 'in:text,template,image'
        ]);

        $to = $request->input('to');
        $type = $request->input('type', 'text');

        try {
            switch ($type) {
                case 'template':
                    $templateName = $request->input('template_name');
                    $parameters = $request->input('parameters', []);
                    $language = $request->input('language', 'ar');
                    $result = $this->whatsAppService->sendTemplateMessage($to, $templateName, $language, $parameters);
                    break;

                case 'image':
                    $imageUrl = $request->input('image_url');
                    $caption = $request->input('caption', '');
                    $result = $this->whatsAppService->sendImageMessage($to, $imageUrl, $caption);
                    break;

                default:
                    $message = $request->input('message');
                    $result = $this->whatsAppService->sendTextMessage($to, $message);
            }

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send Template Message
     */
    public function sendTemplate(Request $request)
    {
        $request->validate([
            'to' => 'required',
            'template_name' => 'required',
            'language' => 'string',
            'parameters' => 'array'
        ]);

        $to = $request->input('to');
        $templateName = $request->input('template_name');
        $language = $request->input('language', 'ar');
        $parameters = $request->input('parameters', []);

        try {
            $result = $this->whatsAppService->sendTemplateMessage($to, $templateName, $language, $parameters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
