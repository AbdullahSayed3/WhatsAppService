<?php
// app/Console/Commands/WhatsAppStats.php

namespace App\Console\Commands;

use App\Models\WhatsAppUser;
use App\Models\WhatsAppMessage;
use Illuminate\Console\Command;

class WhatsAppStats extends Command
{
    protected $signature = 'whatsapp:stats {--user= : Show stats for specific user phone number}';
    protected $description = 'Show WhatsApp usage statistics';

    public function handle()
    {
        $userPhone = $this->option('user');

        if ($userPhone) {
            $this->showUserStats($userPhone);
        } else {
            $this->showOverallStats();
        }
    }

    private function showOverallStats()
    {
        $this->info('📊 WhatsApp Service Statistics');
        $this->line('================================');

        $totalUsers = WhatsAppUser::count();
        $activeUsers = WhatsAppUser::where('status', 'active')->count();
        $newUsers = WhatsAppUser::where('status', 'new')->count();
        $totalMessages = WhatsAppMessage::count();
        $inboundMessages = WhatsAppMessage::where('direction', 'inbound')->count();
        $outboundMessages = WhatsAppMessage::where('direction', 'outbound')->count();
        $messagesToday = WhatsAppMessage::whereDate('created_at', today())->count();

        $this->table(['Metric', 'Count'], [
            ['Total Users', $totalUsers],
            ['Active Users', $activeUsers],
            ['New Users', $newUsers],
            ['Total Messages', $totalMessages],
            ['Messages Today', $messagesToday],
            ['Inbound Messages', $inboundMessages],
            ['Outbound Messages', $outboundMessages]
        ]);

        // Most active users
        $this->line('');
        $this->info('🔥 Most Active Users:');

        $activeUsersList = WhatsAppUser::orderBy('message_count', 'desc')
            ->limit(5)
            ->get(['phone_number', 'name', 'message_count', 'last_message_at']);

        if ($activeUsersList->count() > 0) {
            $tableData = $activeUsersList->map(function ($user) {
                return [
                    $user->phone_number,
                    $user->name ?? 'Unknown',
                    $user->message_count,
                    $user->last_message_at ? $user->last_message_at->diffForHumans() : 'Never'
                ];
            })->toArray();

            $this->table(['Phone', 'Name', 'Messages', 'Last Active'], $tableData);
        } else {
            $this->line('No users found yet.');
        }
    }

    private function showUserStats($phoneNumber)
    {
        $user = WhatsAppUser::where('phone_number', $phoneNumber)->first();

        if (!$user) {
            $this->error("User {$phoneNumber} not found!");
            return 1;
        }

        $this->info("📱 User Statistics for {$phoneNumber}");
        $this->line('================================');

        $userMessages = $user->messages()->count();
        $inboundCount = $user->inboundMessages()->count();
        $outboundCount = $user->outboundMessages()->count();

        $this->table(['Field', 'Value'], [
            ['Phone Number', $user->phone_number],
            ['Name', $user->name ?? 'Not provided'],
            ['Status', $user->status],
            ['Current Step', $user->current_step],
            ['Total Messages', $userMessages],
            ['Inbound Messages', $inboundCount],
            ['Outbound Messages', $outboundCount],
            ['Joined', $user->created_at->format('Y-m-d H:i:s')],
            ['Last Active', $user->last_message_at ? $user->last_message_at->diffForHumans() : 'Never']
        ]);

        // Last 5 messages
        $this->line('');
        $this->info('💬 Recent Messages:');
        $recentMessages = $user->messages()->orderBy('created_at', 'desc')->limit(5)->get();

        if ($recentMessages->count() > 0) {
            $messagesData = $recentMessages->map(function ($message) {
                return [
                    $message->direction === 'inbound' ? '📥' : '📤',
                    $message->type,
                    mb_substr($message->content, 0, 50) . (mb_strlen($message->content) > 50 ? '...' : ''),
                    $message->created_at->format('H:i:s')
                ];
            })->toArray();

            $this->table(['Direction', 'Type', 'Content', 'Time'], $messagesData);
        } else {
            $this->line('No messages found.');
        }

        return 0;
    }
}
