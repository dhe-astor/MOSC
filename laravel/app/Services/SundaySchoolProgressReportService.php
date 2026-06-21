<?php

namespace App\Services;

use App\Models\SundaySchoolStudent;
use App\Models\SundaySchoolMark;
use App\Models\SundaySchoolProgressReport;
use App\Models\User;
use App\Services\SundaySchoolAttendanceService;
use App\Services\AuditLogService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Barryvdh\DomPDF\Facade\Pdf;
use Exception;
use Illuminate\Support\Facades\DB;

class SundaySchoolProgressReportService
{
    protected SundaySchoolAttendanceService $attendanceService;

    public function __construct(SundaySchoolAttendanceService $attendanceService)
    {
        $this->attendanceService = $attendanceService;
    }

    /**
     * Generate or update the progress report (including PDF) for a Sunday School student.
     */
    public function generateReport(int $studentId, User $generator): SundaySchoolProgressReport
    {
        $student = SundaySchoolStudent::with(['member', 'class', 'academicYear', 'church'])->findOrFail($studentId);

        // 1. Calculate attendance percentage
        $attendancePct = $this->attendanceService->calculateAttendancePercentage($studentId);

        // 2. Fetch all verified marks for the student
        $marks = SundaySchoolMark::where('student_id', $studentId)
            ->whereHas('exam', function ($q) use ($student) {
                $q->where('academic_year_id', $student->academic_year_id);
            })
            ->with('exam')
            ->get();

        // 3. Compute total marks and final grade
        $totalMarks = 0.00;
        $grade = 'N/A';
        $totalMax = 0;

        if ($marks->isNotEmpty()) {
            $totalMarks = $marks->sum('marks_obtained');
            
            // Calculate total maximum marks of all exams
            foreach ($marks as $m) {
                $totalMax += $m->exam->max_marks;
            }

            if ($totalMax > 0) {
                $pct = ($totalMarks / $totalMax) * 100;
                $grade = $this->calculateGrade($pct);
            }
        }

        // 4. Generate HTML content for the PDF Progress Card
        $html = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>Progress Report Card</title>
            <style>
                body {
                    font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
                    padding: 30px;
                    color: #333;
                    line-height: 1.5;
                }
                .header-container {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #1e3a8a;
                    padding-bottom: 10px;
                }
                .title {
                    font-size: 24px;
                    color: #1e3a8a;
                    font-weight: bold;
                    text-transform: uppercase;
                    margin: 0;
                }
                .subtitle {
                    font-size: 14px;
                    color: #555;
                    margin: 5px 0 0 0;
                }
                .info-table {
                    width: 100%;
                    margin-bottom: 30px;
                    border-collapse: collapse;
                }
                .info-table td {
                    padding: 6px 12px;
                    font-size: 14px;
                }
                .info-table td.label {
                    font-weight: bold;
                    color: #4b5563;
                    width: 20%;
                }
                .info-table td.value {
                    color: #111827;
                    width: 30%;
                }
                .marks-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-bottom: 30px;
                }
                .marks-table th, .marks-table td {
                    border: 1px solid #d1d5db;
                    padding: 10px 12px;
                    font-size: 13px;
                }
                .marks-table th {
                    background-color: #f3f4f6;
                    font-weight: bold;
                    text-align: left;
                    color: #374151;
                }
                .marks-table td {
                    color: #4b5563;
                }
                .summary-container {
                    margin-top: 20px;
                    padding: 15px;
                    background-color: #f9fafb;
                    border: 1px solid #e5e7eb;
                    border-radius: 6px;
                }
                .summary-title {
                    font-size: 16px;
                    font-weight: bold;
                    color: #1e3a8a;
                    margin-bottom: 10px;
                }
                .footer {
                    margin-top: 50px;
                    text-align: center;
                    font-size: 12px;
                    color: #9ca3af;
                }
            </style>
        </head>
        <body>
            <div class='header-container'>
                <div class='title'>Sunday School Progress Report</div>
                <div class='subtitle'>Malankara Syrian Orthodox Church (MSOC) Europe</div>
            </div>

