<?php

namespace App\Services;

use App\Models\FinanceMoneyAccount;
use App\Models\FinanceIncomeHeader;
use App\Models\FinanceExpenseHeader;
use App\Models\FinanceTransfer;
use Illuminate\Support\Facades\DB;

class MoneyAccountService
{
    /**
     * Get money accounts for a church or diocese.
     */
    public static function getMoneyAccounts(?int $churchId = null): array
    {
        $query = FinanceMoneyAccount::where('is_active', true);
        if ($churchId !== null) {
            $query->where('church_id', $churchId);
        } else {
            $query->whereNull('church_id');
        }
        return $query->orderBy('name')->get()->toArray();
    }

    /**
     * Calculate current balance for a money account.
     */
    public static function getAccountBalance(int $accountId): float
    {
        // 1. Confirmed Incomes (+)
        $incomes = DB::table('finance_income_headers')
            ->join('finance_income_lines', 'finance_income_headers.id', '=', 'finance_income_lines.income_header_id')
            ->where('finance_income_headers.money_account_id', $accountId)
            ->where('finance_income_headers.status', 'confirmed')
            ->sum('finance_income_lines.amount');

        // 2. Paid Expenses (-)
        $expenses = DB::table('finance_expense_headers')
            ->join('finance_expense_lines', 'finance_expense_headers.id', '=', 'finance_expense_lines.expense_header_id')
            ->where('finance_expense_headers.money_account_id', $accountId)
            ->whereIn('finance_expense_headers.status', ['paid', 'approved'])
            ->sum('finance_expense_lines.amount');

        // 3. Transfers In (+)
        $transfersIn = FinanceTransfer::where('to_account_id', $accountId)
            ->where('status', 'confirmed')
            ->sum('amount');

        // 4. Transfers Out (-)
        $transfersOut = FinanceTransfer::where('from_account_id', $accountId)
            ->where('status', 'confirmed')
            ->sum('amount');

        return (float)($incomes - $expenses + $transfersIn - $transfersOut);
    }

    /**
     * Get balances for all money accounts of a church or diocese.
     */
    public static function getBalances(?int $churchId = null): array
    {
        $accounts = self::getMoneyAccounts($churchId);
        $balances = [];
        
        foreach ($accounts as $account) {
            $balance = self::getAccountBalance($account['id']);
            $balances[] = array_merge($account, [
                'current_balance' => $balance
            ]);
        }

        return $balances;
    }
}
