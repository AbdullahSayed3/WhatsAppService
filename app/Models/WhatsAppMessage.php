<?php
// app/Models/WhatsAppMessage.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'phone_number',
        'direction',
        'type',
        'content',
        'metadata',
        'status',
        'sent_at',
        'delivered_at',
        'read_at'
    ];

    protected $casts = [
        'metadata' => 'array',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime'
    ];

    /**
     * relation with user
     */
    public function user()
    {
        return $this->belongsTo(WhatsAppUser::class, 'phone_number', 'phone_number');
    }

    /**
     * record inbound message
     */
    public static function logInbound($messageId, $phoneNumber, $type, $content, $metadata = null)
    {
        return static::create([
            'message_id' => $messageId,
            'phone_number' => $phoneNumber,
            'direction' => 'inbound',
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
            'status' => 'received'
        ]);
    }

    /**
     * record outbound message
     */
    public static function logOutbound($messageId, $phoneNumber, $type, $content, $metadata = null)
    {
        return static::create([
            'message_id' => $messageId,
            'phone_number' => $phoneNumber,
            'direction' => 'outbound',
            'type' => $type,
            'content' => $content,
            'metadata' => $metadata,
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    /**
     * update message status
     */
    public function updateStatus($status, $timestamp = null)
    {
        $updates = ['status' => $status];

        switch ($status) {
            case 'delivered':
                $updates['delivered_at'] = $timestamp ?? now();
                break;
            case 'read':
                $updates['read_at'] = $timestamp ?? now();
                break;
        }

        $this->update($updates);
    }
}
