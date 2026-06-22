<?php

namespace App\Services;

use App\Models\FinanceJournalBatch;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceChartAccount;
use App\Models\User;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceReportService
{
    /**
     * Generate finance summary metrics from the double-entry ledger.
     */
    public static function getSummaryMetrics(?int $churchId, ?string $startDate, ?string $endDate, User $user): array
    {
        $startDate = $startDate ?? Carbon::now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? Carbon::now()->endOfMonth()->toDateString();

        // 1. Total Donations (Revenue entries marked as donations or matching INC-004/INC-005)
        $donationsQuery = DB::table('finance_ledger_entries')
            ->join('finance_journal_batches', 'finance_ledger_entries.journal_batch_id', '=', 'finance_journal_batches.id')
            ->join('finance_chart_accounts', 'finance_ledger_entries.chart_account_id', '=', 'finance_chart_accounts.id')
            ->where('finance_chart_accounts.type', 'revenue')
            ->whereBetween('finance_ledger_entries.entry_date', [$startDate, $endDate])
            ->where(function($q) {
                $q->where('finance_ledger_entries.description', 'LIKE', '%donation%')
                  ->orWhere('finance_ledger_entries.description', 'LIKE', '%Donation%')
                  ->orWhere('finance_journal_batches.reference', 'LIKE', '%DON%');
            });

        if ($churchId !== null) {
            $donationsQuery->where('finance_journal_batches.church_id', $churchId);
        }
        $totalDonations = $donationsQuery->sum('finance_ledger_entries.credit');

        // 2. Total Incomes (All other revenue credits)
        $incomesQuery = DB::table('finance_ledger_entries')
            ->join('finance_journal_batches', 'finance_ledger_entries.journal_batch_id', '=', 'finance_journal_batches.id')
            ->join('finance_chart_accounts', 'finance_ledger_entries.chart_account_id', '=', 'finance_chart_accounts.id')
            ->where('finance_chart_accounts.type', 'revenue')
            ->whereBetween('finance_ledger_entries.entry_date', [$startDate, $endDate])
            ->where(function($q) {
                $q->where('finance_ledger_entries.description', 'NOT LIKE', '%donation%')
                  ->where('finance_ledger_entries.description', 'NOT LIKE', '%Donation%')
                  ->where('finance_journal_batches.reference', 'NOT LIKE', '%DON%');
            });

        if ($churchId !== null) {
            $incomesQuery->where('finance_journal_batches.church_id', $churchId);
        }
        $totalIncome = $incomesQuery->sum('finance_ledger_entries.credit');

        // 3. Total Expenses (All expense debits)
        $expensesQuery = DB::table('finance_ledger_entries')
            ->join('finance_journal_batches', 'finance_ledger_entries.journal_batch_id', '=', 'finance_journal_batches.id')
            ->join('finance_chart_accounts', 'finance_ledger_entries.chart_account_id', '=', 'finance_chart_accounts.id')
            ->where('finance_chart_accounts.type', 'expense')
            ->whereBetween('finance_ledger_entries.entry_date', [$startDate, $endDate]);

        if ($churchId !== null) {
            $expensesQuery->where('finance_journal_batches.church_id', $churchId);
        }
        $totalExpense = $expensesQuery->sum('finance_ledger_entries.debit');

        // 4. Net Balance
        $netBalance = ($totalDonations + $totalIncome) - $totalExpense;

        return [
            'total_donations' => (float)$totalDonations,
            'total_income' => (float)$totalIncome,
            'total_expense' => (float)$totalExpense,
            'net_balance' => (float)$netBalance,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ];
    }

    /**
     * Compile monthly trends report.
     */
    public static function getMonthlyReport(?int $churchId, int $year, User $user): array
    {
        $monthlyData = [];

        for ($month = 1; $month <= 12; $month++) {
            $start = Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
            $end = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();

            $metrics = self::getSummaryMetrics($churchId, $start, $end, $user);

            $monthlyData[] = [
                'month' => $month,
                'month_name' => Carbon::create($year, $month, 1)->format('F'),
                'donations' => $metrics['total_donations'],
                'income' => $metrics['total_income'],
                'expense' => $metrics['total_expense'],
                'balance' => $metrics['net_balance']
            ];
        }

        return [
            'year' => $year,
            'church_id' => $churchId,
            'data' => $monthlyData
        ];
    }

    /**
     * Compile consolidated diocese parish-wise statement.
     */
    public static function getDioceseConsolidatedReport(?string $startDate, ?string $endDate, User $user): array
    {
        $startDate = $startDate ?? Carbon::now()->startOfMonth()->toDateString();
        $endDate = $endDate ?? Carbon::now()->endOfMonth()->toDateString();

        $churches = \App\Models\Church::all();
        $consolidated = [];

        foreach ($churches as $church) {
            $metrics = self::getSummaryMetrics($church->id, $startDate, $endDate, $user);
            $consolidated[] = [
                'church_id' => $church->id,
                'church_name' => $church->name,
                'donations' => $metrics['total_donations'],
                'income' => $metrics['total_income'],
                'expense' => $metrics['total_expense'],
                'balance' => $metrics['net_balance'],
            ];
        }

        // Diocese headquarters only (where church_id is null)
        $metricsNull = self::getSummaryMetrics(null, $startDate, $endDate, $user);

        $consolidated[] = [
            'church_id' => null,
            'church_name' => 'Diocese Headquarters (Consolidated)',
            'donations' => $metricsNull['total_donations'],
            'income' => $metricsNull['total_income'],
            'expense' => $metricsNull['total_expense'],
            'balance' => $metricsNull['net_balance'],
        ];

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'data' => $consolidated
        ];
    }

    /**
     * Export reports to CSV representation.
     */
    public static function exportToCsv(string $reportType, ?int $churchId, ?string $startDate, ?string $endDate, User $user): string
    {
        $csvContent = '';

        if ($reportType === 'summary') {
            $metrics = self::getSummaryMetrics($churchId, $startDate, $endDate, $user);
            $csvContent .= "MSOC Europe Finance Summary Report\n";
            $csvContent .= "Date Range:," . $metrics['start_date'] . " to " . $metrics['end_date'] . "\n\n";
            $csvContent .= "Metric,Amount (EUR)\n";
            $csvContent .= "Total Donations," . $metrics['total_donations'] . "\n";
            $csvContent .= "Total Income," . $metrics['total_income'] . "\n";
            $csvContent .= "Total Expense," . $metrics['total_expense'] . "\n";
            $csvContent .= "Net Balance," . $metrics['net_balance'] . "\n";
        } elseif ($reportType === 'monthly') {
            $year = (int)date('Y');
            $report = self::getMonthlyReport($churchId, $year, $user);
            $csvContent .= "MSOC Europe Monthly Finance Statement - Year {$year}\n\n";
            $csvContent .= "Month,Donations,Income,Expense,Balance\n";
            foreach ($report['data'] as $row) {
                $csvContent .= "{$row['month_name']},{$row['donations']},{$row['income']},{$row['expense']},{$row['balance']}\n";
            }
        } elseif ($reportType === 'consolidated') {
            $report = self::getDioceseConsolidatedReport($startDate, $endDate, $user);
            $csvContent .= "MSOC Europe Consolidated Parish Statement\n";
            $csvContent .= "Date Range:," . $report['start_date'] . " to " . $report['end_date'] . "\n\n";
            $csvContent .= "Church Name,Donations,Income,Expense,Balance\n";
            foreach ($report['data'] as $row) {
                $csvContent .= "{$row['church_name']},{$row['donations']},{$row['income']},{$row['expense']},{$row['balance']}\n";
            }
        }

        // Audit Log for Exports
        AuditLogService::log(
            'Finance',
            'Finance Report Exported',
            "Exported {$reportType} finance report as CSV from ledger entries",
            null,
            ['report_type' => $reportType, 'church_id' => $churchId],
            null,
            $churchId,
            $user->default_diocese_id ?? 1
        );

        return $csvContent;
    }

    /**
     * Generate profit/loss report grouped by Programme Accounts (Perunnal, cost centers, etc.)
     */
    public static function getProgrammeReport(?int $churchId, User $user): array
    {
        $programmes = \App\Models\FinanceProgrammeAccount::where('is_active', true);
        if ($churchId !== null) {
            $programmes->where(function ($q) use ($churchId) {
                $q->where('church_id', $churchId)->orWhereNull('church_id');
            });
        }
        $programmes = $programmes->get();

        $report = [];
        foreach ($programmes as $prog) {
            $income = DB::table('finance_ledger_entries')
                ->join('finance_chart_accounts', 'finance_ledger_entries.chart_account_id', '=', 'finance_chart_accounts.id')
                ->where('finance_ledger_entries.programme_account_id', $prog->id)
                ->where('finance_chart_accounts.type', 'revenue')
                ->sum('finance_ledger_entries.credit') ?? 0.00;

            $expense = DB::table('finance_ledger_entries')
                ->join('finance_chart_accounts', 'finance_ledger_entries.chart_account_id', '=', 'finance_chart_accounts.id')
                ->where('finance_ledger_entries.programme_account_id', $prog->id)
                ->where('finance_chart_accounts.type', 'expense')
                ->sum('finance_ledger_entries.debit') ?? 0.00;

            $report[] = [
                'programme_id' => $prog->id,
                'code' => $prog->code,
                'name' => $prog->name,
                'description' => $prog->description,
                'income' => (float)$income,
                'expense' => (float)$expense,
                'profit_loss' => (float)($income - $expense)
            ];
        }

        return $report;
    }
}
