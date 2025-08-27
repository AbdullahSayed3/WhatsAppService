<?php
// app/Http/Controllers/WhatsAppController.php

namespace App\Http\Controllers;

use App\Services\WhatsAppService;
use App\Services\ConversationHandler;
use App\Models\WhatsAppMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    private $whatsAppService;
    private $conversationHandler;

    public function __construct(WhatsAppService $whatsAppService, ConversationHandler $conversationHandler)
    {
        $this->whatsAppService = $whatsAppService;
        $this->conversationHandler = $conversationHandler;
    }

    /**
     * Webhook verification للتأكد من صحة الـ webhook
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
     * استقبال الرسائل من WhatsApp
     */
    public function receiveMessage(Request $request)
    {
        try {
            $body = $request->all();
            Log::info('WhatsApp Webhook received: ' . json_encode($body));

            // التحقق من وجود رسائل
            if (isset($body['entry'][0]['changes'][0]['value']['messages'])) {
                foreach ($body['entry'][0]['changes'][0]['value']['messages'] as $message) {
                    $this->processIncomingMessage($message, $body['entry'][0]['changes'][0]['value']);
                }
            }

            // التحقق من تحديثات حالة الرسائل
            if (isset($body['entry'][0]['changes'][0]['value']['statuses'])) {
                foreach ($body['entry'][0]['changes'][0]['value']['statuses'] as $status) {
                    $this->processMessageStatus($status);
                }
            }

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('Error processing webhook: ' . $e->getMessage());
            return response('Error', 500);
        }
    }

    /**
     * معالجة الرسائل الواردة - استخدام ConversationHandler
     */
    private function processIncomingMessage($message, $value)
    {
        try {
            Log::info('=== DEBUG: Processing incoming message ===');
            Log::info('Message data: ' . json_encode($message));
            Log::info('Value data: ' . json_encode($value));

            // Use ConversationHandler
            $this->conversationHandler->handleIncomingMessage($message, $value);

            Log::info('=== DEBUG: Message processed successfully ===');
        } catch (\Exception $e) {
            Log::error('=== ERROR in processIncomingMessage ===');
            Log::error('Error: ' . $e->getMessage());
            Log::error('File: ' . $e->getFile());
            Log::error('Line: ' . $e->getLine());
            Log::error('Stack trace: ' . $e->getTraceAsString());

            // Send error reply
            if (isset($message['from'])) {
                $this->whatsAppService->sendTextMessage(
                    $message['from'],
                    'حدث خطأ في معالجة رسالتك. يرجى المحاولة لاحقاً.'
                );
            }
        }
    }

    /**
     * معالجة حالات الرسائل (تم الإرسال، تم التسليم، تم القراءة)
     */
    private function processMessageStatus($status)
    {
        try {
            $messageId = $status['id'];
            $statusType = $status['status'];
            $recipient = $status['recipient_id'];
            $timestamp = isset($status['timestamp']) ? $status['timestamp'] : null;

            Log::info("Message {$messageId} status: {$statusType} for {$recipient}");

            // تحديث حالة الرسالة في قاعدة البيانات
            $message = WhatsAppMessage::where('message_id', $messageId)->first();
            if ($message) {
                $message->updateStatus($statusType, $timestamp);
                Log::info("Updated message {$messageId} status to {$statusType}");
            } else {
                Log::warning("Message {$messageId} not found in database");
            }
        } catch (\Exception $e) {
            Log::error('Error processing message status: ' . $e->getMessage());
        }
    }

    /**
     * إرسال رسالة (API للاختبار)
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'message' => 'required_if:type,text|string',
            'type' => 'in:text,template,image,document',
            'template_name' => 'required_if:type,template|string',
            'image_url' => 'required_if:type,image|url',
            'document_url' => 'required_if:type,document|url',
            'parameters' => 'array',
            'parameters.*' => 'string',
        ]);

        $to = $request->input('to');
        $type = $request->input('type', 'text');

        try {
            $result = null;

            switch ($type) {
                case 'template':
                    $templateName = $request->input('template_name');
                    $parameters = $request->input('parameters', []);
                    $language = $request->input('language', 'ar');

                    Log::info('Sending template message', [
                        'to' => $to,
                        'template_name' => $templateName,
                        'language' => $language,
                        'parameters' => $parameters
                    ]);

                    $result = $this->whatsAppService->sendTemplateMessage($to, $templateName, $language, $parameters);
                    break;

                case 'image':
                    $imageUrl = $request->input('image_url');
                    $caption = $request->input('caption', '');
                    Log::info('Sending image message', ['to' => $to, 'image_url' => $imageUrl]);
                    $result = $this->whatsAppService->sendImageMessage($to, $imageUrl, $caption);
                    break;

                case 'document':
                    $documentUrl = $request->input('document_url');
                    $filename = $request->input('filename', '');
                    $caption = $request->input('caption', '');
                    Log::info('Sending document message', ['to' => $to, 'document_url' => $documentUrl]);
                    $result = $this->whatsAppService->sendDocumentMessage($to, $documentUrl, $filename, $caption);
                    break;

                case 'text':
                default:
                    $message = $request->input('message');
                    Log::info('Sending text message', ['to' => $to, 'message' => $message]);
                    $result = $this->whatsAppService->sendTextMessage($to, $message);
                    break;
            }

            // التحقق من وجود message ID في الرد
            if (!isset($result['messages'][0]['id'])) {
                Log::error('No message ID returned from WhatsApp API', ['result' => $result]);
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to send message: No message ID returned',
                    'data' => $result
                ], 500);
            }

            // تسجيل الرسالة في قاعدة البيانات
            $messageId = $result['messages'][0]['id'];
            $content = $type === 'text' ? $request->input('message') : "[$type message]";
            WhatsAppMessage::logOutbound($messageId, $to, $type, $content);

            Log::info('Message sent successfully', ['message_id' => $messageId, 'to' => $to]);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending message: ' . $e->getMessage(), ['request' => $request->all()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send message'
            ], 500);
        }
    }

    /**
     * إرسال Template Message
     */
    public function sendTemplate(Request $request)
    {
        $request->validate([
            'to' => 'required|string',
            'template_name' => 'required|string',
            'language' => 'string',
            'parameters' => 'array',
            'parameters.*' => 'string',
        ]);

        $to = $request->input('to');
        $templateName = $request->input('template_name');
        $language = $request->input('language', 'ar');
        $parameters = $request->input('parameters', []);

        try {
            Log::info('Sending template message', [
                'to' => $to,
                'template_name' => $templateName,
                'language' => $language,
                'parameters' => $parameters
            ]);

            $result = $this->whatsAppService->sendTemplateMessage($to, $templateName, $language, $parameters);

            // تسجيل الرسالة في قاعدة البيانات
            if (isset($result['messages'][0]['id'])) {
                $messageId = $result['messages'][0]['id'];
                WhatsAppMessage::logOutbound($messageId, $to, 'template', $templateName);
                Log::info('Template message logged', ['message_id' => $messageId, 'to' => $to]);
            } else {
                Log::error('No message ID returned from WhatsApp API', ['result' => $result]);
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to send template: No message ID returned',
                    'data' => $result
                ], 500);
            }

            return response()->json([
                'success' => true,
                'message' => 'Template sent successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            Log::error('Error sending template: ' . $e->getMessage(), ['request' => $request->all()]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to send template'
            ], 500);
        }
    }

    /**
     * الحصول على إحصائيات سريعة
     */
    public function getStats()
    {
        try {
            $stats = [
                'total_messages' => WhatsAppMessage::count(),
                'messages_today' => WhatsAppMessage::whereDate('created_at', today())->count(),
            ];

            Log::info('Stats retrieved', ['stats' => $stats]);

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting stats: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to retrieve stats'
            ], 500);
        }
    }

    /**
     * صفحة الاختبار البسيطة
     */
    public function testPage()
    {
        return response()->json([
            'service' => 'WhatsApp Business API',
            'status' => 'Active',
            'version' => '1.0',
            'timestamp' => now()->toISOString(),
            'endpoints' => [
                'webhook' => '/api/whatsapp/webhook',
                'send_message' => '/api/whatsapp/send',
                'send_template' => '/api/whatsapp/template',
                'stats' => '/api/whatsapp/stats'
            ]
        ]);
    }
}
