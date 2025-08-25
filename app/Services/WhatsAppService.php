<?php
// app/Services/WhatsAppService.php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppService
{
    private $accessToken;
    private $phoneNumberId;
    private $apiVersion;
    private $baseUrl;

    public function __construct()
    {
        $this->accessToken = config('services.whatsapp.access_token');
        $this->phoneNumberId = config('services.whatsapp.phone_number_id');
        $this->apiVersion = config('services.whatsapp.api_version', 'v22.0');
        $this->baseUrl = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}";
    }

    /**
     * Send Text Message
     */
    public function sendTextMessage($to, $message)
    {
        $url = "{$this->baseUrl}/messages";

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];

        return $this->sendRequest($url, $data);
    }

    /**
     * Send Template Message
     */
    public function sendTemplateMessage($to, $templateName, $languageCode = 'ar', $parameters = [])
    {
        $url = "{$this->baseUrl}/messages";

        $components = [];
        if (!empty($parameters)) {
            $components[] = [
                'type' => 'body',
                'parameters' => array_map(function ($param) {
                    return ['type' => 'text', 'text' => $param];
                }, $parameters)
            ];
        }

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => [
                    'code' => $languageCode
                ],
                'components' => $components
            ]
        ];

        return $this->sendRequest($url, $data);
    }

    /**
     * Send Image Message
     */
    public function sendImageMessage($to, $imageUrl, $caption = '')
    {
        $url = "{$this->baseUrl}/messages";

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'image',
            'image' => [
                'link' => $imageUrl,
                'caption' => $caption
            ]
        ];

        return $this->sendRequest($url, $data);
    }

    /**
     * Send Document Message
     */
    public function sendDocumentMessage($to, $documentUrl, $filename = '', $caption = '')
    {
        $url = "{$this->baseUrl}/messages";

        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'document',
            'document' => [
                'link' => $documentUrl,
                'filename' => $filename,
                'caption' => $caption
            ]
        ];

        return $this->sendRequest($url, $data);
    }

    /**
     * Send request to WhatsApp API
     */
    private function sendRequest($url, $data)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])->post($url, $data);

            Log::info('WhatsApp API Response: ' . $response->body());

            return $response->json();
        } catch (\Exception $e) {
            Log::error('WhatsApp API Error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Add Mark as Read functionality
     */
    public function markAsRead($messageId)
    {
        $url = "{$this->baseUrl}/messages";

        $data = [
            'messaging_product' => 'whatsapp',
            'status' => 'read',
            'message_id' => $messageId
        ];

        return $this->sendRequest($url, $data);
    }
}
