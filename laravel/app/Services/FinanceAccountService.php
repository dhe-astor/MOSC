<?php

namespace App\Services;

use App\Models\FinanceChartAccount;
use App\Models\FinanceIncomeHead;
use App\Models\FinanceExpenseHead;
use App\Models\FinanceFundClass;
use App\Models\FinanceProgrammeAccount;

class FinanceAccountService
{
    /**
     * Get the full Chart of Accounts with linked income/expense heads.
     */
    public static function getCOA(): array
    {
        $accounts = FinanceChartAccount::where('is_active', true)->orderBy('code')->get();
        
        $coa = [];
        foreach ($accounts as $account) {
            $heads = [];
            if ($account->type === 'revenue') {
                $heads = FinanceIncomeHead::where('chart_account_id', $account->id)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get()
                    ->toArray();
            } elseif ($account->type === 'expense') {
                $heads = FinanceExpenseHead::where('chart_account_id', $account->id)
                    ->where('is_active', true)
                    ->orderBy('code')
                    ->get()
                    ->toArray();
            }

            $coa[] = [
                'id' => $account->id,
                'code' => $account->code,
                'name' => $account->name,
                'type' => $account->type,
                'description' => $account->description,
                'heads' => $heads,
            ];
        }

        return $coa;
    }

    /**
     * Get active income heads.
     */
    public static function getIncomeHeads(): array
    {
        return FinanceIncomeHead::with('chartAccount')
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->toArray();
    }

    /**
     * Get active expense heads.
     */
    public static function getExpenseHeads(): array
    {
        return FinanceExpenseHead::with('chartAccount')
            ->where('is_active', true)
            ->orderBy('code')
            ->get()
            ->toArray();
    }

    /**
     * Get active fund classes.
     */
    public static function getFundClasses(): array
    {
        return FinanceFundClass::where('is_active', true)
            ->orderBy('code')
            ->get()
            ->toArray();
    }

    /**
     * Get active programme accounts / cost centres.
     */
    public static function getProgrammeAccounts(?int $churchId = null): array
    {
        $query = FinanceProgrammeAccount::where('is_active', true);
        if ($churchId !== null) {
            $query->where(function ($q) use ($churchId) {
                $q->where('church_id', $churchId)->orWhereNull('church_id');
            });
        }
        return $query->orderBy('code')->get()->toArray();
    }
}
