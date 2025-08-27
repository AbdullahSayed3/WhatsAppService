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
        try {
            Log::info('Incoming message data:', $messageData);

            $from = $messageData['from'];
            $messageId = $messageData['id'];
            $timestamp = $messageData['timestamp'];
            $type = $messageData['type'];

            // Find or create user
            $user = WhatsAppUser::findOrCreateUser($from);
            Log::info('User found/created:', ['phone' => $from, 'id' => $user->id]);

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
        } catch (\Exception $e) {
            Log::error('ConversationHandler Error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            throw $e;
        }
    }

    /**
     * Handle new user - Send Welcome Message
     */
    private function handleNewUser($user)
    {
        Log::info("New user detected: {$user->phone_number}");

        // Send welcome message instead of template
        $welcomeMessage = "مرحباً بك في خدمتنا!\n\n";
        $welcomeMessage .= "أهلاً وسهلاً، كيف يمكنني مساعدتك اليوم؟\n\n";
        $welcomeMessage .= "يمكنك كتابة:\n";
        $welcomeMessage .= "• 'help' للمساعدة\n";
        $welcomeMessage .= "• 'services' لمعرفة خدماتنا\n";
        $welcomeMessage .= "• 'menu' للقائمة الرئيسية";

        $result = $this->sendTextMessage($user->phone_number, $welcomeMessage);

        // Update user status
        $user->update([
            'status' => 'active',
            'current_step' => 'conversation'
        ]);

        Log::info("Welcome message sent to: {$user->phone_number}");
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
            'hello' => 'Hello! Welcome to our service.',
            'hi' => 'Hi there! Great to have you here.',
            'hey' => 'Hey! Thanks for reaching out!',
            'مرحبا' => 'أهلاً بك! مرحباً بك في خدمتنا.',
            'السلام عليكم' => 'وعليكم السلام ورحمة الله وبركاته'
        ];

        $response = $responses[$content] ?? 'Hello! How can I help you today?';

        $this->sendTextMessage($user->phone_number, $response);

        // Ask for name
        $this->sendTextMessage($user->phone_number, 'لنتعرف عليك أكثر، ما اسمك؟ / To get to know you better, what is your name?');

        $user->setStep('awaiting_name');
    }

    /**
     * Handle name input
     */
    private function handleNameInput($user, $content)
    {
        // Save name
        $user->update(['name' => $content]);

        $welcomeMessage = "تشرفنا بك {$content}!\n\n";
        $welcomeMessage .= "يمكنك الان:\n";
        $welcomeMessage .= "• كتابة 'help' للمساعدة\n";
        $welcomeMessage .= "• كتابة 'services' لمعرفة خدماتنا\n";
        $welcomeMessage .= "• كتابة 'menu' لرؤية الخيارات\n";
        $welcomeMessage .= "• أو اكتب أي سؤال لديك!";

        $this->sendTextMessage($user->phone_number, $welcomeMessage);

        $user->setStep('conversation');
    }

    /**
     * Handle regular conversation
     */
    private function handleConversation($user, $content, $messageData)
    {
        $userName = $user->name ?? 'عميلنا العزيز';

        switch ($content) {
            case 'help':
            case 'مساعدة':
                $helpMessage = "كيف يمكنني مساعدتك {$userName}؟\n\n";
                $helpMessage .= "• 'services' - لمعرفة خدماتنا\n";
                $helpMessage .= "• 'info' - معلومات عنا\n";
                $helpMessage .= "• 'contact' - طرق التواصل\n";
                $helpMessage .= "• 'time' - الوقت الحالي\n";
                $helpMessage .= "• 'stats' - إحصائياتك";
                $this->sendTextMessage($user->phone_number, $helpMessage);
                break;

            case 'services':
            case 'خدمات':
                $servicesMessage = "خدماتنا المتاحة:\n\n";
                $servicesMessage .= "✅ الرد التلقائي الذكي\n";
                $servicesMessage .= "✅ معالجة الاستفسارات\n";
                $servicesMessage .= "✅ الدعم التقني المجاني\n";
                $servicesMessage .= "✅ المراقبة على مدار الساعة\n\n";
                $servicesMessage .= "اكتب 'details' لمعرفة المزيد!";
                $this->sendTextMessage($user->phone_number, $servicesMessage);
                break;

            case 'info':
            case 'معلومات':
                $infoMessage = "عن خدمتنا:\n\n";
                $infoMessage .= "نوفر خدمة واتساب بزنس متقدمة\n";
                $infoMessage .= "باستخدام أحدث التقنيات والذكاء الاصطناعي\n\n";
                $infoMessage .= "• متاح على مدار الساعة\n";
                $infoMessage .= "• ردود فورية\n";
                $infoMessage .= "• حماية كاملة للبيانات";
                $this->sendTextMessage($user->phone_number, $infoMessage);
                break;

            case 'time':
            case 'وقت':
                $currentTime = now()->format('Y-m-d H:i:s');
                $this->sendTextMessage($user->phone_number, "الوقت الحالي: {$currentTime}");
                break;

            case 'stats':
            case 'إحصائيات':
                $statsMessage = "إحصائياتك {$userName}:\n\n";
                $statsMessage .= "• عدد الرسائل: {$user->message_count}\n";
                $statsMessage .= "• تاريخ التسجيل: " . $user->created_at->format('Y-m-d') . "\n";
                $statsMessage .= "• آخر نشاط: " . $user->last_message_at->diffForHumans() . "\n";
                $statsMessage .= "• الحالة: " . ($user->status === 'active' ? 'نشط' : $user->status);
                $this->sendTextMessage($user->phone_number, $statsMessage);
                break;

            case 'menu':
            case 'قائمة':
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
        $menuMessage = "القائمة الرئيسية:\n\n";
        $menuMessage .= "1️⃣ خدماتنا\n";
        $menuMessage .= "2️⃣ معلومات عنا\n";
        $menuMessage .= "3️⃣ طرق التواصل\n";
        $menuMessage .= "4️⃣ مساعدة\n";
        $menuMessage .= "5️⃣ إحصائياتك\n\n";
        $menuMessage .= "اكتب رقم الخيار أو الاسم:";

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
            case 'services':
            case 'خدمات':
                $this->handleConversation($user, 'services', []);
                break;
            case '2':
            case 'info':
            case 'معلومات':
                $this->handleConversation($user, 'info', []);
                break;
            case '4':
            case 'help':
            case 'مساعدة':
                $this->handleConversation($user, 'help', []);
                break;
            case '5':
            case 'stats':
            case 'إحصائيات':
                $this->handleConversation($user, 'stats', []);
                break;
            default:
                $this->sendTextMessage($user->phone_number, "خيار غير صحيح. اكتب 'menu' لرؤية الخيارات مرة أخرى.");
        }

        $user->setStep('conversation');
    }

    /**
     * Smart reply to unknown messages
     */
    private function handleSmartReply($user, $content, $messageData)
    {
        $userName = $user->name ?? 'عميلنا العزيز';

        // Smart replies based on content
        if (str_contains($content, 'شكر') || str_contains($content, 'thank')) {
            $reply = "العفو {$userName}! دائماً في خدمتك.";
        } elseif (str_contains($content, 'سعر') || str_contains($content, 'price') || str_contains($content, 'cost')) {
            $reply = "لمعلومات الأسعار، يرجى كتابة 'services' أو التواصل مع فريق المبيعات.";
        } elseif (str_contains($content, 'متى') || str_contains($content, 'when') || str_contains($content, 'time')) {
            $reply = "نحن متاحون على مدار الساعة. اكتب 'time' لمعرفة الوقت الحالي.";
        } elseif (str_contains($content, '؟') || str_contains($content, '?')) {
            $reply = "سؤال رائع {$userName}! اكتب 'help' للحصول على إجابات شاملة.";
        } else {
            $replies = [
                "شكراً لرسالتك {$userName}!",
                "فهمت، كيف يمكنني مساعدتك أكثر؟",
                "أهلاً {$userName}، اكتب 'help' لترى ما يمكنني فعله لك!",
                "أقدر تواصلك، هل تحتاج مساعدة في شيء محدد؟"
            ];
            $reply = $replies[array_rand($replies)];
        }

        $this->sendTextMessage($user->phone_number, $reply);
    }

    /**
     * Extract message content
     */
    private function extractMessageContent($messageData)
    {
        switch ($messageData['type']) {
            case 'text':
                return $messageData['text']['body'];
            case 'image':
                return $messageData['image']['caption'] ?? '[Image]';
            case 'document':
                return $messageData['document']['filename'] ?? '[Document]';
            case 'audio':
                return '[Audio message]';
            default:
                return '[Unsupported message]';
        }
    }

    /**
     * Send text message with tracking
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

// namespace App\Services;

// use App\Models\WhatsAppUser;
// use App\Models\WhatsAppMessage;
// use App\Services\WhatsAppService;
// use Illuminate\Support\Facades\Log;

// class ConversationHandler
// {
//     private $whatsAppService;

//     public function __construct(WhatsAppService $whatsAppService)
//     {
//         $this->whatsAppService = $whatsAppService;
//     }

//     /**
//      * Handle incoming message
//      */
//     public function handleIncomingMessage($messageData, $valueData)
//     {
//         $from = $messageData['from'];
//         $messageId = $messageData['id'];
//         $timestamp = $messageData['timestamp'];
//         $type = $messageData['type'];

