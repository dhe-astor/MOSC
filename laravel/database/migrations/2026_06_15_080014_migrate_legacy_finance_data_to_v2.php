<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            // 1. Create default Chart of Accounts if not exist
            $defaultAccounts = [
                ['code' => '1000', 'name' => 'Asset', 'type' => 'asset', 'description' => 'Default Asset Account', 'is_active' => true],
                ['code' => '2000', 'name' => 'Liability', 'type' => 'liability', 'description' => 'Default Liability Account', 'is_active' => true],
                ['code' => '3000', 'name' => 'Equity', 'type' => 'equity', 'description' => 'Default Equity Account', 'is_active' => true],
                ['code' => '4000', 'name' => 'Revenue', 'type' => 'revenue', 'description' => 'Default Revenue Account', 'is_active' => true],
                ['code' => '5000', 'name' => 'Expense', 'type' => 'expense', 'description' => 'Default Expense Account', 'is_active' => true],
            ];

            $accountMap = [];
            foreach ($defaultAccounts as $acc) {
                $existing = DB::table('finance_chart_accounts')->where('code', $acc['code'])->first();
                if ($existing) {
                    $accountMap[$acc['code']] = $existing->id;
                } else {
                    $id = DB::table('finance_chart_accounts')->insertGetId(array_merge($acc, [
                        'created_at' => now(),
                        'updated_at' => now()
                    ]));
                    $accountMap[$acc['code']] = $id;
                }
            }

            $revenueAccountId = $accountMap['4000'];
            $expenseAccountId = $accountMap['5000'];

            // 2. Create default Fund Class GEN if not exists
            $existingFund = DB::table('finance_fund_classes')->where('code', 'GEN')->first();
            if ($existingFund) {
                $generalFundClassId = $existingFund->id;
            } else {
                $generalFundClassId = DB::table('finance_fund_classes')->insertGetId([
                    'code' => 'GEN',
                    'name' => 'General Fund',
                    'description' => 'Default General Fund',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            // Create default fallback income/expense heads
            $fallbackHeads = [
                ['table' => 'finance_income_heads', 'chart_account_id' => $revenueAccountId, 'code' => 'INC-DEFAULT-DONATION', 'name' => 'Default Donation Income Head'],
                ['table' => 'finance_income_heads', 'chart_account_id' => $revenueAccountId, 'code' => 'INC-DEFAULT-INCOME', 'name' => 'Default General Income Head'],
                ['table' => 'finance_expense_heads', 'chart_account_id' => $expenseAccountId, 'code' => 'EXP-DEFAULT-EXPENSE', 'name' => 'Default General Expense Head'],
            ];
            $fallbackHeadIds = [];
            foreach ($fallbackHeads as $fh) {
                $tbl = $fh['table'];
                $existingFh = DB::table($tbl)->where('code', $fh['code'])->first();
                if ($existingFh) {
                    $fallbackHeadIds[$fh['code']] = $existingFh->id;
                } else {
                    $id = DB::table($tbl)->insertGetId([
                        'chart_account_id' => $fh['chart_account_id'],
                        'code' => $fh['code'],
                        'name' => $fh['name'],
                        'description' => 'Default fallback head',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $fallbackHeadIds[$fh['code']] = $id;
                }
            }
            $defaultDonationIncomeHeadId = $fallbackHeadIds['INC-DEFAULT-DONATION'];
            $defaultIncomeIncomeHeadId = $fallbackHeadIds['INC-DEFAULT-INCOME'];
            $defaultExpenseExpenseHeadId = $fallbackHeadIds['EXP-DEFAULT-EXPENSE'];

            // 3. Create cash and bank money account for each church and diocese
            $dioceses = DB::table('dioceses')->get();
            $churches = DB::table('churches')->get();

            $moneyAccountMap = []; // code => id

            foreach ($dioceses as $diocese) {
                $cashCode = "CASH-DIOCESE-{$diocese->id}";
                $bankCode = "BANK-DIOCESE-{$diocese->id}";

                // Cash
                $existingCash = DB::table('finance_money_accounts')->where('code', $cashCode)->first();
                if ($existingCash) {
                    $moneyAccountMap[$cashCode] = $existingCash->id;
                } else {
                    $id = DB::table('finance_money_accounts')->insertGetId([
                        'church_id' => null,
                        'code' => $cashCode,
                        'name' => "Diocese {$diocese->name} Cash Account",
                        'type' => 'cash',
                        'currency' => 'EUR',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $moneyAccountMap[$cashCode] = $id;
                }

                // Bank
                $existingBank = DB::table('finance_money_accounts')->where('code', $bankCode)->first();
                if ($existingBank) {
                    $moneyAccountMap[$bankCode] = $existingBank->id;
                } else {
                    $id = DB::table('finance_money_accounts')->insertGetId([
                        'church_id' => null,
                        'code' => $bankCode,
                        'name' => "Diocese {$diocese->name} Bank Account",
                        'type' => 'bank',
                        'currency' => 'EUR',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $moneyAccountMap[$bankCode] = $id;
                }
            }

            foreach ($churches as $church) {
                $cashCode = "CASH-CHURCH-{$church->id}";
                $bankCode = "BANK-CHURCH-{$church->id}";

                // Cash
                $existingCash = DB::table('finance_money_accounts')->where('code', $cashCode)->first();
                if ($existingCash) {
                    $moneyAccountMap[$cashCode] = $existingCash->id;
                } else {
                    $id = DB::table('finance_money_accounts')->insertGetId([
                        'church_id' => $church->id,
                        'code' => $cashCode,
                        'name' => "Church {$church->name} Cash Account",
                        'type' => 'cash',
                        'currency' => 'EUR',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $moneyAccountMap[$cashCode] = $id;
                }

                // Bank
                $existingBank = DB::table('finance_money_accounts')->where('code', $bankCode)->first();
                if ($existingBank) {
                    $moneyAccountMap[$bankCode] = $existingBank->id;
                } else {
                    $id = DB::table('finance_money_accounts')->insertGetId([
                        'church_id' => $church->id,
                        'code' => $bankCode,
                        'name' => "Church {$church->name} Bank Account",
                        'type' => 'bank',
                        'currency' => 'EUR',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                    $moneyAccountMap[$bankCode] = $id;
                }
            }

            // 4. Map each finance_categories record
            $categories = DB::table('finance_categories')->get();
            $incomeHeadMap = [];
            $expenseHeadMap = [];

            foreach ($categories as $cat) {
                if ($cat->category_type === 'expense') {
                    $code = "EXP-{$cat->id}";
                    $existingHead = DB::table('finance_expense_heads')->where('code', $code)->first();
                    if ($existingHead) {
                        $expenseHeadMap[$cat->id] = $existingHead->id;
                    } else {
                        $id = DB::table('finance_expense_heads')->insertGetId([
                            'chart_account_id' => $expenseAccountId,
                            'code' => $code,
                            'name' => $cat->name,
                            'description' => $cat->description,
                            'is_active' => ($cat->status === 'active'),
                            'created_at' => $cat->created_at ?? now(),
                            'updated_at' => $cat->updated_at ?? now()
                        ]);
                        $expenseHeadMap[$cat->id] = $id;
                    }
                } else {
                    $code = "INC-{$cat->id}";
                    $existingHead = DB::table('finance_income_heads')->where('code', $code)->first();
                    if ($existingHead) {
                        $incomeHeadMap[$cat->id] = $existingHead->id;
                    } else {
                        $id = DB::table('finance_income_heads')->insertGetId([
                            'chart_account_id' => $revenueAccountId,
                            'code' => $code,
                            'name' => $cat->name,
                            'description' => $cat->description,
                            'is_active' => ($cat->status === 'active'),
                            'created_at' => $cat->created_at ?? now(),
                            'updated_at' => $cat->updated_at ?? now()
                        ]);
                        $incomeHeadMap[$cat->id] = $id;
                    }
                }
            }

            // Helper to get money account ID
            $getMoneyAccountId = function ($churchId, $dioceseId, $paymentMethod) use ($moneyAccountMap) {
                $isCash = strtolower($paymentMethod) === 'cash';
                if ($churchId) {
                    $code = $isCash ? "CASH-CHURCH-{$churchId}" : "BANK-CHURCH-{$churchId}";
                } else {
                    $code = $isCash ? "CASH-DIOCESE-{$dioceseId}" : "BANK-DIOCESE-{$dioceseId}";
                }
                return $moneyAccountMap[$code] ?? null;
            };

            // 5. Migrate donations
            $donations = DB::table('donations')->get();
            $donationToLineMap = [];
            $donationToHeaderMap = [];

            foreach ($donations as $donation) {
                $moneyAccountId = $getMoneyAccountId($donation->church_id, $donation->diocese_id, $donation->payment_method);
                if (!$moneyAccountId) {
                    // fallback to any
                    $moneyAccountId = reset($moneyAccountMap);
                }

                $status = 'draft';
                $donationStatus = strtolower($donation->status);
                if (in_array($donationStatus, ['received', 'approved'])) {
                    $status = 'posted';
                } elseif (in_array($donationStatus, ['cancelled', 'failed', 'refunded'])) {
                    $status = 'cancelled';
                }

                $headerId = DB::table('finance_income_headers')->insertGetId([
                    'church_id' => $donation->church_id,
                    'income_date' => $donation->received_date,
                    'money_account_id' => $moneyAccountId,
                    'reference_no' => $donation->payment_reference,
                    'remarks' => $donation->notes,
                    'status' => $status,
                    'created_by' => $donation->created_by,
                    'created_at' => $donation->created_at ?? now(),
                    'updated_at' => $donation->updated_at ?? now()
                ]);

                $lineId = DB::table('finance_income_lines')->insertGetId([
                    'income_header_id' => $headerId,
                    'income_head_id' => $incomeHeadMap[$donation->finance_category_id] ?? $defaultDonationIncomeHeadId,
                    'fund_class_id' => $generalFundClassId,
                    'programme_account_id' => null,
                    'member_id' => $donation->member_id,
                    'donor_name' => $donation->donor_name,
                    'amount' => $donation->amount,
                    'remarks' => $donation->notes,
                    'created_at' => $donation->created_at ?? now(),
                    'updated_at' => $donation->updated_at ?? now()
                ]);

                $donationToLineMap[$donation->id] = $lineId;
                $donationToHeaderMap[$donation->id] = $headerId;
            }

            // 6. Migrate income_records
            $incomes = DB::table('income_records')->get();
            $incomeToLineMap = [];
            $incomeToHeaderMap = [];

            foreach ($incomes as $income) {
                $moneyAccountId = $getMoneyAccountId($income->church_id, $income->diocese_id, $income->payment_method);
                if (!$moneyAccountId) {
                    $moneyAccountId = reset($moneyAccountMap);
                }

                $status = 'draft';
                $incomeStatus = strtolower($income->status);
                if (in_array($incomeStatus, ['received', 'approved'])) {
                    $status = 'posted';
                } elseif (in_array($incomeStatus, ['rejected', 'cancelled'])) {
                    $status = 'cancelled';
                }

                $headerId = DB::table('finance_income_headers')->insertGetId([
                    'church_id' => $income->church_id,
                    'income_date' => $income->income_date,
                    'money_account_id' => $moneyAccountId,
                    'reference_no' => $income->payment_reference,
                    'remarks' => $income->description,
                    'status' => $status,
                    'created_by' => $income->created_by,
                    'created_at' => $income->created_at ?? now(),
                    'updated_at' => $income->updated_at ?? now()
                ]);

                $lineId = DB::table('finance_income_lines')->insertGetId([
                    'income_header_id' => $headerId,
                    'income_head_id' => $incomeHeadMap[$income->finance_category_id] ?? $defaultIncomeIncomeHeadId,
                    'fund_class_id' => $generalFundClassId,
                    'programme_account_id' => null,
                    'member_id' => $income->member_id,
                    'donor_name' => null,
                    'amount' => $income->amount,
                    'remarks' => $income->title . ($income->description ? ' - ' . $income->description : ''),
                    'created_at' => $income->created_at ?? now(),
                    'updated_at' => $income->updated_at ?? now()
                ]);

                $incomeToLineMap[$income->id] = $lineId;
                $incomeToHeaderMap[$income->id] = $headerId;
            }

            // 7. Migrate expense_records
            $expenses = DB::table('expense_records')->get();
            $expenseToLineMap = [];

            foreach ($expenses as $expense) {
                $moneyAccountId = $getMoneyAccountId($expense->church_id, $expense->diocese_id, $expense->payment_method);
                if (!$moneyAccountId) {
                    $moneyAccountId = reset($moneyAccountMap);
                }

                $status = 'draft';
                $expenseStatus = strtolower($expense->status);
                if (in_array($expenseStatus, ['approved', 'paid'])) {
                    $status = 'posted';
                } elseif (in_array($expenseStatus, ['rejected', 'cancelled'])) {
                    $status = 'cancelled';
                }

                $voucherNumber = $expense->bill_number;
                if (empty($voucherNumber) || DB::table('finance_expense_headers')->where('voucher_number', $voucherNumber)->exists()) {
                    $voucherNumber = 'VOUCHER-' . $expense->id;
                }

                $headerId = DB::table('finance_expense_headers')->insertGetId([
                    'church_id' => $expense->church_id,
                    'expense_date' => $expense->expense_date,
                    'money_account_id' => $moneyAccountId,
                    'voucher_number' => $voucherNumber,
                    'reference_no' => $expense->bill_number,
                    'payee_name' => $expense->vendor_name ?? 'Unknown',
                    'remarks' => $expense->description,
                    'status' => $status,
                    'created_by' => $expense->created_by,
                    'created_at' => $expense->created_at ?? now(),
                    'updated_at' => $expense->updated_at ?? now()
                ]);

                $lineId = DB::table('finance_expense_lines')->insertGetId([
                    'expense_header_id' => $headerId,
                    'expense_head_id' => $expenseHeadMap[$expense->finance_category_id] ?? $defaultExpenseExpenseHeadId,
                    'fund_class_id' => $generalFundClassId,
                    'programme_account_id' => null,
                    'amount' => $expense->amount,
                    'remarks' => $expense->title . ($expense->description ? ' - ' . $expense->description : ''),
                    'created_at' => $expense->created_at ?? now(),
                    'updated_at' => $expense->updated_at ?? now()
                ]);

                $expenseToLineMap[$expense->id] = $lineId;
            }

            // 8. Migrate receipts
            $receipts = DB::table('receipts')->get();

            foreach ($receipts as $receipt) {
                $incomeHeaderId = null;
                $incomeLineId = null;
                $recType = $receipt->receiptable_type;

                // Strip namespace if present
                if (str_contains($recType, '\\')) {
                    $parts = explode('\\', $recType);
                    $recType = end($parts);
                }

                if (strtolower($recType) === 'donation') {
                    $incomeHeaderId = $donationToHeaderMap[$receipt->receiptable_id] ?? null;
                    $incomeLineId = $donationToLineMap[$receipt->receiptable_id] ?? null;
                } elseif (strtolower($recType) === 'incomerecord') {
                    $incomeHeaderId = $incomeToHeaderMap[$receipt->receiptable_id] ?? null;
                    $incomeLineId = $incomeToLineMap[$receipt->receiptable_id] ?? null;
                }

                // If we have an income_line_id, lookup its income_head_id
                $incomeHeadId = null;
                if ($incomeLineId) {
                    $line = DB::table('finance_income_lines')->where('id', $incomeLineId)->first();
                    $incomeHeadId = $line ? $line->income_head_id : null;
                }

                if (!$incomeHeadId) {
                    // fallback based on receiptable_type
                    if (strtolower($recType) === 'donation') {
                        $donation = DB::table('donations')->where('id', $receipt->receiptable_id)->first();
                        $catId = $donation ? $donation->finance_category_id : null;
                        $incomeHeadId = $incomeHeadMap[$catId] ?? $defaultDonationIncomeHeadId;
                    } else {
                        $incomeRec = DB::table('income_records')->where('id', $receipt->receiptable_id)->first();
                        $catId = $incomeRec ? $incomeRec->finance_category_id : null;
                        $incomeHeadId = $incomeHeadMap[$catId] ?? $defaultIncomeIncomeHeadId;
                    }
                }

                $status = 'active';
                if (strtolower($receipt->status) === 'cancelled') {
                    $status = 'cancelled';
                }

                $receiptId = DB::table('finance_receipts')->insertGetId([
                    'income_header_id' => $incomeHeaderId,
                    'receipt_number' => $receipt->receipt_number,
                    'receipt_date' => $receipt->receipt_date,
                    'received_from' => $receipt->payer_name,
                    'member_id' => $receipt->member_id,
                    'payment_method' => $receipt->payment_method,
                    'total_amount' => $receipt->amount,
                    'status' => $status,
                    'created_at' => $receipt->created_at ?? now(),
                    'updated_at' => $receipt->updated_at ?? now()
                ]);

                DB::table('finance_receipt_lines')->insert([
                    'receipt_id' => $receiptId,
                    'income_line_id' => $incomeLineId,
                    'income_head_id' => $incomeHeadId,
                    'amount' => $receipt->amount,
                    'description' => $receipt->description,
                    'created_at' => $receipt->created_at ?? now(),
                    'updated_at' => $receipt->updated_at ?? now()
                ]);
            }

            // 9. Assert counts and sums of amounts match exactly
            // Donations count and sum
            $legacyDonationCount = DB::table('donations')->count();
            $legacyDonationSum = DB::table('donations')->sum('amount') ?? 0;
            $newDonationCount = DB::table('finance_income_lines')->whereIn('id', array_values($donationToLineMap))->count();
            $newDonationSum = DB::table('finance_income_lines')->whereIn('id', array_values($donationToLineMap))->sum('amount') ?? 0;

            if ($legacyDonationCount !== $newDonationCount) {
                throw new \Exception("Donation count mismatch: legacy {$legacyDonationCount} vs new {$newDonationCount}");
            }
            if (abs((float)$legacyDonationSum - (float)$newDonationSum) > 0.01) {
                throw new \Exception("Donation sum mismatch: legacy {$legacyDonationSum} vs new {$newDonationSum}");
            }

            // IncomeRecords count and sum
            $legacyIncomeCount = DB::table('income_records')->count();
            $legacyIncomeSum = DB::table('income_records')->sum('amount') ?? 0;
            $newIncomeCount = DB::table('finance_income_lines')->whereIn('id', array_values($incomeToLineMap))->count();
            $newIncomeSum = DB::table('finance_income_lines')->whereIn('id', array_values($incomeToLineMap))->sum('amount') ?? 0;

            if ($legacyIncomeCount !== $newIncomeCount) {
                throw new \Exception("Income count mismatch: legacy {$legacyIncomeCount} vs new {$newIncomeCount}");
            }
            if (abs((float)$legacyIncomeSum - (float)$newIncomeSum) > 0.01) {
                throw new \Exception("Income sum mismatch: legacy {$legacyIncomeSum} vs new {$newIncomeSum}");
            }

            // ExpenseRecords count and sum
            $legacyExpenseCount = DB::table('expense_records')->count();
            $legacyExpenseSum = DB::table('expense_records')->sum('amount') ?? 0;
            $newExpenseCount = DB::table('finance_expense_lines')->whereIn('id', array_values($expenseToLineMap))->count();
            $newExpenseSum = DB::table('finance_expense_lines')->whereIn('id', array_values($expenseToLineMap))->sum('amount') ?? 0;

            if ($legacyExpenseCount !== $newExpenseCount) {
                throw new \Exception("Expense count mismatch: legacy {$legacyExpenseCount} vs new {$newExpenseCount}");
            }
            if (abs((float)$legacyExpenseSum - (float)$newExpenseSum) > 0.01) {
                throw new \Exception("Expense sum mismatch: legacy {$legacyExpenseSum} vs new {$newExpenseSum}");
            }

            // Receipts count and sum
            $legacyReceiptCount = DB::table('receipts')->count();
            $legacyReceiptSum = DB::table('receipts')->sum('amount') ?? 0;
            $newReceiptCount = DB::table('finance_receipts')->count();
            $newReceiptSum = DB::table('finance_receipts')->sum('total_amount') ?? 0;

            if ($legacyReceiptCount !== $newReceiptCount) {
                throw new \Exception("Receipt count mismatch: legacy {$legacyReceiptCount} vs new {$newReceiptCount}");
            }
            if (abs((float)$legacyReceiptSum - (float)$newReceiptSum) > 0.01) {
                throw new \Exception("Receipt sum mismatch: legacy {$legacyReceiptSum} vs new {$newReceiptSum}");
            }

            // 10. Rename legacy tables
            Schema::rename('donations', 'legacy_donations');
            Schema::rename('income_records', 'legacy_income_records');
            Schema::rename('expense_records', 'legacy_expense_records');
            Schema::rename('receipts', 'legacy_receipts');
            Schema::rename('finance_approvals', 'legacy_finance_approvals');
        });
    }

    public function down(): void
    {
        Schema::rename('legacy_donations', 'donations');
        Schema::rename('legacy_income_records', 'income_records');
        Schema::rename('legacy_expense_records', 'expense_records');
        Schema::rename('legacy_receipts', 'receipts');
        Schema::rename('legacy_finance_approvals', 'finance_approvals');
    }
};
