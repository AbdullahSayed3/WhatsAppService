<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WhatsAppMessage;
use Carbon\Carbon;

class WhatsAppMessageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // رسائل للمستخدم الأول
        WhatsAppMessage::create([
            'message_id' => 'msg_001',
            'phone_number' => '201234567890',
            'content' => 'مرحباً',
            'direction' => 'inbound',
            'type' => 'text',
            'status' => 'delivered',
            'created_at' => Carbon::now()->subDays(5)
        ]);

        WhatsAppMessage::create([
            'message_id' => 'msg_002',
            'phone_number' => '201234567890',
            'content' => 'أهلاً بك! كيف يمكنني مساعدتك؟',
            'direction' => 'outbound',
            'type' => 'text',
            'status' => 'sent',
            'created_at' => Carbon::now()->subDays(5)->addMinutes(1)
        ]);

        WhatsAppMessage::create([
            'message_id' => 'msg_003',
            'phone_number' => '201234567890',
            'content' => 'أريد طلب طعام',
            'direction' => 'inbound',
            'type' => 'text',
            'status' => 'delivered',
            'created_at' => Carbon::now()->subHours(3)
        ]);

        WhatsAppMessage::create([
            'message_id' => 'msg_004',
            'phone_number' => '201234567890',
            'content' => 'ممتاز! ما نوع الطعام الذي تريده؟',
            'direction' => 'outbound',
            'type' => 'text',
            'status' => 'delivered',
            'created_at' => Carbon::now()->subHours(3)->addMinutes(1)
        ]);

        // رسائل للمستخدم الثاني
        WhatsAppMessage::create([
            'message_id' => 'msg_005',
            'phone_number' => '201987654321',
            'content' => 'السلام عليكم',
            'direction' => 'inbound',
            'type' => 'text',
            'status' => 'delivered',
            'created_at' => Carbon::now()->subHours(1)
        ]);

        WhatsAppMessage::create([
            'message_id' => 'msg_006',
            'phone_number' => '201987654321',
            'content' => 'وعليكم السلام ورحمة الله وبركاته',
            'direction' => 'outbound',
            'type' => 'text',
            'status' => 'sent',
            'created_at' => Carbon::now()->subHours(1)->addSeconds(30)
        ]);

        // رسائل للمستخدم الثالث
        WhatsAppMessage::create([
            'message_id' => 'msg_007',
            'phone_number' => '201555123456',
            'content' => 'مساء الخير',
            'direction' => 'inbound',
            'type' => 'text',
            'status' => 'delivered',
            'created_at' => Carbon::now()->subMinutes(30)
        ]);

        WhatsAppMessage::create([
            'message_id' => 'msg_008',
            'phone_number' => '201555123456',
            'content' => 'مساء النور! كيف حالك؟',
            'direction' => 'outbound',
            'type' => 'text',
            'status' => 'sent',
            'created_at' => Carbon::now()->subMinutes(29)
        ]);

        WhatsAppMessage::create([
            'message_id' => 'msg_009',
            'phone_number' => '201555123456',
            'content' => 'الحمد لله، أريد الاستفسار عن الأسعار',
            'direction' => 'inbound',
            'type' => 'text',
            'status' => 'delivered',
            'created_at' => Carbon::now()->subMinutes(15)
        ]);

        WhatsAppMessage::create([
            'message_id' => 'msg_010',
            'phone_number' => '201555123456',
            'content' => 'بالطبع! سأرسل لك قائمة الأسعار',
            'direction' => 'outbound',
            'type' => 'text',
            'status' => 'delivered',
            'created_at' => Carbon::now()->subMinutes(14)
        ]);
    }
}