//         // Find or create user
//         $user = WhatsAppUser::findOrCreateUser($from);

//         // Log inbound message
//         $content = $this->extractMessageContent($messageData);
//         WhatsAppMessage::logInbound($messageId, $from, $type, $content, $messageData);

//         // Mark as read
//         $this->whatsAppService->markAsRead($messageId);

//         // Check if new user
//         if ($user->isNew()) {
//             $this->handleNewUser($user);
//         } else {
//             $this->processUserMessage($user, $messageData);
//         }

//         // Update last activity
//         $user->updateLastActivity();
//     }

//     /**
//      * Handle new user - Send Template
//      */
//     private function handleNewUser($user)
//     {
//         Log::info("New user detected: {$user->phone_number}");

//         // Send welcome template (should be approved from Meta)
//         $templateResult = $this->whatsAppService->sendTemplateMessage(
//             $user->phone_number,
//             'hello_world', // This should be the name of the approved template
//             'en'
//         );

//         if (isset($templateResult['messages'][0]['id'])) {
//             $messageId = $templateResult['messages'][0]['id'];
//             WhatsAppMessage::logOutbound($messageId, $user->phone_number, 'template', 'hello_world');
//         }

//         // Update user status
//         $user->update([
//             'status' => 'active',
//             'current_step' => 'awaiting_response'
//         ]);

