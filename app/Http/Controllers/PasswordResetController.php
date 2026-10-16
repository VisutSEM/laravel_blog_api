<?php

namespace App\Http\Controllers;

use App\Mail\ResetPasswordOtpMail;
use App\Models\PasswordResetCode;
use App\Models\User;
use App\Services\SmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetController extends Controller
{
    public function __construct(
        protected SmsService $smsService
    ) {}

    /**
     * Step 1: Request a password reset OTP by email or phone number.
     * POST /api/password/forgot
     */
    public function sendResetOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string|max:255',
        ]);

        $rawIdentifier = trim($validated['identifier']);
        $type = filter_var($rawIdentifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';

        // Normalize identifier
        $normalizedIdentifier = $type === 'email'
            ? strtolower($rawIdentifier)
            : preg_replace('/[^\d+]/', '', $rawIdentifier);

        // Find user by email or phone number
        $user = $type === 'email'
            ? User::where('email', $normalizedIdentifier)->first()
            : User::where('phone', $rawIdentifier)
                  ->orWhere('phone', $normalizedIdentifier)
                  ->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => "No user account was found associated with this {$type}.",
            ], 404);
        }

        // Rate limit: prevent requesting more than once every 60 seconds
        $recentCode = PasswordResetCode::where('identifier', $normalizedIdentifier)
            ->where('created_at', '>=', now()->subSeconds(60))
            ->first();

        if ($recentCode) {
            $secondsRemaining = 60 - $recentCode->created_at->diffInSeconds(now());
            return response()->json([
                'status'  => 'error',
                'message' => "Please wait {$secondsRemaining} seconds before requesting a new verification code.",
            ], 429);
        }

        // Invalidate any existing codes for this identifier
        PasswordResetCode::where('identifier', $normalizedIdentifier)->delete();

        // Generate 6-digit numeric OTP
        $otp = sprintf('%06d', random_int(100000, 999999));
        $expiresInMinutes = 15;

        // Save new OTP record
        PasswordResetCode::create([
            'identifier' => $normalizedIdentifier,
            'type'       => $type,
            'otp'        => $otp,
            'attempts'   => 0,
            'expires_at' => now()->addMinutes($expiresInMinutes),
        ]);

        // Dispatch OTP via Email or SMS
        $dispatchSuccess = true;
        $deliveryMessage = '';

        if ($type === 'email') {
            try {
                Mail::to($user->email)->send(new ResetPasswordOtpMail($otp, $user->name, $expiresInMinutes));
                $deliveryMessage = 'Verification code sent to your email address.';
            } catch (\Throwable $e) {
                $dispatchSuccess = false;
                $deliveryMessage = 'Failed to deliver email: ' . $e->getMessage();
            }
        } else {
            $phoneNumber = $user->phone ?: $normalizedIdentifier;
            $smsResult = $this->smsService->sendOtp($phoneNumber, $otp, $expiresInMinutes);
            $dispatchSuccess = $smsResult['success'];
            $deliveryMessage = $smsResult['message'];
        }

        $maskedIdentifier = $this->maskIdentifier($rawIdentifier, $type);

        $responseData = [
            'identifier'         => $maskedIdentifier,
            'type'               => $type,
            'expires_in_minutes' => $expiresInMinutes,
        ];

        // Helpful in local/debug environment for testing
        if (config('app.debug')) {
            $responseData['debug_otp'] = $otp;
        }

        if (!$dispatchSuccess) {
            return response()->json([
                'status'  => 'error',
                'message' => $deliveryMessage,
                'data'    => $responseData,
            ], 500);
        }

        return response()->json([
            'status'  => 'success',
            'message' => "A verification code has been sent to {$maskedIdentifier}.",
            'data'    => $responseData,
        ], 200);
    }

    /**
     * Step 2: Verify the 6-digit OTP code.
     * POST /api/password/verify-otp
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => 'required|string',
            'otp'        => 'required|string|size:6',
        ]);

        $rawIdentifier = trim($validated['identifier']);
        $type = filter_var($rawIdentifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $normalizedIdentifier = $type === 'email'
            ? strtolower($rawIdentifier)
            : preg_replace('/[^\d+]/', '', $rawIdentifier);

        $record = PasswordResetCode::where('identifier', $normalizedIdentifier)
            ->orWhere('identifier', $rawIdentifier)
            ->latest()
            ->first();

        if (!$record) {
            return response()->json([
                'status'  => 'error',
                'message' => 'No active password reset request was found for this account.',
            ], 404);
        }

        if ($record->isExpired()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The verification code has expired. Please request a new one.',
            ], 400);
        }

        if ($record->hasExceededAttempts(5)) {
            $record->delete();
            return response()->json([
                'status'  => 'error',
                'message' => 'Too many failed verification attempts. This code has been invalidated. Please request a new one.',
            ], 429);
        }

        if ($record->otp !== trim($validated['otp'])) {
            $record->increment('attempts');
            $remaining = 5 - $record->attempts;
            $hint = $remaining > 0 ? " You have {$remaining} attempt(s) remaining." : '';

            return response()->json([
                'status'  => 'error',
                'message' => 'Invalid verification code.' . $hint,
            ], 400);
        }

        // Generate a secure single-use reset token for step 3
        $resetToken = Str::random(64);

        $record->update([
            'reset_token' => $resetToken,
            'verified_at' => now(),
            'expires_at'  => now()->addMinutes(15), // 15 mins to complete password reset
        ]);

        return response()->json([
            'status'  => 'success',
            'message' => 'Verification code verified successfully.',
            'data'    => [
                'reset_token' => $resetToken,
                'identifier'  => $rawIdentifier,
            ],
        ], 200);
    }

    /**
     * Step 3: Reset password using verified reset_token (or directly with valid OTP).
     * POST /api/password/reset
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'identifier'            => 'required|string',
            'reset_token'           => 'nullable|string',
            'otp'                   => 'nullable|string|size:6',
            'password'              => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string',
        ]);

        if (empty($validated['reset_token']) && empty($validated['otp'])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Either reset_token or otp is required to reset your password.',
            ], 422);
        }

        $rawIdentifier = trim($validated['identifier']);
        $type = filter_var($rawIdentifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'phone';
        $normalizedIdentifier = $type === 'email'
            ? strtolower($rawIdentifier)
            : preg_replace('/[^\d+]/', '', $rawIdentifier);

        // Find matching reset code
        $query = PasswordResetCode::where(function ($q) use ($rawIdentifier, $normalizedIdentifier) {
            $q->where('identifier', $normalizedIdentifier)
              ->orWhere('identifier', $rawIdentifier);
        });

        if (!empty($validated['reset_token'])) {
            $query->where('reset_token', $validated['reset_token'])
                  ->whereNotNull('verified_at');
        } else {
            $query->where('otp', $validated['otp']);
        }

        $record = $query->latest()->first();

        if (!$record || $record->isExpired()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'The reset token or code is invalid or has expired. Please restart the reset process.',
            ], 400);
        }

        // Find the user
        $user = $type === 'email'
            ? User::where('email', $normalizedIdentifier)->first()
            : User::where('phone', $rawIdentifier)
                  ->orWhere('phone', $normalizedIdentifier)
                  ->first();

        if (!$user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'User account could not be found.',
            ], 404);
        }

        // Update user password
        $user->password = Hash::make($validated['password']);
        $user->save();

        // Invalidate all reset tokens for this user/identifier
        PasswordResetCode::where('identifier', $normalizedIdentifier)
            ->orWhere('identifier', $rawIdentifier)
            ->delete();

        // Revoke all existing Sanctum authentication tokens for security
        $user->tokens()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Your password has been reset successfully. Please log in with your new password.',
        ], 200);
    }

    /**
     * Mask email or phone number for user privacy.
     */
    protected function maskIdentifier(string $identifier, string $type): string
    {
        if ($type === 'email') {
            $parts = explode('@', $identifier);
            if (count($parts) !== 2) {
                return $identifier;
            }
            $name = $parts[0];
            $domain = $parts[1];

            $len = strlen($name);
            if ($len <= 2) {
                $maskedName = substr($name, 0, 1) . '*';
            } else {
                $maskedName = substr($name, 0, 1) . str_repeat('*', max(1, $len - 2)) . substr($name, -1);
            }

            return $maskedName . '@' . $domain;
        }

        // Phone masking
        $len = strlen($identifier);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return substr($identifier, 0, 3) . str_repeat('*', max(2, $len - 5)) . substr($identifier, -2);
    }
}
