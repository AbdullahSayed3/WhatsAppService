<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WhatsAppUser;
use Carbon\Carbon;

class WhatsAppUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // إنشاء مستخدمين تجريبيين
        WhatsAppUser::create([
            'phone_number' => '201234567890',
            'name' => 'أحمد محمد',
            'status' => 'active',
            'current_step' => 'menu',
            'first_message_at' => Carbon::now()->subDays(5),
            'last_message_at' => Carbon::now()->subHours(2),
            'message_count' => 15,
            'session_data' => ['language' => 'ar', 'last_order' => 'pizza']
        ]);

        WhatsAppUser::create([
            'phone_number' => '201987654321',
            'name' => 'فاطمة علي',
            'status' => 'new',
            'current_step' => 'welcome',
            'first_message_at' => Carbon::now()->subHours(1),
            'last_message_at' => Carbon::now()->subMinutes(30),
            'message_count' => 3,
            'session_data' => ['language' => 'ar']
        ]);

        WhatsAppUser::create([
            'phone_number' => '201555123456',
            'name' => 'محمد عبدالله',
            'status' => 'active',
            'current_step' => 'order_details',
            'first_message_at' => Carbon::now()->subDays(2),
            'last_message_at' => Carbon::now()->subMinutes(10),
            'message_count' => 8,
            'session_data' => ['language' => 'ar', 'cart' => ['item1', 'item2']]
        ]);

        WhatsAppUser::create([
            'phone_number' => '201111222333',
            'name' => 'سارة أحمد',
            'status' => 'inactive',
            'current_step' => 'welcome',
            'first_message_at' => Carbon::now()->subWeek(),
            'last_message_at' => Carbon::now()->subDays(3),
            'message_count' => 1,
            'session_data' => ['language' => 'ar']
        ]);

        WhatsAppUser::create([
            'phone_number' => '201444555666',
            'name' => 'خالد محمود',
            'status' => 'new',
            'current_step' => 'welcome',
            'first_message_at' => Carbon::now()->subMinutes(5),
            'last_message_at' => Carbon::now()->subMinutes(5),
            'message_count' => 1,
            'session_data' => []
        ]);
    }
}
