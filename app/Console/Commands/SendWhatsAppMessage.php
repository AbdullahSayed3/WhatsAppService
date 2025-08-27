<?php
// app/Console/Commands/SendWhatsAppMessage.php

namespace App\Console\Commands;

use App\Services\WhatsAppService;
use Illuminate\Console\Command;

class SendWhatsAppMessage extends Command
{
    protected $signature = 'whatsapp:send 
                            {phone : The phone number with country code}
                            {message : The message to send}
                            {--type=text : Message type (text, template)}
                            {--template= : Template name if type is template}
                            {--params=* : Template parameters}';

    protected $description = 'Send WhatsApp message via command line';

    public function handle()
    {
        $phone = $this->argument('phone');
        $message = $this->argument('message');
        $type = $this->option('type');
        $template = $this->option('template');
        $params = $this->option('params');

        $this->info("Sending {$type} message to {$phone}...");

        try {
            $whatsAppService = new WhatsAppService();

            if ($type === 'template' && $template) {
                $result = $whatsAppService->sendTemplateMessage($phone, $template, 'en', $params);
            } else {
                $result = $whatsAppService->sendTextMessage($phone, $message);
            }

            if (isset($result['error'])) {
                $this->error('Error: ' . $result['error']);
                return;
            }

            if (is_array($result)) {
                $this->info('Message sent successfully!');
                $this->line('Response: ' . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            } else {
                $this->info('Response: ' . $result);
            }
        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
            $this->error('Line: ' . $e->getLine() . ' in ' . $e->getFile());
        }
    }
}
