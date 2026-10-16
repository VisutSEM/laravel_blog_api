<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    /**
     * Send an OTP code to a phone number.
     *
     * @param string $phoneNumber
     * @param string $otp
     * @param int $expiresInMinutes
     * @return array
     */
    public function sendOtp(string $phoneNumber, string $otp, int $expiresInMinutes = 15): array
    {
        $appName = config('app.name', 'Laravel');
        $message = "Your {$appName} password reset verification code is: {$otp}. This code is valid for {$expiresInMinutes} minutes.";

        $driver = config('sms.default', 'log');

        return match ($driver) {
            'twilio' => $this->sendViaTwilio($phoneNumber, $message),
            default  => $this->sendViaLog($phoneNumber, $message, $otp),
        };
    }

    /**
     * Send SMS via Log driver (ideal for local development & testing).
     */
    protected function sendViaLog(string $phoneNumber, string $message, string $otp): array
    {
        Log::channel(config('sms.drivers.log.channel', 'stack'))->info(
            "=================== SMS OUTBOX ===================\n" .
            "To: {$phoneNumber}\n" .
            "OTP: {$otp}\n" .
            "Message: {$message}\n" .
            "=================================================="
        );

        return [
            'success' => true,
            'driver'  => 'log',
            'message' => 'SMS recorded in system log.',
        ];
    }

    /**
     * Send SMS via Twilio REST API.
     */
    protected function sendViaTwilio(string $phoneNumber, string $message): array
    {
        $sid = config('sms.drivers.twilio.sid');
        $token = config('sms.drivers.twilio.auth_token');
        $from = config('sms.drivers.twilio.from');

        if (!$sid || !$token || !$from) {
            Log::warning('Twilio SMS requested but credentials are not configured in .env or config/sms.php. Falling back to Log.');
            return $this->sendViaLog($phoneNumber, $message, '');
        }

        try {
            $response = Http::asForm()
                ->withBasicAuth($sid, $token)
                ->post("https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json", [
                    'To'   => $phoneNumber,
                    'From' => $from,
                    'Body' => $message,
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'driver'  => 'twilio',
                    'message' => 'SMS sent successfully via Twilio.',
                ];
            }

            Log::error('Twilio SMS failed', ['response' => $response->body()]);
            return [
                'success' => false,
                'driver'  => 'twilio',
                'message' => 'Failed to send SMS via Twilio: ' . ($response->json('message') ?? 'Unknown error'),
            ];
        } catch (\Throwable $e) {
            Log::error('Twilio SMS exception: ' . $e->getMessage());
            return [
                'success' => false,
                'driver'  => 'twilio',
                'message' => 'Exception while sending SMS: ' . $e->getMessage(),
            ];
        }
    }
}
