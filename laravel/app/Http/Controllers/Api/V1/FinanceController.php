<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\User;
use App\Models\FinanceCategory;
use App\Models\Donation;
use App\Models\IncomeRecord;
use App\Models\ExpenseRecord;
use App\Models\Receipt;
use App\Models\FinanceReceipt;
use App\Models\FinanceReceiptLine;
use App\Models\FinanceApproval;
use App\Services\DonationService;
use App\Services\IncomeService;
use App\Services\ExpenseService;
use App\Services\FinanceApprovalService;
use App\Services\FinanceReportService;
use App\Services\ReceiptGenerationService;
use App\Services\ChurchAccessService;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;
use Exception;

class FinanceController extends Controller
{
    use ApiResponse;

    /**
     * Helper to verify if the user has diocese-wide finance permissions.
     */
    protected function hasDioceseFinanceAccess(User $user): bool
    {
        if ($user->hasRole(['Super Admin', 'Diocese Admin', 'Diocese Secretary', 'Priest Secretary', 'Diocese Treasurer', 'Diocese Auditor'])) {
            return true;
        }
        return ChurchAccessService::hasDioceseAccess($user);
    }

    /**
     * Helper to check if a user can access a specific church's financial data.
     */
    protected function checkChurchAccess(User $user, ?int $churchId): void
    {
        if ($churchId === null) {
            if (!$this->hasDioceseFinanceAccess($user)) {
                throw new Exception("Diocese-level scoping access denied.", 403);
            }
            return;
        }

        if (!$this->hasDioceseFinanceAccess($user) && !ChurchAccessService::canAccessChurch($user, $churchId)) {
            throw new Exception("Access denied to church ID {$churchId}.", 403);
        }
    }

    // ==========================================
    // 1. Finance Category Routes
    // ==========================================
    public function listCategories(Request $request)
    {
        $user = $request->user();
        $query = FinanceCategory::query();

        if (!$this->hasDioceseFinanceAccess($user)) {
            $accessibleIds = ChurchAccessService::getAccessibleChurchIds($user);
            $query->where(function ($q) use ($accessibleIds) {
                $q->whereNull('church_id')->orWhereIn('church_id', $accessibleIds);
            });
        }

        $categories = $query->orderBy('category_type')->orderBy('name')->get();
        return $this->successResponse($categories, 'Finance categories retrieved.');
    }

