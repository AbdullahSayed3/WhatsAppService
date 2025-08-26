<?php
// app/Services/ConversationHandler.php

namespace App\Services;

use App\Models\WhatsAppUser;
use App\Models\WhatsAppMessage;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Log;

class ConversationHandler
{
    private $whatsAppService;

    public function __construct(WhatsAppService $whatsAppService)
    {
        $this->whatsAppService = $whatsAppService;
    }

    /**
     * Handle incoming message
     */
    public function handleIncomingMessage($messageData, $valueData)
    {
        $from = $messageData['from'];
        $messageId = $messageData['id'];
        $timestamp = $messageData['timestamp'];
        $type = $messageData['type'];

        // Find or create user
        $user = WhatsAppUser::findOrCreateUser($from);

        // Log inbound message
        $content = $this->extractMessageContent($messageData);
        WhatsAppMessage::logInbound($messageId, $from, $type, $content, $messageData);

        // Mark as read
        $this->whatsAppService->markAsRead($messageId);

        // Check if new user
        if ($user->isNew()) {
            $this->handleNewUser($user);
        } else {
            $this->processUserMessage($user, $messageData);
        }

        // Update last activity
        $user->updateLastActivity();
    }

    /**
     * Handle new user - Send Template
     */
    private function handleNewUser($user)
    {
        Log::info("New user detected: {$user->phone_number}");

        // Send welcome template (should be approved from meta)
        $templateResult = $this->whatsAppService->sendTemplateMessage(
            $user->phone_number,
            'hello_world', // This should be the name of the approved template
            'ar'
        );

        if (isset($templateResult['messages'][0]['id'])) {
            $messageId = $templateResult['messages'][0]['id'];
            WhatsAppMessage::logOutbound($messageId, $user->phone_number, 'template', 'hello_world');
        }

        // Update user status
        $user->update([
            'status' => 'active',
            'current_step' => 'awaiting_response'
        ]);

        Log::info("Welcome template sent to: {$user->phone_number}");
    }

    /**
     * Handle user message based on current step
     */
    private function processUserMessage($user, $messageData)
    {
        $content = strtolower(trim($this->extractMessageContent($messageData)));
        $currentStep = $user->current_step;

        Log::info("Processing message from {$user->phone_number}, step: {$currentStep}, content: {$content}");

        switch ($currentStep) {
            case 'awaiting_response':
                $this->handleFirstResponse($user, $content);
                break;

            case 'conversation':
                $this->handleConversation($user, $content, $messageData);
                break;

            case 'awaiting_name':
                $this->handleNameInput($user, $content);
                break;

            case 'menu':
                $this->handleMenuSelection($user, $content);
                break;

            default:
                $this->handleConversation($user, $content, $messageData);
        }
    }

    /**
     * Handle first response after template
     */
    private function handleFirstResponse($user, $content)
    {
        $responses = [
            'مرحبا' => '🌟 أهلاً بك! سعداء بتواصلك معنا.',
            'hello' => '🌟 Hello! Welcome to our service.',
            'hi' => '🌟 Hi there! Great to have you here.'
        ];

        $response = $responses[$content] ?? '👋 مرحباً بك! كيف يمكنني مساعدتك اليوم؟';

        $this->sendTextMessage($user->phone_number, $response);

        // Ask for name
        $this->sendTextMessage($user->phone_number, 'لنتعرف عليك أكثر، ما اسمك؟');

        $user->setStep('awaiting_name');
    }

    /**
     * Handle name input
     */
    private function handleNameInput($user, $content)
    {
        // Save name
        $user->update(['name' => $content]);

        $welcomeMessage = "سعيد بلقائك يا {$content}! 😊\n\n";
        $welcomeMessage .= "يمكنك الآن:\n";
        $welcomeMessage .= "• كتابة 'مساعدة' للحصول على المساعدة\n";
        $welcomeMessage .= "• كتابة 'خدمات' لمعرفة خدماتنا\n";
        $welcomeMessage .= "• كتابة 'قائمة' لعرض الخيارات\n";
        $welcomeMessage .= "• أو اكتب أي سؤال تريد!";

        $this->sendTextMessage($user->phone_number, $welcomeMessage);

        $user->setStep('conversation');
    }

