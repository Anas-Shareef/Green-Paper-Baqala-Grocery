<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Services\PhoneNumberService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminCustomerController extends BaseApiController
{
    /**
     * Get Paginated Customer List for Admin Portal
     */
    public function index(Request $request): JsonResponse
    {
        $query = Customer::with(['defaultAddress', 'addresses']);

        if ($request->has('q') && !empty(trim($request->input('q')))) {
            $q = trim($request->input('q'));
            $query->where(function ($sub) use ($q) {
                $sub->where('name', 'like', "%{$q}%")
                    ->orWhere('phone', 'like', "%{$q}%")
                    ->orWhere('whatsapp_number', 'like', "%{$q}%");
            });
        }

        $customers = $query->orderBy('id', 'desc')->paginate(20);

        // Transform with computed total orders & total spent
        $transformed = $customers->getCollection()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'phone' => $c->phone,
                'whatsapp_number' => $c->whatsapp_number ?: $c->phone,
                'status' => $c->status,
                'total_orders' => $c->total_orders,
                'total_spent' => $c->total_spent,
                'average_order_value' => $c->average_order_value,
                'last_order_date' => $c->last_order_date,
                'default_address' => $c->defaultAddress ?: $c->addresses->first(),
                'created_at' => $c->created_at,
            ];
        });

        $customers->setCollection($transformed);

        return $this->paginatedResponse($customers, 'Admin customers retrieved successfully');
    }

    /**
     * Get Detailed Customer Profile with Orders & Address Book
     */
    public function show(string $id): JsonResponse
    {
        $customer = Customer::with(['addresses', 'orders' => function ($q) {
            $q->orderBy('id', 'desc')->with('items');
        }])->find($id);

        if (!$customer) {
            return $this->errorResponse('Customer not found', [], 404);
        }

        return $this->successResponse([
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'whatsapp_number' => $customer->whatsapp_number,
                'status' => $customer->status,
                'notes' => $customer->notes,
                'total_orders' => $customer->total_orders,
                'total_spent' => $customer->total_spent,
                'average_order_value' => $customer->average_order_value,
                'last_order_date' => $customer->last_order_date,
                'created_at' => $customer->created_at,
            ],
            'addresses' => $customer->addresses,
            'orders' => $customer->orders,
        ], 'Customer details retrieved successfully');
    }

    /**
     * Update Customer Information (Admin)
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $customer = Customer::find($id);
        if (!$customer) {
            return $this->errorResponse('Customer not found', [], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|in:active,inactive,blocked',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $updateData = array_filter($request->only(['name', 'notes', 'status']));
        if ($request->has('phone')) {
            $updateData['phone'] = PhoneNumberService::normalize($request->input('phone'));
        }

        $customer->update($updateData);

        return $this->successResponse($customer->fresh(), 'Customer updated successfully');
    }
}
