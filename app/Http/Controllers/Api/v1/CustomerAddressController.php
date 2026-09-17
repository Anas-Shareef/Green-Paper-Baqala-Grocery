<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\PhoneNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class CustomerAddressController extends BaseApiController
{
    /**
     * Recognize Customer by Mobile Phone Number & Return Saved Delivery Details
     */
    public function recognize(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'phone' => 'required|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Valid phone number is required', $validator->errors(), 422);
            }

            $rawPhone = $request->input('phone');
            $phone = PhoneNumberService::normalize($rawPhone);
            if (empty($phone)) {
                return $this->errorResponse('Please enter a valid UAE mobile number.', [], 422);
            }

            $customer = PhoneNumberService::findCustomer($rawPhone);

            if (!$customer) {
                return $this->successResponse([
                    'customer_exists' => false,
                    'phone' => $phone,
                    'customer' => null,
                    'addresses' => [],
                ], 'Mobile number is available for a new guest order.');
            }

            // Safe customer address loading
            $addresses = [];
            try {
                if (\Illuminate\Support\Facades\Schema::hasTable('customer_addresses')) {
                    $addresses = $customer->addresses()->orderBy('is_default', 'desc')->orderBy('id', 'desc')->get()->toArray();
                }
            } catch (\Throwable $e) {
                $addresses = [];
            }

            // Virtual fallback address card from customer's legacy columns if no customer_addresses exist
            if (empty($addresses) && ($customer->villa_number || $customer->address || $customer->zone)) {
                $addresses = [
                    [
                        'id' => 'legacy-' . $customer->id,
                        'customer_id' => $customer->id,
                        'label' => 'Home',
                        'villa_number' => $customer->villa_number ?: 'Villa',
                        'street_address' => $customer->address ?: ($customer->zone ? "Zone {$customer->zone}" : 'Villa Delivery'),
                        'zone' => $customer->zone,
                        'landmark' => null,
                        'delivery_notes' => null,
                        'is_default' => true,
                    ]
                ];
            }

            return $this->successResponse([
                'customer_exists' => true,
                'phone' => $phone,
                'customer' => [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'phone' => $customer->phone ?: $phone,
                ],
                'addresses' => $addresses,
            ], 'Customer Recognized');

        } catch (\Throwable $e) {
            return $this->errorResponse('Unable to recognize customer profile: ' . $e->getMessage(), [], 500);
        }
    }

    /**
     * Alias for recognize method
     */
    public function identify(Request $request): JsonResponse
    {
        return $this->recognize($request);
    }

    /**
     * Get Addresses for Customer Phone
     */
    public function index(Request $request): JsonResponse
    {
        $rawPhone = $request->input('phone', '');
        if (empty($rawPhone)) {
            return $this->errorResponse('Phone parameter is required', [], 422);
        }

        $customer = PhoneNumberService::findCustomer($rawPhone);
        if (!$customer) {
            return $this->successResponse([], 'No addresses found for customer');
        }

        $addresses = CustomerAddress::where('customer_id', $customer->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('id', 'desc')
            ->get();

        return $this->successResponse($addresses, 'Customer addresses retrieved');
    }

    /**
     * Create New Delivery Address for Customer
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string',
            'name' => 'nullable|string|max:255',
            'label' => 'nullable|string|max:50',
            'villa_number' => 'nullable|string|max:100',
            'building_number' => 'nullable|string|max:100',
            'street_address' => 'required|string',
            'zone' => 'nullable|string|max:100',
            'landmark' => 'nullable|string|max:255',
            'delivery_notes' => 'nullable|string',
            'is_default' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $rawPhone = $request->input('phone');
        $phone = PhoneNumberService::normalize($rawPhone);
        $name = trim($request->input('name', 'Valued Customer'));

        $customer = PhoneNumberService::findCustomer($rawPhone);
        if (!$customer) {
            $customer = Customer::create([
                'phone' => $phone,
                'name' => $name,
                'status' => 'active',
            ]);
        }

        if ($name !== 'Valued Customer' && $customer->name === 'Valued Customer') {
            $customer->update(['name' => $name]);
        }

        $isFirst = $customer->addresses()->count() === 0;
        $isDefault = $isFirst || (bool) $request->input('is_default', false);

        if ($isDefault) {
            CustomerAddress::where('customer_id', $customer->id)->update(['is_default' => false]);
        }

        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'label' => $request->input('label', 'Home'),
            'villa_number' => $request->input('villa_number'),
            'building_number' => $request->input('building_number'),
            'street_address' => $request->input('street_address'),
            'zone' => $request->input('zone'),
            'landmark' => $request->input('landmark'),
            'delivery_notes' => $request->input('delivery_notes'),
            'is_default' => $isDefault,
        ]);

        return $this->successResponse($address, 'Address created successfully', 201);
    }

    /**
     * Update Saved Customer Address
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $address = CustomerAddress::find($id);
        if (!$address) {
            return $this->errorResponse('Address not found', [], 404);
        }

        $address->update($request->only([
            'label', 'villa_number', 'building_number',
            'street_address', 'zone', 'landmark', 'delivery_notes'
        ]));

        if ($request->has('is_default') && $request->input('is_default')) {
            CustomerAddress::where('customer_id', $address->customer_id)
                ->where('id', '!=', $address->id)
                ->update(['is_default' => false]);
            $address->update(['is_default' => true]);
        }

        return $this->successResponse($address, 'Address updated successfully');
    }

    /**
     * Delete Saved Customer Address
     */
    public function destroy(string $id): JsonResponse
    {
        $address = CustomerAddress::find($id);
        if (!$address) {
            return $this->errorResponse('Address not found', [], 404);
        }

        $customerId = $address->customer_id;
        $wasDefault = $address->is_default;

        $address->delete();

        if ($wasDefault) {
            $nextAddress = CustomerAddress::where('customer_id', $customerId)->first();
            if ($nextAddress) {
                $nextAddress->update(['is_default' => true]);
            }
        }

        return $this->successResponse(null, 'Address deleted successfully');
    }

    /**
     * Set Address as Default
     */
    public function setDefault(string $id): JsonResponse
    {
        $address = CustomerAddress::find($id);
        if (!$address) {
            return $this->errorResponse('Address not found', [], 404);
        }

        CustomerAddress::where('customer_id', $address->customer_id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return $this->successResponse($address, 'Address set as default');
    }

    /**
     * Update Customer Profile & Phone Number without Creating Duplicate Customer (PRD Section 11 & 12)
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => 'required|exists:customers,id',
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $customer = Customer::find($request->input('customer_id'));
        if (!$customer) {
            return $this->errorResponse('Customer not found', [], 404);
        }

        $dataToUpdate = [];
        if ($request->has('name') && !empty($request->input('name'))) {
            $dataToUpdate['name'] = trim($request->input('name'));
        }

        if ($request->has('phone') && !empty($request->input('phone'))) {
            $normalized = PhoneNumberService::normalize($request->input('phone'));
            if (!empty($normalized)) {
                $dataToUpdate['phone'] = $normalized;
            }
        }

        if ($request->has('notes')) {
            $dataToUpdate['notes'] = $request->input('notes');
        }

        if (!empty($dataToUpdate)) {
            $customer->update($dataToUpdate);
        }

        return $this->successResponse([
            'customer' => $customer->fresh(['addresses']),
        ], 'Customer profile updated successfully');
    }
}
