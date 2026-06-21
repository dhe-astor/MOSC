<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\ApiResponse;
use App\Models\ReportDefinition;
use App\Models\SavedReport;
use App\Models\ReportRun;
use App\Models\ReportExport;
use App\Models\ScheduledReport;
use App\Models\DashboardWidget;
use App\Services\ReportDefinitionService;
use App\Services\ReportQueryService;
use App\Services\ReportExportService;
use App\Services\ScheduledReportService;
use App\Services\DashboardWidgetService;
use App\Services\AnalyticsSummaryService;
use App\Services\AuditLogService;
use App\Services\ChurchAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportController extends Controller
{
    use ApiResponse;

    // =========================================================================
    // Report Definitions
    // =========================================================================
    public function definitions(Request $request)
    {
        $user = $request->user();
        $definitions = ReportDefinitionService::getAvailableDefinitions($user);
        return $this->successResponse($definitions, 'Report definitions retrieved successfully');
    }

    public function definition(Request $request, $id)
    {
        $user = $request->user();
        $definition = ReportDefinition::findOrFail($id);
        ReportQueryService::authorizeReport($definition, $user);
        return $this->successResponse($definition, 'Report definition retrieved successfully');
    }

    // =========================================================================
    // Saved Reports
    // =========================================================================
    public function savedReports(Request $request)
    {
        $user = $request->user();
        $query = SavedReport::query();

        if (!$user->hasRole(['Super Admin', 'Diocese Admin'])) {
            $query->where(function ($q) use ($user) {
                $q->where('created_by', $user->id)
                  ->orWhere(function ($sub) use ($user) {
                      $sub->where('visibility', 'parish')
                          ->where('church_id', $user->active_church_id);
                  });
            });
        }

        return $this->successResponse($query->get(), 'Saved reports retrieved successfully');
    }

    public function storeSavedReport(Request $request)
    {
        $request->validate([
            'report_definition_id' => 'required|exists:report_definitions,id',
            'name' => 'required|string|max:255',
            'filters' => 'required|array',
            'columns' => 'nullable|array',
            'visibility' => 'required|in:private,parish,diocese',
        ]);

        $user = $request->user();
        $definition = ReportDefinition::findOrFail($request->input('report_definition_id'));
        ReportQueryService::authorizeReport($definition, $user);

        // Scope validation
        if ($request->input('visibility') === 'parish' && !$user->active_church_id) {
            return $this->errorResponse('Parish visibility requires an active church context.', 400);
        }

        $savedReport = SavedReport::create([
            'diocese_id' => $user->default_diocese_id,
            'church_id' => $user->active_church_id,
            'report_definition_id' => $definition->id,
            'name' => $request->input('name'),
            'description' => $request->input('description'),
            'filters' => $request->input('filters'),
            'columns' => $request->input('columns'),
            'visibility' => $request->input('visibility'),
            'created_by' => $user->id,
        ]);

        return $this->successResponse($savedReport, 'Report filters saved successfully', 201);
    }

    public function showSavedReport(Request $request, $id)
    {
        $saved = SavedReport::findOrFail($id);
        return $this->successResponse($saved, 'Saved report details retrieved');
    }

    public function updateSavedReport(Request $request, $id)
    {
        $saved = SavedReport::findOrFail($id);
        if ($saved->created_by !== $request->user()->id && !$request->user()->hasRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to update this saved report.', 403);
        }

        $saved->update($request->only(['name', 'description', 'filters', 'columns', 'visibility']));
        return $this->successResponse($saved, 'Saved report updated successfully');
    }

    public function destroySavedReport(Request $request, $id)
    {
        $saved = SavedReport::findOrFail($id);
        if ($saved->created_by !== $request->user()->id && !$request->user()->hasRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to delete this saved report.', 403);
        }

        $saved->delete();
        return $this->successResponse(null, 'Saved report deleted successfully');
    }

    // =========================================================================
    // Report Runs
    // =========================================================================
    public function run(Request $request)
    {
        $request->validate([
            'report_key' => 'required|string',
            'filters' => 'nullable|array',
        ]);

        $user = $request->user();
        $reportKey = $request->input('report_key');
        $filters = $request->input('filters') ?? [];

        // No raw queries from frontend
        if (isset($filters['raw_sql']) || isset($filters['query'])) {
            return $this->errorResponse('Raw SQL or custom query input is prohibited.', 400);
        }

        $definition = ReportDefinition::where('report_key', $reportKey)->firstOrFail();

        // 1. Create a ReportRun
        $run = ReportRun::create([
            'diocese_id' => $user->default_diocese_id ?? 1,
            'church_id' => $user->active_church_id,
            'report_definition_id' => $definition->id,
            'report_key' => $reportKey,
            'filters' => $filters,
            'status' => 'processing',
            'generated_by' => $user->id,
            'started_at' => Carbon::now(),
        ]);

        try {
            // 2. Fetch data
            $reportData = ReportQueryService::runReport($reportKey, $filters, $user);

            // 3. Mark completed
            $run->update([
                'status' => 'completed',
                'row_count' => count($reportData['data']),
                'completed_at' => Carbon::now(),
            ]);

            return $this->successResponse([
                'run_id' => $run->id,
                'definition' => $definition,
                'headers' => $reportData['headers'],
                'data' => $reportData['data'],
                'summary' => $reportData['summary'],
            ], 'Report generated successfully');

        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => Carbon::now(),
            ]);

            return $this->errorResponse('Failed to run report: ' . $e->getMessage(), $e->getStatusCode());
        } catch (\Exception $e) {
            $run->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => Carbon::now(),
            ]);

            return $this->errorResponse('Failed to run report: ' . $e->getMessage(), 500);
        }
    }

    public function runs(Request $request)
    {
        $runs = ReportRun::where('generated_by', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return $this->successResponse($runs, 'Recent report runs list');
    }

    public function showRun(Request $request, $id)
    {
        $run = ReportRun::findOrFail($id);
        if ($run->generated_by !== $request->user()->id && !$request->user()->hasRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized to view this report run.', 403);
        }
        return $this->successResponse($run, 'Report run details');
    }

    // =========================================================================
    // Exports
    // =========================================================================
    public function createExport(Request $request, $id)
    {
        $request->validate([
            'export_type' => 'required|in:csv,pdf',
        ]);

        $user = $request->user();
        $run = ReportRun::findOrFail($id);

        if ($run->status !== 'completed') {
            return $this->errorResponse('Cannot export a report run that is not completed.', 400);
        }

        $export = ReportExportService::createExport($run, $request->input('export_type'), $user);
        return $this->successResponse($export, 'Export file generated successfully', 201);
    }

    public function exports(Request $request)
    {
        $exports = ReportExport::where('generated_by', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
        return $this->successResponse($exports, 'Exports history');
    }

    public function downloadExport(Request $request, $id)
    {
        $user = $request->user();
        
        if (!$user->hasPermissionTo('download_report_exports')) {
            return $this->errorResponse('Unauthorized to download report exports.', 403);
        }

        $export = ReportExport::findOrFail($id);

        // Check if expired
        if ($export->status === 'expired' || ($export->expires_at && $export->expires_at->isPast())) {
            return $this->errorResponse('This export file has expired (retention limit is 7 days).', 403);
        }

        // Check file exists
        if (!Storage::exists($export->file_path)) {
            return $this->errorResponse('Export file not found on disk.', 404);
        }

        // Additional auth check at download time
        $run = $export->run;
        if ($run) {
            $definition = $run->definition;
            if ($definition) {
                ReportQueryService::authorizeReport($definition, $user);
            }
        }

        // Increment downloaded count
        $export->increment('downloaded_count');

        // Audit Log
        AuditLogService::log(
            'Reports',
            'Report Export Downloaded',
            "Downloaded export file: {$export->file_name}",
            null,
            ['export_id' => $export->id],
            null,
            $export->church_id,
            $export->diocese_id
        );

        return response()->download(Storage::path($export->file_path), $export->file_name);
    }

    public function expireExport(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasRole(['Super Admin', 'Diocese Admin'])) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $export = ReportExport::findOrFail($id);
        if (Storage::exists($export->file_path)) {
            Storage::delete($export->file_path);
        }
        $export->update(['status' => 'expired']);

        return $this->successResponse($export, 'Export expired manually');
    }

    // =========================================================================
    // Scheduled Reports
    // =========================================================================
    public function scheduledReports(Request $request)
    {
        $scheduled = ScheduledReport::where('created_by', $request->user()->id)->get();
        return $this->successResponse($scheduled, 'Scheduled reports list');
    }

    public function storeScheduledReport(Request $request)
    {
        $request->validate([
            'report_definition_id' => 'required|exists:report_definitions,id',
            'saved_report_id' => 'nullable|exists:saved_reports,id',
            'name' => 'required|string|max:255',
            'frequency' => 'required|in:daily,weekly,monthly,quarterly,yearly',
            'recipients' => 'required|array',
            'export_type' => 'required|in:csv,pdf',
        ]);

        $user = $request->user();
        $definition = ReportDefinition::findOrFail($request->input('report_definition_id'));
        ReportQueryService::authorizeReport($definition, $user);

        $scheduled = ScheduledReport::create([
            'diocese_id' => $user->default_diocese_id,
            'church_id' => $user->active_church_id,
            'report_definition_id' => $definition->id,
            'saved_report_id' => $request->input('saved_report_id'),
            'name' => $request->input('name'),
            'frequency' => $request->input('frequency'),
            'timezone' => 'Europe/Vienna',
            'recipients' => $request->input('recipients'),
            'export_type' => $request->input('export_type'),
            'status' => 'active',
            'next_run_at' => Carbon::now(),
            'created_by' => $user->id,
        ]);

        return $this->successResponse($scheduled, 'Report scheduled successfully', 201);
    }

    public function showScheduledReport(Request $request, $id)
    {
        $scheduled = ScheduledReport::findOrFail($id);
        return $this->successResponse($scheduled, 'Scheduled report details');
    }

    public function updateScheduledReport(Request $request, $id)
    {
        $scheduled = ScheduledReport::findOrFail($id);
        if ($scheduled->created_by !== $request->user()->id) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $scheduled->update($request->only(['name', 'frequency', 'recipients', 'export_type']));
        return $this->successResponse($scheduled, 'Scheduled report updated successfully');
    }

    public function pauseScheduledReport(Request $request, $id)
    {
        $scheduled = ScheduledReport::findOrFail($id);
        if ($scheduled->created_by !== $request->user()->id) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $scheduled->update(['status' => 'paused']);
        return $this->successResponse($scheduled, 'Scheduled report paused');
    }

    public function resumeScheduledReport(Request $request, $id)
    {
        $scheduled = ScheduledReport::findOrFail($id);
        if ($scheduled->created_by !== $request->user()->id) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $scheduled->update(['status' => 'active', 'next_run_at' => Carbon::now()]);
        return $this->successResponse($scheduled, 'Scheduled report resumed');
    }

    public function cancelScheduledReport(Request $request, $id)
    {
        $scheduled = ScheduledReport::findOrFail($id);
        if ($scheduled->created_by !== $request->user()->id) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $scheduled->update(['status' => 'cancelled']);
        return $this->successResponse($scheduled, 'Scheduled report cancelled');
    }

    // =========================================================================
    // Dashboard Widgets
    // =========================================================================
    public function dashboardWidgets(Request $request)
    {
        $widgets = DashboardWidgetService::getActiveWidgets($request->user());
        return $this->successResponse($widgets, 'Active dashboard widgets retrieved');
    }

    public function updateDashboardWidget(Request $request, $id)
    {
        $user = $request->user();
        if (!$user->hasPermissionTo('manage_dashboard_widgets')) {
            return $this->errorResponse('Unauthorized.', 403);
        }

        $widget = DashboardWidget::findOrFail($id);
        $widget->update($request->only(['title', 'sort_order', 'status']));
        return $this->successResponse($widget, 'Dashboard widget updated successfully');
    }

    // =========================================================================
    // Shortcut Category Routes
    // =========================================================================
    public function dioceseOverview(Request $request)
    {
        return $this->runReportDirect('diocese_overview', $request);
    }

    public function parishOverview(Request $request)
    {
        return $this->runReportDirect('parish_overview', $request);
    }

    public function members(Request $request)
    {
        return $this->runReportDirect('members_families_list', $request);
    }

    public function sacraments(Request $request)
    {
        return $this->runReportDirect('sacramental_records', $request);
    }

    public function certificates(Request $request)
    {
        return $this->runReportDirect('certificates_issued', $request);
    }

    public function coursesEvents(Request $request)
    {
        return $this->runReportDirect('courses_summary', $request);
    }

    public function sundaySchool(Request $request)
    {
        return $this->runReportDirect('sunday_school_progress', $request);
    }

    public function ministries(Request $request)
    {
        return $this->runReportDirect('ministries_overview', $request);
    }

    public function finance(Request $request)
    {
        return $this->runReportDirect('finance_statement', $request);
    }

    public function cms(Request $request)
    {
        return $this->runReportDirect('cms_publishing', $request);
    }

    public function communications(Request $request)
    {
        return $this->runReportDirect('communications_delivery', $request);
    }

    public function memberPortal(Request $request)
    {
        return $this->runReportDirect('portal_usage', $request);
    }

    public function gdpr(Request $request)
    {
        return $this->runReportDirect('gdpr_privacy_audit', $request);
    }

    public function audit(Request $request)
    {
        return $this->runReportDirect('audit_logs', $request);
    }

    private function runReportDirect(string $key, Request $request)
    {
        $user = $request->user();
        $filters = $request->all();
        $data = ReportQueryService::runReport($key, $filters, $user);
        return $this->successResponse($data, 'Report details retrieved successfully');
    }
}
