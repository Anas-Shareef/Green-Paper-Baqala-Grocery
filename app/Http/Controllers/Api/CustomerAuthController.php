<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CustomerAuthController extends Controller
{
    /**
     * Send OTP to customer phone number.
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string|min:7|max:15',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);

        // Standard verification code for testing: 1234
        $otp = '1234';
        Cache::put("otp_{$phone}", $otp, now()->addMinutes(10));

        // Find or create customer
        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Valued Customer',
                'whatsapp_number' => $phone,
                'status' => 'active',
            ]
        );

        return response()->json([
            'success' => true,
            'message' => "Verification code sent to {$phone}.",
            'demo_otp' => $otp, // Helpful demo helper
            'phone' => $phone,
            'customer' => $customer,
        ]);
    }

    /**
     * Verify OTP and return authenticated customer profile payload.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        $phone = preg_replace('/[^0-9]/', '', $request->phone);
        $otp = trim($request->otp);
        $cachedOtp = Cache::get("otp_{$phone}");

        if ($otp !== '1234' && $otp !== $cachedOtp) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid verification code. Use demo code 1234.',
            ], 422);
        }

        $customer = Customer::where('phone', $phone)->firstOrFail();

        // If user submitted name or villa during verification
        if ($request->filled('name')) {
            $customer->name = $request->name;
        }
        if ($request->filled('villa_number')) {
            $customer->villa_number = $request->villa_number;
        }
        if ($request->filled('address')) {
            $customer->address = $request->address;
        }
        $customer->save();

        return response()->json([
            'success' => true,
            'message' => 'Phone verified successfully.',
            'token' => 'cust_token_' . bin2hex(random_bytes(16)),
            'customer' => $customer,
        ]);
    }
}
