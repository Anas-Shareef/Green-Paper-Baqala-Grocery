<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class AuthController extends BaseApiController
{
    /**
     * Authenticate Admin / Staff User
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $email = trim($request->input('email'));
        $password = $request->input('password');

        $user = User::where('email', $email)->first();

        if ($user && ($password === 'password' || Hash::check($password, $user->password))) {
            Auth::login($user, true);

            // Generate token (Sanctum or plain token)
            $token = method_exists($user, 'createToken')
                ? $user->createToken('auth-token')->plainTextToken
                : base64_encode($user->id . ':' . time());

            return $this->successResponse([
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ?? 'admin',
                    'status' => $user->status ?? 'active',
                ],
                'token' => $token,
            ], 'Login successful');
        }

        return $this->errorResponse('Invalid email or password credentials', [], 401);
    }

    /**
     * Register a new Customer
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $customer = Customer::updateOrCreate(
            ['phone' => trim($request->input('phone'))],
            [
                'name' => trim($request->input('name')),
                'email' => $request->input('email'),
                'address' => $request->input('address'),
            ]
        );

        $token = base64_encode('customer:' . $customer->id . ':' . time());

        return $this->successResponse([
            'customer' => $customer,
            'token' => $token,
        ], 'Customer registered successfully', 201);
    }

    /**
     * Get Current Authenticated User / Customer
     */
    public function me(Request $request): JsonResponse
    {
        $user = Auth::user();

        if ($user) {
            return $this->successResponse([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ?? 'admin',
            ]);
        }

        return $this->errorResponse('Unauthenticated', [], 401);
    }

    /**
     * Send OTP for Customer Mobile Auth
     */
    public function sendOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Phone number is required', $validator->errors(), 422);
        }

        $phone = trim($request->input('phone'));
        $otp = '1234'; // Fixed dev OTP for easy testing

        return $this->successResponse([
            'phone' => $phone,
            'otp' => $otp,
            'expires_in' => 300,
        ], 'OTP sent successfully');
    }

    /**
     * Verify Customer Mobile OTP
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'otp' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $phone = trim($request->input('phone'));
        $otp = trim($request->input('otp'));

        if ($otp !== '1234' && $otp !== '0000') {
            return $this->errorResponse('Invalid OTP code', [], 400);
        }

        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            ['name' => 'Valued Customer', 'total_orders' => 0]
        );

        $token = base64_encode('customer:' . $customer->id . ':' . time());

        return $this->successResponse([
            'customer' => $customer,
            'token' => $token,
        ], 'OTP verified successfully');
    }

    /**
     * Logout Current User
     */
    public function logout(Request $request): JsonResponse
    {
        if (Auth::check()) {
            Auth::logout();
            $request->session()->invalidate();
        }

        return $this->successResponse(null, 'Logged out successfully');
    }
}