//         Log::info("Welcome template sent to: {$user->phone_number}");
//     }

//     /**
//      * Handle user message based on current step
//      */
//     private function processUserMessage($user, $messageData)
//     {
//         $content = strtolower(trim($this->extractMessageContent($messageData)));
//         $currentStep = $user->current_step;

//         Log::info("Processing message from {$user->phone_number}, step: {$currentStep}, content: {$content}");

//         switch ($currentStep) {
//             case 'awaiting_response':
//                 $this->handleFirstResponse($user, $content);
//                 break;

//             case 'conversation':
//                 $this->handleConversation($user, $content, $messageData);
//                 break;

//             case 'awaiting_name':
//                 $this->handleNameInput($user, $content);
//                 break;

//             case 'menu':
//                 $this->handleMenuSelection($user, $content);
//                 break;

//             default:
//                 $this->handleConversation($user, $content, $messageData);
//         }
//     }

//     /**
//      * Handle first response after template
//      */
//     private function handleFirstResponse($user, $content)
//     {
//         $responses = [
//             'hello' => 'Hello! Welcome to our service.',
//             'hi' => 'Hi there! Great to have you here.',
//             'hey' => 'Hey! Thanks for reaching out!'
//         ];

//         $response = $responses[$content] ?? 'Hello! How can I help you today?';

//         $this->sendTextMessage($user->phone_number, $response);

//         // Ask for name
//         $this->sendTextMessage($user->phone_number, 'To get to know you better, what is your name?');

//         $user->setStep('awaiting_name');
//     }

//     /**
//      * Handle name input
//      */
//     private function handleNameInput($user, $content)
//     {
//         // Save name
//         $user->update(['name' => $content]);

//         $welcomeMessage = "Nice to meet you, {$content}!\n\n";
//         $welcomeMessage .= "You can now:\n";
//         $welcomeMessage .= "• Type 'help' to get help\n";
//         $welcomeMessage .= "• Type 'services' to know our services\n";
//         $welcomeMessage .= "• Type 'menu' to see options\n";
//         $welcomeMessage .= "• Or write any question you have!";

//         $this->sendTextMessage($user->phone_number, $welcomeMessage);

//         $user->setStep('conversation');
//     }

//     /**
//      * Handle regular conversation
//      */
//     private function handleConversation($user, $content, $messageData)
//     {
//         $userName = $user->name ?? 'dear customer';

//         switch ($content) {
//             case 'help':
//                 $helpMessage = "How can I help you {$userName}?\n\n";
//                 $helpMessage .= "• 'services' - to know our services\n";
//                 $helpMessage .= "• 'info' - information about us\n";
//                 $helpMessage .= "• 'contact' - contact methods\n";
//                 $helpMessage .= "• 'time' - current time\n";
//                 $helpMessage .= "• 'stats' - your statistics";
//                 $this->sendTextMessage($user->phone_number, $helpMessage);
//                 break;

//             case 'services':
//                 $servicesMessage = "Our available services:\n\n";
//                 $servicesMessage .= "✅ Smart Auto Reply\n";
//                 $servicesMessage .= "✅ Query Processing\n";
//                 $servicesMessage .= "✅ Free Technical Support\n";
//                 $servicesMessage .= "✅ 24/7 Monitoring\n\n";
//                 $servicesMessage .= "Type 'details' to know more!";
//                 $this->sendTextMessage($user->phone_number, $servicesMessage);
//                 break;