    /**
     * Handle regular conversation
     */
    private function handleConversation($user, $content, $messageData)
    {
        $userName = $user->name ?? 'عزيزي العميل';

        switch ($content) {
            case 'مساعدة':
            case 'help':
                $helpMessage = "📋 كيف يمكنني مساعدتك يا {$userName}؟\n\n";
                $helpMessage .= "• 'خدمات' - لمعرفة خدماتنا\n";
                $helpMessage .= "• 'معلومات' - معلومات عنا\n";
                $helpMessage .= "• 'تواصل' - طرق التواصل\n";
                $helpMessage .= "• 'وقت' - الوقت الحالي\n";
                $helpMessage .= "• 'إحصائيات' - إحصائياتك";
                $this->sendTextMessage($user->phone_number, $helpMessage);
                break;

            case 'خدمات':
            case 'services':
                $servicesMessage = "🛎️ خدماتنا المتاحة:\n\n";
                $servicesMessage .= "✅ الرد الآلي الذكي\n";
                $servicesMessage .= "✅ معالجة الاستفسارات\n";
                $servicesMessage .= "✅ دعم فني مجاني\n";
                $servicesMessage .= "✅ متابعة على مدار الساعة\n\n";
                $servicesMessage .= "اكتب 'تفاصيل' لمعرفة المزيد!";
                $this->sendTextMessage($user->phone_number, $servicesMessage);
                break;

            case 'معلومات':
            case 'info':
                $infoMessage = "ℹ️ معلومات عن خدمتنا:\n\n";
                $infoMessage .= "نحن نقدم خدمة واتساب بيزنس متطورة\n";
                $infoMessage .= "باستخدام أحدث التقنيات والذكاء الاصطناعي\n\n";
                $infoMessage .= "🕒 متاحون 24/7\n";
                $infoMessage .= "⚡ ردود فورية\n";
                $infoMessage .= "🔒 حماية كاملة لبياناتك";
                $this->sendTextMessage($user->phone_number, $infoMessage);
                break;

            case 'وقت':
            case 'time':
                $currentTime = now()->format('Y-m-d H:i:s');
                $this->sendTextMessage($user->phone_number, "⏰ الوقت الحالي: {$currentTime}");
                break;

            case 'إحصائيات':
            case 'stats':
                $statsMessage = "📊 إحصائياتك يا {$userName}:\n\n";
                $statsMessage .= "• عدد الرسائل: {$user->message_count}\n";
                $statsMessage .= "• تاريخ التسجيل: " . $user->created_at->format('Y-m-d') . "\n";
                $statsMessage .= "• آخر نشاط: " . $user->last_message_at->diffForHumans() . "\n";
                $statsMessage .= "• الحالة: " . ($user->status === 'active' ? 'نشط' : $user->status);
                $this->sendTextMessage($user->phone_number, $statsMessage);
                break;

            case 'قائمة':
            case 'menu':
                $this->showMainMenu($user);
                break;

            default:
                // Smart reply to unknown messages
                $this->handleSmartReply($user, $content, $messageData);
        }
    }

    /**
     * Show main menu
     */
    private function showMainMenu($user)
    {
        $menuMessage = "📋 القائمة الرئيسية:\n\n";
        $menuMessage .= "1️⃣ خدماتنا\n";
        $menuMessage .= "2️⃣ معلومات عنا\n";
        $menuMessage .= "3️⃣ طرق التواصل\n";
        $menuMessage .= "4️⃣ المساعدة\n";
        $menuMessage .= "5️⃣ إحصائياتك\n\n";
        $menuMessage .= "اكتب رقم الخيار أو اسمه:";

        $this->sendTextMessage($user->phone_number, $menuMessage);
        $user->setStep('menu');
    }

    /**
     * Handle menu selection
     */
    private function handleMenuSelection($user, $content)
    {
        switch ($content) {
            case '1':
            case 'خدماتنا':
                $this->handleConversation($user, 'خدمات', []);
                break;
            case '2':
            case 'معلومات':
                $this->handleConversation($user, 'معلومات', []);
                break;
            case '4':
            case 'مساعدة':
                $this->handleConversation($user, 'مساعدة', []);
                break;
            case '5':
            case 'إحصائياتك':
                $this->handleConversation($user, 'إحصائيات', []);
                break;
            default:
                $this->sendTextMessage($user->phone_number, "❌ خيار غير صحيح. اكتب 'قائمة' لعرض الخيارات مرة أخرى.");
        }

        $user->setStep('conversation');
    }

    /**
     * Smart reply to unknown messages
     */
    private function handleSmartReply($user, $content, $messageData)
    {
        $userName = $user->name ?? 'عزيزي';

        // Smart replies based on content
        if (str_contains($content, 'شكر')) {
            $reply = "العفو يا {$userName}! 😊 دائماً في خدمتك.";
        } elseif (str_contains($content, 'سعر') || str_contains($content, 'تكلفة')) {
            $reply = "💰 لمعرفة الأسعار والتكاليف، يرجى كتابة 'خدمات' أو التواصل مع فريق المبيعات.";
        } elseif (str_contains($content, 'متى') || str_contains($content, 'وقت')) {
            $reply = "⏰ نحن متاحون 24/7. اكتب 'وقت' لمعرفة الوقت الحالي.";
        } elseif (str_contains($content, '؟')) {
            $reply = "🤔 سؤال ممتاز يا {$userName}! اكتب 'مساعدة' للحصول على إجابات شاملة.";
        } else {
            $replies = [
                "شكراً لرسالتك يا {$userName}! 🙏",
                "فهمت كلامك، كيف يمكنني مساعدتك أكثر؟ 🤝",
                "مرحباً {$userName}، اكتب 'مساعدة' لمعرفة ما يمكنني فعله لك! 💡",
                "أقدر تواصلك، هل تحتاج مساعدة في شيء محدد؟ 🎯"
            ];
            $reply = $replies[array_rand($replies)];
        }

        $this->sendTextMessage($user->phone_number, $reply);
    }

    /**
     * extract message content
     */
    private function extractMessageContent($messageData)
    {
        switch ($messageData['type']) {
            case 'text':
                return $messageData['text']['body'];
            case 'image':
                return $messageData['image']['caption'] ?? '[صورة]';
            case 'document':
                return $messageData['document']['filename'] ?? '[مستند]';
            case 'audio':
                return '[رسالة صوتية]';
            default:
                return '[رسالة غير مدعومة]';
        }
    }

    /**
     * send text message with tracking
     */
    private function sendTextMessage($phoneNumber, $message)
    {
        $result = $this->whatsAppService->sendTextMessage($phoneNumber, $message);

        if (isset($result['messages'][0]['id'])) {
            $messageId = $result['messages'][0]['id'];
            WhatsAppMessage::logOutbound($messageId, $phoneNumber, 'text', $message);
        }

        return $result;
    }
}
