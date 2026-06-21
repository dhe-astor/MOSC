<?php

namespace App\Services;

use App\Models\ReportRun;
use App\Models\ReportExport;
use App\Models\User;
use App\Services\AuditLogService;
use App\Services\ReportQueryService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ReportExportService
{
    /**
     * Create an export from a completed report run.
     */
    public static function createExport(ReportRun $run, string $type, User $user): ReportExport
    {
        $reportData = ReportQueryService::runReport($run->report_key, $run->filters ?? [], $user);
        
        $fileName = 'report_' . $run->report_key . '_' . Str::random(10) . '.' . $type;
        $filePath = 'private/report_exports/' . $fileName;
        
        $content = '';
        if ($type === 'csv') {
            $content = self::generateCsv($reportData['headers'], $reportData['data']);
        } elseif ($type === 'pdf') {
            $content = self::generatePdfSummary($run->report_key, $reportData['headers'], $reportData['data']);
        }
        
        Storage::put($filePath, $content);
        
        $export = ReportExport::create([
            'diocese_id' => $run->diocese_id,
            'church_id' => $run->church_id,
            'report_run_id' => $run->id,
            'export_type' => $type,
            'file_path' => $filePath,
            'file_name' => $fileName,
            'file_size' => strlen($content),
            'status' => 'generated',
            'generated_by' => $user->id,
            'expires_at' => Carbon::now()->addDays(7),
        ]);
        
        AuditLogService::log(
            'Reports',
            'Report Exported',
            "Generated {$type} export for report key: {$run->report_key}",
            null,
            ['export_id' => $export->id],
            null,
            $run->church_id,
            $run->diocese_id
        );
        
        return $export;
    }
    
    private static function generateCsv(array $headers, array $data): string
    {
        $fp = fopen('php://temp', 'r+');
        fputcsv($fp, $headers);
        foreach ($data as $row) {
            fputcsv($fp, array_values($row));
        }
        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);
        return $csv;
    }
    
    private static function generatePdfSummary(string $reportKey, array $headers, array $data): string
    {
        $html = '<html><head><style>body{font-family:sans-serif;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #ddd;padding:8px;text-align:left;}</style></head><body>';
        $html .= '<h2>Report Summary: ' . ucfirst(str_replace('_', ' ', $reportKey)) . '</h2>';
        $html .= '<table><thead><tr>';
        foreach ($headers as $h) {
            $html .= '<th>' . htmlspecialchars($h) . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        foreach ($data as $row) {
            $html .= '<tr>';
            foreach (array_values($row) as $val) {
                $html .= '<td>' . htmlspecialchars((string)$val) . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></body></html>';
        
        try {
            if (class_exists(\Dompdf\Dompdf::class)) {
                $dompdf = new \Dompdf\Dompdf();
                $dompdf->loadHtml($html);
                $dompdf->setPaper('A4', 'portrait');
                $dompdf->render();
                return $dompdf->output();
            }
        } catch (\Exception $e) {
            // fallback
        }
        
        return $html;
    }
}
