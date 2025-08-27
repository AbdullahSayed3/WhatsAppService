<?php
// app/Models/WhatsAppUser.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class WhatsAppUser extends Model
{
    use HasFactory;

    // إضافة اسم الجدول بوضوح
    protected $table = 'whatsapp_users';

    protected $fillable = [
        'phone_number',  // تم التغيير من phone_number إلى phone
        'name',
        'status',
        'session_data',
        'current_step',
        'first_message_at',
        'last_message_at',
        'message_count'
    ];

    protected $casts = [
        'session_data' => 'array',
        'first_message_at' => 'datetime',
        'last_message_at' => 'datetime'
    ];

    /**
     * علاقة مع الرسائل
     */
    public function messages()
    {
        return $this->hasMany(WhatsAppMessage::class, 'phone_number', 'phone_number')  // تغيير phone_number إلى phone
            ->orderBy('created_at', 'desc');
    }

    /**
     * الرسائل الواردة فقط
     */
    public function inboundMessages()
    {
        return $this->messages()->where('direction', 'inbound');
    }

    /**
     * الرسائل الصادرة فقط
     */
    public function outboundMessages()
    {
        return $this->messages()->where('direction', 'outbound');
    }

    /**
     * هل المستخدم جديد؟
     */
    public function isNew()
    {
        return $this->status === 'new' || $this->message_count === 0;
    }

    /**
     * تحديث آخر نشاط
     */
    public function updateLastActivity()
    {
        $this->update([
            'last_message_at' => now(),
            'message_count' => $this->message_count + 1
        ]);
    }

    /**
     * تحديث خطوة المحادثة
     */
    public function setStep($step, $data = null)
    {
        $sessionData = $this->session_data ?? [];
        if ($data) {
            $sessionData = array_merge($sessionData, $data);
        }

        $this->update([
            'current_step' => $step,
            'session_data' => $sessionData
        ]);
    }

    /**
     * الحصول على بيانات الجلسة
     */
    public function getSessionData($key = null, $default = null)
    {
        if ($key === null) {
            return $this->session_data ?? [];
        }

        return $this->session_data[$key] ?? $default;
    }

    /**
     * إنشاء مستخدم جديد أو الحصول على موجود
     */
    public static function findOrCreateUser($phoneNumber)
    {
        $user = static::where('phone_number', $phoneNumber)->first();  // تغيير phone_number إلى phone

        if (!$user) {
            $user = static::create([
                'phone_number' => $phoneNumber,  // تغيير phone_number إلى phone
                'status' => 'new',
                'first_message_at' => now(),
                'last_message_at' => now(),
                'current_step' => 'welcome'
            ]);
        }

        return $user;
    }
}