//             case 'info':
//                 $infoMessage = "About our service:\n\n";
//                 $infoMessage .= "We provide advanced WhatsApp Business service\n";
//                 $infoMessage .= "using latest technologies and AI\n\n";
//                 $infoMessage .= "• Available 24/7\n";
//                 $infoMessage .= "• Instant replies\n";
//                 $infoMessage .= "• Complete data protection";
//                 $this->sendTextMessage($user->phone_number, $infoMessage);
//                 break;

//             case 'time':
//                 $currentTime = now()->format('Y-m-d H:i:s');
//                 $this->sendTextMessage($user->phone_number, "Current time: {$currentTime}");
//                 break;

//             case 'stats':
//                 $statsMessage = "Your statistics {$userName}:\n\n";
//                 $statsMessage .= "• Message count: {$user->message_count}\n";
//                 $statsMessage .= "• Registration date: " . $user->created_at->format('Y-m-d') . "\n";
//                 $statsMessage .= "• Last active: " . $user->last_message_at->diffForHumans() . "\n";
//                 $statsMessage .= "• Status: " . ($user->status === 'active' ? 'Active' : $user->status);
//                 $this->sendTextMessage($user->phone_number, $statsMessage);
//                 break;

//             case 'menu':
//                 $this->showMainMenu($user);
//                 break;

//             default:
//                 // Smart reply to unknown messages
//                 $this->handleSmartReply($user, $content, $messageData);
//         }
//     }

//     /**
//      * Show main menu
//      */
//     private function showMainMenu($user)
//     {
//         $menuMessage = "Main Menu:\n\n";
//         $menuMessage .= "1️⃣ Our Services\n";
//         $menuMessage .= "2️⃣ About Us\n";
//         $menuMessage .= "3️⃣ Contact Methods\n";
//         $menuMessage .= "4️⃣ Help\n";
//         $menuMessage .= "5️⃣ Your Statistics\n\n";
//         $menuMessage .= "Type option number or name:";

//         $this->sendTextMessage($user->phone_number, $menuMessage);
//         $user->setStep('menu');
//     }

//     /**
//      * Handle menu selection
//      */
//     private function handleMenuSelection($user, $content)
//     {
//         switch ($content) {
//             case '1':
//             case 'services':
//                 $this->handleConversation($user, 'services', []);
//                 break;
//             case '2':
//             case 'info':
//                 $this->handleConversation($user, 'info', []);
//                 break;
//             case '4':
//             case 'help':
//                 $this->handleConversation($user, 'help', []);
//                 break;
//             case '5':
//             case 'stats':
//                 $this->handleConversation($user, 'stats', []);
//                 break;
//             default:
//                 $this->sendTextMessage($user->phone_number, "Invalid option. Type 'menu' to see options again.");
//         }

//         $user->setStep('conversation');
//     }

//     /**
//      * Smart reply to unknown messages
//      */
//     private function handleSmartReply($user, $content, $messageData)
//     {
//         $userName = $user->name ?? 'dear customer';

//         // Smart replies based on content
//         if (str_contains($content, 'thank')) {
//             $reply = "You're welcome {$userName}! Always at your service.";
//         } elseif (str_contains($content, 'price') || str_contains($content, 'cost')) {
//             $reply = "For pricing information, please type 'services' or contact our sales team.";
//         } elseif (str_contains($content, 'when') || str_contains($content, 'time')) {
//             $reply = "We're available 24/7. Type 'time' to see current time.";
//         } elseif (str_contains($content, '?')) {
//             $reply = "Great question {$userName}! Type 'help' to get comprehensive answers.";
//         } else {
//             $replies = [
//                 "Thanks for your message {$userName}!",
//                 "I understand, how can I help you more?",
//                 "Welcome {$userName}, type 'help' to see what I can do for you!",
//                 "I appreciate your contact, do you need help with something specific?"
//             ];
//             $reply = $replies[array_rand($replies)];
//         }

//         $this->sendTextMessage($user->phone_number, $reply);
//     }

//     /**
//      * Extract message content
//      */
//     private function extractMessageContent($messageData)
//     {
//         switch ($messageData['type']) {
//             case 'text':
//                 return $messageData['text']['body'];
//             case 'image':
//                 return $messageData['image']['caption'] ?? '[Image]';
//             case 'document':
//                 return $messageData['document']['filename'] ?? '[Document]';
//             case 'audio':
//                 return '[Audio message]';
//             default:
//                 return '[Unsupported message]';
//         }
//     }

//     /**
//      * Send text message with tracking
//      */
//     private function sendTextMessage($phoneNumber, $message)
//     {
//         $result = $this->whatsAppService->sendTextMessage($phoneNumber, $message);

//         if (isset($result['messages'][0]['id'])) {
//             $messageId = $result['messages'][0]['id'];
//             WhatsAppMessage::logOutbound($messageId, $phoneNumber, 'text', $message);
//         }

//         return $result;
//     }
// }