            <table class='info-table'>
                <tr>
                    <td class='label'>Student Name:</td>
                    <td class='value'>{$student->member->full_name}</td>
                    <td class='label'>Academic Year:</td>
                    <td class='value'>{$student->academicYear->name}</td>
                </tr>
                <tr>
                    <td class='label'>Class / Level:</td>
                    <td class='value'>{$student->class->class_name}</td>
                    <td class='label'>Parish Unit:</td>
                    <td class='value'>" . ($student->church?->name ?? 'Diocese Online Sunday School') . "</td>
                </tr>
                <tr>
                    <td class='label'>Attendance %:</td>
                    <td class='value'>{$attendancePct}%</td>
                    <td class='label'>Final Grade:</td>
                    <td class='value'>{$grade}</td>
                </tr>
            </table>

            <h3 style='color: #1e3a8a; font-size: 16px; margin-bottom: 10px;'>Exam Performance Records</h3>
            <table class='marks-table'>
                <thead>
                    <tr>
                        <th>Exam Name</th>
                        <th>Type</th>
                        <th>Exam Date</th>
                        <th>Max Marks</th>
                        <th>Marks Obtained</th>
                        <th>Grade</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>";
        foreach ($marks as $m) {
            $html .= "
                    <tr>
                        <td>{$m->exam->exam_name}</td>
                        <td>" . ucfirst(str_replace('_', ' ', $m->exam->exam_type)) . "</td>
                        <td>{$m->exam->exam_date->toDateString()}</td>
                        <td>{$m->exam->max_marks}</td>
                        <td>{$m->marks_obtained}</td>
                        <td>{$m->grade}</td>
                        <td>" . strtoupper($m->result_status) . "</td>
                    </tr>";
        }
        if ($marks->isEmpty()) {
            $html .= "
                    <tr>
                        <td colspan='7' style='text-align: center; color: #9ca3af; font-style: italic;'>No exam records found for this academic year.</td>
                    </tr>";
        }
        $html .= "
                </tbody>
            </table>

            <div class='summary-container'>
                <div class='summary-title'>Academic Performance Summary</div>
                <table style='width: 100%; border: none;'>
                    <tr>
                        <td style='padding: 4px 0; font-size: 14px;'><strong>Total Marks:</strong> {$totalMarks} / {$totalMax}</td>
                        <td style='padding: 4px 0; font-size: 14px;'><strong>Attendance Standing:</strong> " . ($attendancePct >= 75 ? 'Satisfactory' : 'Needs Improvement') . "</td>
                    </tr>
                </table>
            </div>

            <div class='footer'>
                Generated by MSOC Europe Sunday School Administration Portal on " . date('Y-m-d H:i') . "
            </div>
        </body>
        </html>";

        // 5. Render PDF
        $pdf = Pdf::loadHTML($html);
        $pdfContent = $pdf->output();

        // 6. Save PDF to private storage
        $pdfPath = "private/sunday_school_progress_reports/{$studentId}_{$student->academic_year_id}.pdf";
        Storage::put($pdfPath, $pdfContent);

        // 7. Update/Create report record
        return DB::transaction(function () use ($student, $attendancePct, $totalMarks, $grade, $pdfPath, $generator) {
            $report = SundaySchoolProgressReport::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'academic_year_id' => $student->academic_year_id,
                ],
                [
                    'class_id' => $student->class_id,
                    'attendance_percentage' => $attendancePct,
                    'total_marks' => $totalMarks,
                    'grade' => $grade,
                    'generated_by' => $generator->id,
                    'generated_at' => Carbon::now(),
                    'pdf_path' => $pdfPath,
                ]
            );

            AuditLogService::log(
                'sunday_school',
                'progress_report_generated',
                "Generated progress report for student ID {$student->id}",
                null,
                $report->toArray(),
                $report,
                $student->church_id,
                $student->diocese_id
            );

            return $report;
        });
    }

    /**
     * Helper to compute final grades from overall percentages.
     */
    protected function calculateGrade(float $percentage): string
    {
        if ($percentage >= 90) return 'A';
        if ($percentage >= 80) return 'B';
        if ($percentage >= 70) return 'C';
        if ($percentage >= 50) return 'D';
        return 'F';
    }
}
