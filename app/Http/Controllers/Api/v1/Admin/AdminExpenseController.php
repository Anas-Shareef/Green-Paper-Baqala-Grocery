<?php

namespace App\Http\Controllers\Api\v1\Admin;

use App\Http\Controllers\Api\v1\BaseApiController;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Services\SupabaseStorageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminExpenseController extends BaseApiController
{
    protected SupabaseStorageService $storageService;

    public function __construct(SupabaseStorageService $storageService)
    {
        $this->storageService = $storageService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Expense::with('category');

        if ($request->has('category_id') && !empty($request->input('category_id'))) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->has('payment_method') && !empty($request->input('payment_method'))) {
            $query->where('payment_method', $request->input('payment_method'));
        }

        if ($request->has('start_date') && $request->has('end_date')) {
            $query->whereBetween('expense_date', [$request->input('start_date'), $request->input('end_date')]);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->orderBy('id', 'desc')->paginate(20);
        $categories = ExpenseCategory::all();

        return response()->json([
            'success' => true,
            'message' => 'Expenses retrieved successfully',
            'data' => $expenses->items(),
            'categories' => $categories,
            'meta' => [
                'current_page' => $expenses->currentPage(),
                'last_page' => $expenses->lastPage(),
                'total' => $expenses->total(),
                'total_amount' => (float) $query->sum('amount'),
            ]
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'category_id' => 'required|exists:expense_categories,id',
            'amount' => 'required|numeric|min:0.01',
            'expense_date' => 'required|date',
            'payment_method' => 'required|string|in:cash,card,bank_transfer,check',
            'description' => 'nullable|string',
            'attachment' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation failed', $validator->errors(), 422);
        }

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $this->storageService->uploadFile($request->file('attachment'), 'expense-attachments', 'expenses');
        }

        $expense = Expense::create([
            'expense_number' => 'EXP-' . strtoupper(uniqid()),
            'category_id' => $request->input('category_id'),
            'amount' => $request->input('amount'),
            'expense_date' => $request->input('expense_date'),
            'payment_method' => $request->input('payment_method'),
            'description' => $request->input('description'),
            'attachment_path' => $attachmentPath,
        ]);

        return $this->successResponse($expense->load('category'), 'Expense recorded successfully', 201);
    }

    public function destroy(string $id): JsonResponse
    {
        $expense = Expense::find($id);
        if (!$expense) {
            return $this->errorResponse('Expense not found', [], 404);
        }

        if ($expense->attachment_path) {
            $this->storageService->deleteFile($expense->attachment_path, 'expense-attachments');
        }

        $expense->delete();
        return $this->successResponse(null, 'Expense deleted successfully');
    }
}
