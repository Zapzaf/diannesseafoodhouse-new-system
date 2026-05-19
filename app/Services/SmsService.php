<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class SmsService
{
    public function send(string $to, string $message): void
    {
        $endpoint = (string) config('services.sms.endpoint', env('SMS_ENDPOINT'));
        $apiKey = (string) config('services.sms.api_key', env('SMS_API_KEY'));

        if ($endpoint === '' || $apiKey === '' || $to === '') {
            return;
        }

        Http::withToken($apiKey)
            ->acceptJson()
            ->post($endpoint, [
                'to' => $to,
                'message' => $message,
                'device' => (string) config('services.sms.device', env('SMS_DEVICE')),
                'sim' => (string) config('services.sms.sim', env('SMS_SIM')),
            ]);
    }
}
