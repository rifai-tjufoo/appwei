<?php

namespace App\Services;

use App\Models\AppSetting;

class AppSettings
{
    public const WHATSAPP_API_URL = 'whatsapp_api_url';

    public const WHATSAPP_API_KEY = 'whatsapp_api_key';

    public static function whatsappApiUrl(): string
    {
        return rtrim(AppSetting::get(self::WHATSAPP_API_URL, 'https://wa.forfunforlife.com'), '/');
    }

    public static function whatsappApiKey(): ?string
    {
        return AppSetting::get(self::WHATSAPP_API_KEY);
    }
}