    public function storeCategory(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_finance_categories')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'nullable|exists:churches,id',
            'category_type' => 'required|string|in:income,expense,donation,fee,other',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $churchId = $request->input('church_id');
            $this->checkChurchAccess($user, $churchId);

            $slug = Str::slug($request->input('name'));
            
            // Check uniqueness manually for custom scoped slug
            $exists = FinanceCategory::where('diocese_id', $request->input('diocese_id'))
                ->where('church_id', $churchId)
                ->where('slug', $slug)
                ->exists();
            if ($exists) {
                return $this->errorResponse('Category name already exists in this scope.', 422);
            }

            $category = FinanceCategory::create([
                'diocese_id' => $request->input('diocese_id'),
                'church_id' => $churchId,
                'category_type' => $request->input('category_type'),
                'name' => $request->input('name'),
                'slug' => $slug,
                'description' => $request->input('description'),
                'is_system' => false,
                'status' => 'active',
                'created_by' => $user->id,
            ]);

            AuditLogService::log(
                'Finance',
                'Category Created',
                "Created finance category '{$category->name}'",
                null,
                $category->toArray(),
                $category,
                $category->church_id,
                $category->diocese_id
            );

            return $this->successResponse($category, 'Finance category created.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function showCategory(Request $request, $id)
    {
        try {
            $category = FinanceCategory::findOrFail($id);
            $this->checkChurchAccess($request->user(), $category->church_id);
            return $this->successResponse($category, 'Finance category details.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateCategory(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_finance_categories')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $category = FinanceCategory::findOrFail($id);
            if ($category->is_system) {
                return $this->errorResponse('System categories cannot be modified.', 422);
            }
            $this->checkChurchAccess($user, $category->church_id);

            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'description' => 'nullable|string',
                'status' => 'required|string|in:active,inactive,archived',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), 422);
            }

            $oldValues = $category->toArray();

            $category->update([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'status' => $request->input('status'),
                'updated_by' => $user->id,
            ]);

            AuditLogService::log(
                'Finance',
                'Category Updated',
                "Updated category ID {$category->id}",
                $oldValues,
                $category->toArray(),
                $category,
                $category->church_id,
                $category->diocese_id
            );

            return $this->successResponse($category, 'Finance category updated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function archiveCategory(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_finance_categories')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $category = FinanceCategory::findOrFail($id);
            if ($category->is_system) {
                return $this->errorResponse('System categories cannot be archived.', 422);
            }
            $this->checkChurchAccess($user, $category->church_id);

            $oldValues = $category->toArray();

            $category->update([
                'status' => 'archived',
                'updated_by' => $user->id,
            ]);

            AuditLogService::log(
                'Finance',
                'Category Archived',
                "Archived category ID {$category->id}",
                $oldValues,
                $category->toArray(),
                $category,
                $category->church_id,
                $category->diocese_id
            );

            return $this->successResponse($category, 'Finance category archived.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // ==========================================
    // 2. Donation Routes
    // ==========================================
    public function listDonations(Request $request)
    {
        $user = $request->user();
        $query = Donation::query()->with(['category', 'member', 'family', 'church']);

        if (!$this->hasDioceseFinanceAccess($user)) {
            $query = ChurchAccessService::scopeQuery($user, $query);
        }

        $donations = $query->orderBy('received_date', 'desc')->get();
        return $this->successResponse($donations, 'Donations retrieved.');
    }

    public function storeDonation(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_donations')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'nullable|exists:churches,id',
            'family_id' => 'nullable|exists:families,id',
            'member_id' => 'nullable|exists:members,id',
            'finance_category_id' => 'nullable|exists:finance_categories,id',
            'donor_name' => 'required|string|max:255',
            'donor_email' => 'nullable|email',
            'donor_phone' => 'nullable|string',
            'donation_type' => 'required|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:3',
            'payment_method' => 'required|string|in:cash,bank_transfer,card,paypal,sepa,other',
            'payment_reference' => 'nullable|string',
            'received_date' => 'required|date',
            'status' => 'nullable|string|in:pending,received',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $churchId = $request->input('church_id');
            $this->checkChurchAccess($user, $churchId);

            $donation = DonationService::createDonation($request->all(), $user);
            return $this->successResponse($donation, 'Donation created.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function showDonation(Request $request, $id)
    {
        try {
            $donation = Donation::with(['category', 'member', 'family', 'receipt'])->findOrFail($id);
            $this->checkChurchAccess($request->user(), $donation->church_id);
            return $this->successResponse($donation, 'Donation details.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateDonation(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_donations')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $donation = Donation::findOrFail($id);
            $this->checkChurchAccess($user, $donation->church_id);

            if ($donation->status === 'received') {
                return $this->errorResponse('Received donations cannot be modified.', 422);
            }

            $validator = Validator::make($request->all(), [
                'donor_name' => 'required|string|max:255',
                'donor_email' => 'nullable|email',
                'donor_phone' => 'nullable|string',
                'amount' => 'required|numeric|min:0.01',
                'payment_method' => 'required|string|in:cash,bank_transfer,card,paypal,sepa,other',
                'payment_reference' => 'nullable|string',
                'received_date' => 'required|date',
                'notes' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), 422);
            }

            $oldValues = $donation->toArray();
            $donation->update($request->only([
                'donor_name', 'donor_email', 'donor_phone', 'amount', 'payment_method', 'payment_reference', 'received_date', 'notes'
            ]));

            AuditLogService::log(
                'Finance',
                'Donation Updated',
                "Updated donation ID {$donation->id}",
                $oldValues,
                $donation->toArray(),
                $donation,
                $donation->church_id,
                $donation->diocese_id
            );

            return $this->successResponse($donation, 'Donation updated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function markDonationReceived(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('approve_donations')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $donation = Donation::findOrFail($id);
            $this->checkChurchAccess($user, $donation->church_id);

            $donation = DonationService::markReceived($donation, $user);
            return $this->successResponse($donation, 'Donation marked as received and receipt generated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function approveDonation(Request $request, $id)
    {
        // Simple alias for mark received if already paid/received
        return $this->markDonationReceived($request, $id);
    }

    public function cancelDonation(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_donations')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $donation = Donation::findOrFail($id);
            $this->checkChurchAccess($user, $donation->church_id);

            $donation = DonationService::cancelDonation($donation, $request->input('reason'), $user);
            return $this->successResponse($donation, 'Donation cancelled.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function generateDonationReceipt(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('generate_receipts')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $donation = Donation::findOrFail($id);
            $this->checkChurchAccess($user, $donation->church_id);

            $receipt = ReceiptGenerationService::generateReceipt($donation, $user);
            return $this->successResponse($receipt, 'Receipt generated successfully.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 3. Income Routes
    // ==========================================
    public function listIncome(Request $request)
    {
        $user = $request->user();
        $query = IncomeRecord::query()->with(['category', 'member', 'family', 'church']);

        if (!$this->hasDioceseFinanceAccess($user)) {
            $query = ChurchAccessService::scopeQuery($user, $query);
        }

        $income = $query->orderBy('income_date', 'desc')->get();
        return $this->successResponse($income, 'Income records retrieved.');
    }

    public function storeIncome(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_income')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'nullable|exists:churches,id',
            'finance_category_id' => 'required|exists:finance_categories,id',
            'source_type' => 'nullable|string',
            'source_id' => 'nullable|integer',
            'family_id' => 'nullable|exists:families,id',
            'member_id' => 'nullable|exists:members,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:3',
            'payment_method' => 'required|string|in:cash,bank_transfer,card,paypal,sepa,other',
            'payment_reference' => 'nullable|string',
            'income_date' => 'required|date',
            'status' => 'nullable|string|in:draft,submitted',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $churchId = $request->input('church_id');
            $this->checkChurchAccess($user, $churchId);

            $income = IncomeService::createIncome($request->all(), $user);
            return $this->successResponse($income, 'Income record created.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function showIncome(Request $request, $id)
    {
        try {
            $income = IncomeRecord::with(['category', 'member', 'family', 'receipt'])->findOrFail($id);
            $this->checkChurchAccess($request->user(), $income->church_id);
            return $this->successResponse($income, 'Income record details.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateIncome(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_income')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $income = IncomeRecord::findOrFail($id);
            $this->checkChurchAccess($user, $income->church_id);

            if ($income->status !== 'draft') {
                return $this->errorResponse('Only draft income records can be updated.', 422);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'income_date' => 'required|date',
                'payment_method' => 'required|string|in:cash,bank_transfer,card,paypal,sepa,other',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), 422);
            }

            $oldValues = $income->toArray();
            $income->update($request->only(['title', 'description', 'amount', 'income_date', 'payment_method', 'payment_reference']));

            AuditLogService::log(
                'Finance',
                'Income Updated',
                "Updated income record ID {$income->id}",
                $oldValues,
                $income->toArray(),
                $income,
                $income->church_id,
                $income->diocese_id
            );

            return $this->successResponse($income, 'Income record updated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function submitIncome(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_income')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $income = IncomeRecord::findOrFail($id);
            $this->checkChurchAccess($user, $income->church_id);

            $income = IncomeService::submitIncome($income, $user);
            return $this->successResponse($income, 'Income record submitted for approval.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function approveIncome(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('approve_income')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $income = IncomeRecord::findOrFail($id);
            $this->checkChurchAccess($user, $income->church_id);

            $income = IncomeService::approveIncome($income, $request->input('remarks'), $user);
            return $this->successResponse($income, 'Income record approved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function rejectIncome(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('approve_income')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $income = IncomeRecord::findOrFail($id);
            $this->checkChurchAccess($user, $income->church_id);

            $income = IncomeService::rejectIncome($income, $request->input('reason'), $user);
            return $this->successResponse($income, 'Income record rejected.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function markIncomeReceived(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('approve_income') && !$user->hasPermissionTo('manage_income')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $income = IncomeRecord::findOrFail($id);
            $this->checkChurchAccess($user, $income->church_id);

            $income = IncomeService::markReceived($income, $user);
            return $this->successResponse($income, 'Income marked received and receipt generated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function generateIncomeReceipt(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('generate_receipts')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $income = IncomeRecord::findOrFail($id);
            $this->checkChurchAccess($user, $income->church_id);

            $receipt = ReceiptGenerationService::generateReceipt($income, $user);
            return $this->successResponse($receipt, 'Receipt generated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 4. Expense Routes
    // ==========================================
    public function listExpenses(Request $request)
    {
        $user = $request->user();
        $query = ExpenseRecord::query()->with(['category', 'church']);

        if (!$this->hasDioceseFinanceAccess($user)) {
            $query = ChurchAccessService::scopeQuery($user, $query);
        }

        $expenses = $query->orderBy('expense_date', 'desc')->get();
        return $this->successResponse($expenses, 'Expense records retrieved.');
    }

    public function storeExpense(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_expenses')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'diocese_id' => 'required|exists:dioceses,id',
            'church_id' => 'nullable|exists:churches,id',
            'finance_category_id' => 'required|exists:finance_categories,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:3',
            'expense_date' => 'required|date',
            'payment_method' => 'required|string|in:cash,bank_transfer,card,paypal,sepa,other',
            'vendor_name' => 'nullable|string',
            'bill_number' => 'nullable|string',
            'bill_file' => 'nullable|file|mimes:jpeg,png,pdf,jpg|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $churchId = $request->input('church_id');
            $this->checkChurchAccess($user, $churchId);

            $expense = ExpenseService::createExpense($request->all(), $request->file('bill_file'), $user);
            return $this->successResponse($expense, 'Expense record created.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function showExpense(Request $request, $id)
    {
        try {
            $expense = ExpenseRecord::with(['category', 'church'])->findOrFail($id);
            $this->checkChurchAccess($request->user(), $expense->church_id);
            return $this->successResponse($expense, 'Expense details.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateExpense(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_expenses')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $expense = ExpenseRecord::findOrFail($id);
            $this->checkChurchAccess($user, $expense->church_id);

            if ($expense->status !== 'draft') {
                return $this->errorResponse('Only draft expenses can be updated.', 422);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'expense_date' => 'required|date',
            ]);

            if ($validator->fails()) {
                return $this->errorResponse($validator->errors()->first(), 422);
            }

            $oldValues = $expense->toArray();
            $expense->update($request->only(['title', 'description', 'amount', 'expense_date', 'payment_method', 'vendor_name', 'bill_number']));

            AuditLogService::log(
                'Finance',
                'Expense Updated',
                "Updated expense record ID {$expense->id}",
                $oldValues,
                $expense->toArray(),
                $expense,
                $expense->church_id,
                $expense->diocese_id
            );

            return $this->successResponse($expense, 'Expense record updated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function submitExpense(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_expenses')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $expense = ExpenseRecord::findOrFail($id);
            $this->checkChurchAccess($user, $expense->church_id);

            $expense = ExpenseService::submitExpense($expense, $user);
            return $this->successResponse($expense, 'Expense record submitted.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function approveExpense(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('approve_expenses')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $expense = ExpenseRecord::findOrFail($id);
            $this->checkChurchAccess($user, $expense->church_id);

            $expense = ExpenseService::approveExpense($expense, $request->input('remarks'), $user);
            return $this->successResponse($expense, 'Expense approved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function rejectExpense(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('approve_expenses')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $expense = ExpenseRecord::findOrFail($id);
            $this->checkChurchAccess($user, $expense->church_id);

            $expense = ExpenseService::rejectExpense($expense, $request->input('reason'), $user);
            return $this->successResponse($expense, 'Expense rejected.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function markExpensePaid(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('mark_expense_paid')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $expense = ExpenseRecord::findOrFail($id);
            $this->checkChurchAccess($user, $expense->church_id);

            $expense = ExpenseService::markPaid($expense, $user);
            return $this->successResponse($expense, 'Expense marked paid.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function cancelExpense(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_expenses')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $expense = ExpenseRecord::findOrFail($id);
            $this->checkChurchAccess($user, $expense->church_id);

            $expense = ExpenseService::cancelExpense($expense, $user);
            return $this->successResponse($expense, 'Expense cancelled.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function downloadBill(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('view_finance_reports') && !$user->hasPermissionTo('manage_expenses')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $expense = ExpenseRecord::findOrFail($id);
            $this->checkChurchAccess($user, $expense->church_id);

            if (!$expense->bill_path) {
                return $this->errorResponse('No bill document found.', 404);
            }

            if (!Storage::exists($expense->bill_path)) {
                return $this->errorResponse('File not found in storage.', 404);
            }

            // Audit Log download
            AuditLogService::log(
                'Finance',
                'Bill Downloaded',
                "Downloaded bill document for expense ID {$expense->id}",
                null,
                ['bill_path' => $expense->bill_path],
                null,
                $expense->church_id,
                $expense->diocese_id
            );

            return Storage::download($expense->bill_path);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // ==========================================
    // 5. Receipt Routes
    // ==========================================
    public function listReceipts(Request $request)
    {
        $user = $request->user();
        $query = FinanceReceipt::query()->with(['incomeHeader.church', 'member']);

        if (!$this->hasDioceseFinanceAccess($user)) {
            $query = ChurchAccessService::scopeQuery($user, $query);
        }

        $receipts = $query->orderBy('receipt_date', 'desc')->get();
        return $this->successResponse($receipts, 'Receipts list retrieved.');
    }

    public function showReceipt(Request $request, $id)
    {
        try {
            $receipt = FinanceReceipt::with(['incomeHeader.church', 'member', 'lines.incomeHead'])->findOrFail($id);
            $churchId = $receipt->incomeHeader ? $receipt->incomeHeader->church_id : null;
            $this->checkChurchAccess($request->user(), $churchId);
            return $this->successResponse($receipt, 'Receipt details retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function downloadReceipt(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('generate_receipts') && !$user->hasPermissionTo('view_finance_reports')) {
            return $this->errorResponse('Access Denied', 403);
        }

        try {
            $receipt = FinanceReceipt::findOrFail($id);
            $churchId = $receipt->incomeHeader ? $receipt->incomeHeader->church_id : null;
            $this->checkChurchAccess($user, $churchId);

            $pdfPath = "private/receipts/{$receipt->receipt_number}.pdf";

            if (!$pdfPath || !Storage::exists($pdfPath)) {
                return $this->errorResponse('Receipt PDF file not found.', 404);
            }

            // Audit Log download
            AuditLogService::log(
                'Finance',
                'Receipt Downloaded',
                "Downloaded receipt PDF {$receipt->receipt_number}",
                null,
                ['pdf_path' => $pdfPath],
                $receipt,
                $churchId,
                $receipt->incomeHeader?->church?->diocese_id ?? $user->default_diocese_id ?? 1
            );

            return Storage::download($pdfPath);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function cancelReceipt(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('cancel_receipts')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $receipt = FinanceReceipt::findOrFail($id);
            $churchId = $receipt->incomeHeader ? $receipt->incomeHeader->church_id : null;
            $this->checkChurchAccess($user, $churchId);

            if ($receipt->status === 'cancelled') {
                return $this->errorResponse('Receipt is already cancelled.', 422);
            }

            \Illuminate\Support\Facades\DB::transaction(function () use ($receipt, $request, $user) {
                $receipt->update([
                    'status' => 'cancelled',
                ]);

                // Post reversal journal batch and ledger entries!
                \App\Services\LedgerPostingService::reverseReceipt($receipt, $user);
            });

            AuditLogService::log(
                'Finance',
                'Receipt Cancelled',
                "Cancelled receipt {$receipt->receipt_number}",
                null,
                $receipt->toArray(),
                $receipt,
                $churchId,
                $receipt->incomeHeader?->church?->diocese_id ?? $user->default_diocese_id ?? 1
            );

            return $this->successResponse($receipt, 'Receipt cancelled.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    // ==========================================
    // 6. Finance Approval Routes
    // ==========================================
    public function listApprovals(Request $request)
    {
        $user = $request->user();
        $query = FinanceApproval::query()->with(['requester', 'approver', 'church']);

        if (!$this->hasDioceseFinanceAccess($user)) {
            $query = ChurchAccessService::scopeQuery($user, $query);
        }

        $approvals = $query->orderBy('created_at', 'desc')->get();
        return $this->successResponse($approvals, 'Approvals list retrieved.');
    }

    public function approveApproval(Request $request, $id)
    {
        $user = $request->user();
        try {
            $approval = FinanceApproval::findOrFail($id);
            $this->checkChurchAccess($user, $approval->church_id);

            // Dynamically trigger approval on actual service layer based on class type
            $record = $approval->approvable;
            if (!$record) {
                return $this->errorResponse('Associated record not found.', 404);
            }

            $remarks = $request->input('remarks');

            if ($record instanceof IncomeRecord) {
                if (!$user->hasPermissionTo('approve_income')) {
                    return $this->errorResponse('Unauthorized to approve income.', 403);
                }
                IncomeService::approveIncome($record, $remarks, $user);
            } elseif ($record instanceof ExpenseRecord) {
                if (!$user->hasPermissionTo('approve_expenses')) {
                    return $this->errorResponse('Unauthorized to approve expenses.', 403);
                }
                ExpenseService::approveExpense($record, $remarks, $user);
            } elseif ($record instanceof Donation) {
                if (!$user->hasPermissionTo('approve_donations')) {
                    return $this->errorResponse('Unauthorized to approve donations.', 403);
                }
                DonationService::markReceived($record, $user);
            } else {
                return $this->errorResponse('Unsupported approvable type.', 422);
            }

            $approval->refresh();
            return $this->successResponse($approval, 'Approval request approved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function rejectApproval(Request $request, $id)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $approval = FinanceApproval::findOrFail($id);
            $this->checkChurchAccess($user, $approval->church_id);

            $record = $approval->approvable;
            if (!$record) {
                return $this->errorResponse('Associated record not found.', 404);
            }

            $reason = $request->input('reason');

            if ($record instanceof IncomeRecord) {
                if (!$user->hasPermissionTo('approve_income')) {
                    return $this->errorResponse('Unauthorized to reject income.', 403);
                }
                IncomeService::rejectIncome($record, $reason, $user);
            } elseif ($record instanceof ExpenseRecord) {
                if (!$user->hasPermissionTo('approve_expenses')) {
                    return $this->errorResponse('Unauthorized to reject expenses.', 403);
                }
                ExpenseService::rejectExpense($record, $reason, $user);
            } else {
                return $this->errorResponse('Unsupported approvable type for rejection.', 422);
            }

            $approval->refresh();
            return $this->successResponse($approval, 'Approval request rejected.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 7. Finance Report Routes
    // ==========================================
    public function reportsSummary(Request $request)
    {
        $user = $request->user();
        $churchId = $request->query('church_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        try {
            // If they are not diocese level, override churchId with their accessible church
            if (!$this->hasDioceseFinanceAccess($user)) {
                $accessible = ChurchAccessService::getAccessibleChurchIds($user);
                $churchId = count($accessible) > 0 ? $accessible[0] : -1;
            } else {
                $churchId = $churchId ? (int)$churchId : null;
            }

            $metrics = FinanceReportService::getSummaryMetrics($churchId, $startDate, $endDate, $user);
            return $this->successResponse($metrics, 'Finance summary metrics retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function reportsByChurch(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('view_all_church_finance') && !$this->hasDioceseFinanceAccess($user)) {
            return $this->errorResponse('Access Denied', 403);
        }

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        try {
            $report = FinanceReportService::getDioceseConsolidatedReport($startDate, $endDate, $user);
            return $this->successResponse($report, 'Parish-wise consolidated reports retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function reportsByCategory(Request $request)
    {
        $user = $request->user();
        $churchId = $request->query('church_id');
        $startDate = $request->query('start_date') ?? Carbon::now()->startOfMonth()->toDateString();
        $endDate = $request->query('end_date') ?? Carbon::now()->endOfMonth()->toDateString();

        try {
            if (!$this->hasDioceseFinanceAccess($user)) {
                $accessible = ChurchAccessService::getAccessibleChurchIds($user);
                $churchId = count($accessible) > 0 ? $accessible[0] : -1;
            } else {
                $churchId = $churchId ? (int)$churchId : null;
            }

            // Aggregate breakdown by categories
            $donationsBreakdown = Donation::where('status', 'received')
                ->whereBetween('received_date', [$startDate, $endDate]);
            if ($churchId !== null) {
                $donationsBreakdown->where('church_id', $churchId);
            }
            $donBreakdown = $donationsBreakdown->select('finance_category_id', \DB::raw('SUM(amount) as total'))
                ->groupBy('finance_category_id')
                ->with('category')
                ->get()
                ->map(fn($item) => [
                    'category_name' => $item->category?->name ?? 'General Donation',
                    'category_type' => 'donation',
                    'total' => (float)$item->total
                ]);

            $incomesBreakdown = IncomeRecord::whereIn('status', ['received', 'approved'])
                ->whereBetween('income_date', [$startDate, $endDate]);
            if ($churchId !== null) {
                $incomesBreakdown->where('church_id', $churchId);
            }
            $incBreakdown = $incomesBreakdown->select('finance_category_id', \DB::raw('SUM(amount) as total'))
                ->groupBy('finance_category_id')
                ->with('category')
                ->get()
                ->map(fn($item) => [
                    'category_name' => $item->category?->name ?? 'Other Income',
                    'category_type' => $item->category?->category_type ?? 'income',
                    'total' => (float)$item->total
                ]);

            $expensesBreakdown = ExpenseRecord::whereIn('status', ['approved', 'paid'])
                ->whereBetween('expense_date', [$startDate, $endDate]);
            if ($churchId !== null) {
                $expensesBreakdown->where('church_id', $churchId);
            }
            $expBreakdown = $expensesBreakdown->select('finance_category_id', \DB::raw('SUM(amount) as total'))
                ->groupBy('finance_category_id')
                ->with('category')
                ->get()
                ->map(fn($item) => [
                    'category_name' => $item->category?->name ?? 'Other Expense',
                    'category_type' => 'expense',
                    'total' => (float)$item->total
                ]);

            $allBreakdown = $donBreakdown->concat($incBreakdown)->concat($expBreakdown);

            return $this->successResponse($allBreakdown, 'Category breakdown report retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function reportsMonthly(Request $request)
    {
        $user = $request->user();
        $churchId = $request->query('church_id');
        $year = $request->query('year') ? (int)$request->query('year') : (int)date('Y');

        try {
            if (!$this->hasDioceseFinanceAccess($user)) {
                $accessible = ChurchAccessService::getAccessibleChurchIds($user);
                $churchId = count($accessible) > 0 ? $accessible[0] : -1;
            } else {
                $churchId = $churchId ? (int)$churchId : null;
            }

            $report = FinanceReportService::getMonthlyReport($churchId, $year, $user);
            return $this->successResponse($report, 'Monthly trend report retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function reportsExport(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('export_finance_reports')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $reportType = $request->query('report_type') ?? 'summary';
        $churchId = $request->query('church_id');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        try {
            if (!$this->hasDioceseFinanceAccess($user)) {
                $accessible = ChurchAccessService::getAccessibleChurchIds($user);
                $churchId = count($accessible) > 0 ? $accessible[0] : -1;
                if ($reportType === 'consolidated') {
                    return $this->errorResponse('Consolidated reports can only be exported by Diocese officials.', 403);
                }
            } else {
                $churchId = $churchId ? (int)$churchId : null;
            }

            $csv = FinanceReportService::exportToCsv($reportType, $churchId, $startDate, $endDate, $user);
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="finance_report_' . $reportType . '.csv"');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 8. Manual Payment Integration
    // ==========================================
    public function linkRegistrationPayment(Request $request)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_income')) {
            return $this->errorResponse('Access Denied', 403);
        }

        $validator = Validator::make($request->all(), [
            'source_type' => 'required|string|in:course_registration,event_registration',
            'source_id' => 'required|integer',
            'amount' => 'required|numeric|min:0.00',
            'payment_method' => 'required|string',
            'payment_reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $sourceType = $request->input('source_type');
            $sourceId = $request->input('source_id');

            // Find registration record dynamically
            if ($sourceType === 'course_registration') {
                $registration = \App\Models\CourseRegistration::findOrFail($sourceId);
            } else {
                $registration = \App\Models\EventRegistration::findOrFail($sourceId);
            }

            // Scoping access control
            $churchId = $registration->church_id ?? $registration->member?->church_id ?? $registration->family?->church_id ?? null;
            $this->checkChurchAccess($user, $churchId);

            $income = IncomeService::linkRegistrationPayment(
                $sourceType,
                $registration,
                (float)$request->input('amount'),
                $request->input('payment_method'),
                $request->input('payment_reference'),
                $user
            );

            return $this->successResponse($income, 'Registration manual payment linked successfully to finance.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 9. Chart of Accounts & Masters
    // ==========================================
    public function getCOA(Request $request)
    {
        try {
            $coa = \App\Services\FinanceAccountService::getCOA();
            return $this->successResponse($coa, 'Chart of Accounts retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function listIncomeHeads(Request $request)
    {
        try {
            $heads = \App\Services\FinanceAccountService::getIncomeHeads();
            return $this->successResponse($heads, 'Income heads retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function storeIncomeHead(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'code' => 'required|string|unique:finance_income_heads,code|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
            'member_default' => 'nullable|boolean',
            'parent_id' => 'nullable|exists:finance_income_heads,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $revenueAccount = \App\Models\FinanceChartAccount::where('code', '4000')->first();
            if (!$revenueAccount) {
                throw new \Exception("Default Revenue Account (4000) not found.");
            }

            $head = \App\Models\FinanceIncomeHead::create([
                'chart_account_id' => $revenueAccount->id,
                'parent_id' => $request->input('parent_id'),
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'member_default' => $request->input('member_default', true),
                'is_active' => true,
            ]);

            return $this->successResponse($head, 'Income head created successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function listExpenseHeads(Request $request)
    {
        try {
            $heads = \App\Services\FinanceAccountService::getExpenseHeads();
            return $this->successResponse($heads, 'Expense heads retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function storeExpenseHead(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'code' => 'required|string|unique:finance_expense_heads,code|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $expenseAccount = \App\Models\FinanceChartAccount::where('code', '5000')->first();
            if (!$expenseAccount) {
                throw new \Exception("Default Expense Account (5000) not found.");
            }

            $head = \App\Models\FinanceExpenseHead::create([
                'chart_account_id' => $expenseAccount->id,
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'is_active' => true,
            ]);

            return $this->successResponse($head, 'Expense head created successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function listFundClasses(Request $request)
    {
        try {
            $funds = \App\Services\FinanceAccountService::getFundClasses();
            return $this->successResponse($funds, 'Fund classes retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function listProgrammeAccounts(Request $request)
    {
        try {
            $churchId = $request->query('church_id');
            $programmes = \App\Services\FinanceAccountService::getProgrammeAccounts($churchId ? (int)$churchId : null);
            return $this->successResponse($programmes, 'Programme accounts retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function storeProgrammeAccount(Request $request)
    {
        $user = $request->user();
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'church_id' => 'nullable|exists:churches,id',
            'code' => 'required|string|max:50',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $churchId = $request->input('church_id') ? (int)$request->input('church_id') : null;
            $this->checkChurchAccess($user, $churchId);

            $exists = \App\Models\FinanceProgrammeAccount::where('church_id', $churchId)
                ->where('code', $request->input('code'))
                ->exists();
            if ($exists) {
                return $this->errorResponse('A programme account with this code already exists for this parish/church.', 422);
            }

            $prog = \App\Models\FinanceProgrammeAccount::create([
                'church_id' => $churchId,
                'code' => $request->input('code'),
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'start_date' => $request->input('start_date'),
                'end_date' => $request->input('end_date'),
                'is_active' => true,
            ]);

            return $this->successResponse($prog, 'Programme account created successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 10. Money Accounts & Balances
    // ==========================================
    public function listMoneyAccounts(Request $request)
    {
        try {
            $churchId = $request->query('church_id');
            $accounts = \App\Services\MoneyAccountService::getMoneyAccounts($churchId ? (int)$churchId : null);
            return $this->successResponse($accounts, 'Money accounts retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function listMoneyAccountBalances(Request $request)
    {
        try {
            $churchId = $request->query('church_id');
            $balances = \App\Services\MoneyAccountService::getBalances($churchId ? (int)$churchId : null);
            return $this->successResponse($balances, 'Money account balances retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 11. Income Entry (Header & Lines)
    // ==========================================
    public function listIncomeHeaders(Request $request)
    {
        try {
            $query = \App\Models\FinanceIncomeHeader::with('lines.incomeHead', 'moneyAccount', 'church');
            if (!$this->hasDioceseFinanceAccess($request->user())) {
                $query = ChurchAccessService::scopeQuery($request->user(), $query);
            }
            $headers = $query->orderBy('income_date', 'desc')->get();
            return $this->successResponse($headers, 'Income headers retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function storeIncomeHeader(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'church_id' => 'nullable|exists:churches,id',
            'income_date' => 'required|date',
            'money_account_id' => 'required|exists:finance_money_accounts,id',
            'lines' => 'required|array|min:1',
            'lines.*.income_head_id' => 'required|exists:finance_income_heads,id',
            'lines.*.fund_class_id' => 'required|exists:finance_fund_classes,id',
            'lines.*.amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $this->checkChurchAccess($user, $request->input('church_id'));
            $header = \App\Services\IncomeEntryService::createIncome(
                $request->only(['church_id', 'income_date', 'money_account_id', 'reference_no', 'remarks']),
                $request->input('lines'),
                $user
            );
            return $this->successResponse($header, 'Income entry created successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function showIncomeHeader(Request $request, $id)
    {
        try {
            $header = \App\Models\FinanceIncomeHeader::with('lines.incomeHead', 'moneyAccount')->findOrFail($id);
            $this->checkChurchAccess($request->user(), $header->church_id);
            return $this->successResponse($header, 'Income entry details.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), $e->getCode() ?: 500);
        }
    }

    public function updateIncomeHeader(Request $request, $id)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'income_date' => 'required|date',
            'money_account_id' => 'required|exists:finance_money_accounts,id',
            'lines' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $header = \App\Models\FinanceIncomeHeader::findOrFail($id);
            $this->checkChurchAccess($user, $header->church_id);
            
            $updated = \App\Services\IncomeEntryService::updateIncome(
                $id,
                $request->only(['income_date', 'money_account_id', 'reference_no', 'remarks']),
                $request->input('lines'),
                $user
            );
            return $this->successResponse($updated, 'Income entry updated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function confirmIncomeHeader(Request $request, $id)
    {
        try {
            $header = \App\Models\FinanceIncomeHeader::findOrFail($id);
            $this->checkChurchAccess($request->user(), $header->church_id);
            
            $confirmed = \App\Services\IncomeEntryService::confirmIncome($id, $request->user());
            return $this->successResponse($confirmed, 'Income entry confirmed and posted to ledger.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 12. Expense Entry (Header & Lines)
    // ==========================================
    public function listExpenseHeaders(Request $request)
    {
        try {
            $query = \App\Models\FinanceExpenseHeader::with('lines.expenseHead', 'moneyAccount', 'church');
            if (!$this->hasDioceseFinanceAccess($request->user())) {
                $query = ChurchAccessService::scopeQuery($request->user(), $query);
            }
            $headers = $query->orderBy('expense_date', 'desc')->get();
            return $this->successResponse($headers, 'Expense headers retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function storeExpenseHeader(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'church_id' => 'nullable|exists:churches,id',
            'expense_date' => 'required|date',
            'money_account_id' => 'required|exists:finance_money_accounts,id',
            'lines' => 'required|array|min:1',
            'lines.*.expense_head_id' => 'required|exists:finance_expense_heads,id',
            'lines.*.fund_class_id' => 'required|exists:finance_fund_classes,id',
            'lines.*.amount' => 'required|numeric|min:0.01',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $this->checkChurchAccess($user, $request->input('church_id'));
            $header = \App\Services\ExpenseEntryService::createExpense(
                $request->only(['church_id', 'expense_date', 'money_account_id', 'payee_name', 'remarks']),
                $request->input('lines'),
                $request->file('bill_file'),
                $user
            );
            return $this->successResponse($header, 'Expense voucher created successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function showExpenseHeader(Request $request, $id)
    {
        try {
            $header = \App\Models\FinanceExpenseHeader::with('lines.expenseHead', 'moneyAccount')->findOrFail($id);
            $this->checkChurchAccess($request->user(), $header->church_id);
            return $this->successResponse($header, 'Expense voucher details.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function updateExpenseHeader(Request $request, $id)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'expense_date' => 'required|date',
            'money_account_id' => 'required|exists:finance_money_accounts,id',
            'lines' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $header = \App\Models\FinanceExpenseHeader::findOrFail($id);
            $this->checkChurchAccess($user, $header->church_id);
            
            $updated = \App\Services\ExpenseEntryService::updateExpense(
                $id,
                $request->only(['expense_date', 'money_account_id', 'payee_name', 'remarks']),
                $request->input('lines'),
                $request->file('bill_file'),
                $user
            );
            return $this->successResponse($updated, 'Expense voucher updated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function payExpenseHeader(Request $request, $id)
    {
        try {
            $header = \App\Models\FinanceExpenseHeader::findOrFail($id);
            $this->checkChurchAccess($request->user(), $header->church_id);
            
            $paid = \App\Services\ExpenseEntryService::payExpense($id, $request->user());
            return $this->successResponse($paid, 'Expense voucher paid and posted to ledger.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 13. Priest Payments
    // ==========================================
    public function listPriestPayments(Request $request)
    {
        try {
            $query = \App\Models\FinancePriestPayment::with('priest', 'church');
            if (!$this->hasDioceseFinanceAccess($request->user())) {
                $query = ChurchAccessService::scopeQuery($request->user(), $query);
            }
            $payments = $query->orderBy('payment_date', 'desc')->get();
            return $this->successResponse($payments, 'Priest payments list retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function storePriestPayment(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'church_id' => 'nullable|exists:churches,id',
            'priest_id' => 'required|exists:priest_profiles,id',
            'payment_date' => 'required|date',
            'type' => 'required|string|in:stipend,allowance,travel',
            'amount' => 'nullable|numeric|min:0.01',
            'travel_distance_km' => 'nullable|numeric',
            'travel_rate_per_km' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $this->checkChurchAccess($user, $request->input('church_id'));
            $payment = \App\Services\PriestPaymentService::createPaymentClaim($request->all(), $user);
            return $this->successResponse($payment, 'Priest payment claim created successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function confirmPriestPayment(Request $request, $id)
    {
        try {
            $payment = \App\Models\FinancePriestPayment::findOrFail($id);
            $this->checkChurchAccess($request->user(), $payment->church_id);
            
            $confirmed = \App\Services\PriestPaymentService::confirmPaymentClaim($id, $request->user());
            return $this->successResponse($confirmed, 'Priest payment claim confirmed and voucher generated.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 14. Cash Batches
    // ==========================================
    public function listCashBatches(Request $request)
    {
        try {
            $query = \App\Models\FinanceCashBatch::with('opener', 'closer', 'moneyAccount', 'church');
            if (!$this->hasDioceseFinanceAccess($request->user())) {
                $query = ChurchAccessService::scopeQuery($request->user(), $query);
            }
            $batches = $query->orderBy('opened_at', 'desc')->get();
            return $this->successResponse($batches, 'Cash batches retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function openCashBatch(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'church_id' => 'required|exists:churches,id',
            'money_account_id' => 'required|exists:finance_money_accounts,id',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $this->checkChurchAccess($request->user(), $request->input('church_id'));
            $batch = \App\Services\CashBatchService::openBatch(
                (int)$request->input('church_id'),
                (int)$request->input('money_account_id'),
                $request->user()
            );
            return $this->successResponse($batch, 'Cash batch opened successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function closeCashBatch(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'counting_details' => 'required|array',
            'declared_amount' => 'required|numeric|min:0.00',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $batch = \App\Models\FinanceCashBatch::findOrFail($id);
            $this->checkChurchAccess($request->user(), $batch->church_id);
            
            $closed = \App\Services\CashBatchService::closeBatch(
                $id,
                $request->input('counting_details'),
                (float)$request->input('declared_amount'),
                $request->user()
            );
            return $this->successResponse($closed, 'Cash batch closed and reconciled.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 15. Money Transfers
    // ==========================================
    public function listTransfers(Request $request)
    {
        try {
            $query = \App\Models\FinanceTransfer::with('fromAccount', 'toAccount', 'church');
            if (!$this->hasDioceseFinanceAccess($request->user())) {
                $query = ChurchAccessService::scopeQuery($request->user(), $query);
            }
            $transfers = $query->orderBy('transfer_date', 'desc')->get();
            return $this->successResponse($transfers, 'Transfers retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function storeTransfer(Request $request)
    {
        $user = $request->user();
        $validator = Validator::make($request->all(), [
            'church_id' => 'nullable|exists:churches,id',
            'transfer_date' => 'required|date',
            'from_account_id' => 'required|exists:finance_money_accounts,id',
            'to_account_id' => 'required|exists:finance_money_accounts,id',
            'amount' => 'required|numeric|min:0.01',
            'reference' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $this->checkChurchAccess($user, $request->input('church_id'));
            $transfer = \App\Services\FinanceTransferService::createTransfer($request->all(), $user);
            return $this->successResponse($transfer, 'Transfer draft created.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function confirmTransfer(Request $request, $id)
    {
        try {
            $transfer = \App\Models\FinanceTransfer::findOrFail($id);
            $this->checkChurchAccess($request->user(), $transfer->church_id);
            
            $confirmed = \App\Services\FinanceTransferService::confirmTransfer($id, $request->user());
            return $this->successResponse($confirmed, 'Transfer confirmed and posted to ledger.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 16. Bank Statement Imports & Reconciliation
    // ==========================================
    public function importBankStatement(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'money_account_id' => 'required|exists:finance_money_accounts,id',
            'statement_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $account = \App\Models\FinanceMoneyAccount::findOrFail($request->input('money_account_id'));
            $this->checkChurchAccess($request->user(), $account->church_id);
            
            $import = \App\Services\BankReconciliationService::importStatement(
                $account->id,
                $request->file('statement_file'),
                $request->user()
            );
            return $this->successResponse($import, 'Bank statement imported successfully.', 201);
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function listBankStatementLines(Request $request)
    {
        try {
            $moneyAccountId = $request->query('money_account_id');
            $query = \App\Models\FinanceBankStatementLine::with('import');
            
            if ($moneyAccountId) {
                $query->whereHas('import', function($q) use ($moneyAccountId) {
                    $q->where('money_account_id', $moneyAccountId);
                });
            }

            $lines = $query->orderBy('booking_date', 'desc')->get();
            return $this->successResponse($lines, 'Statement lines retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    public function matchBankStatementLine(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'matchable_type' => 'required|string|in:App\Models\FinanceIncomeHeader,App\Models\FinanceExpenseHeader,App\Models\FinanceTransfer',
            'matchable_id' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse($validator->errors()->first(), 422);
        }

        try {
            $line = \App\Models\FinanceBankStatementLine::findOrFail($id);
            
            $match = \App\Services\BankReconciliationService::matchStatementLine(
                $id,
                $request->input('matchable_type'),
                (int)$request->input('matchable_id'),
                $request->user()
            );
            return $this->successResponse($match, 'Statement line matched successfully.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    // ==========================================
    // 17. Double Entry Ledger List
    // ==========================================
    public function listLedgerEntries(Request $request)
    {
        try {
            $query = \App\Models\FinanceLedgerEntry::with('journalBatch', 'chartAccount', 'fundClass', 'programmeAccount');
            if (!$this->hasDioceseFinanceAccess($request->user())) {
                $query->whereHas('journalBatch', function($q) use ($request) {
                    $q->where('church_id', $request->user()->church_id);
                });
            }
            $entries = $query->orderBy('entry_date', 'desc')->get();
            return $this->successResponse($entries, 'Ledger entries retrieved.');
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }
}

