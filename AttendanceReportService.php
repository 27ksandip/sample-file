<?php

namespace App\Services;

use App\Models\AcademicSession;
use App\Models\ClassSchedule;
use App\Models\Student;
use Carbon\Carbon;

class AttendanceReportService
{
    public function generateAttendanceReport($studentId)
    {
        $academicSession = AcademicSession::where('check_session', 1)->first();

        // Convert start and end dates to Carbon instances
        $sessionStartDate = Carbon::createFromFormat('d-m-Y', $academicSession->start_date);
        $sessionEndDate = Carbon::createFromFormat('d-m-Y', $academicSession->end_date);

        // Get student details
        $student = Student::where('user_id', $studentId)->with('chooseprogram')->firstOrFail();

        // Get class schedules for the student's program and section
        $classSchedule = ClassSchedule::where('standard_id', $student->chooseprogram->standard_id?? '')
            ->where('standard_section_id', $student->chooseprogram->standard_section_id ?? '')
            ->get();

        // Initialize arrays to store results
        $monthlyClasses = [];
        $monthlyAttendance = [];

        // Loop through the months in the academic session
        $currentMonth = $sessionStartDate->copy();
        $monthIndex = 0;

        while ($currentMonth->lte($sessionEndDate)) {
            $startMonth = $currentMonth->copy()->startOfMonth();
            $endOfMonth = $currentMonth->copy()->endOfMonth();

            // Adjust start and end dates to session boundaries
            $effectiveStartDate = $startMonth->max($sessionStartDate);
            $effectiveEndDate = $endOfMonth->min($sessionEndDate);

            // Calculate total classes scheduled for the month
            $totalClassesThisMonth = 0;
            foreach ($classSchedule as $schedule) {
                $classDay = $schedule->day; // e.g., "Monday"
                $classCount = Carbon::parse($effectiveStartDate)->daysUntil($effectiveEndDate)
                    ->filter(fn($date) => $date->englishDayOfWeek === $classDay)
                    ->count();

                $totalClassesThisMonth += $classCount;
            }

            // Count the number of lectures attended by the student for the month
            $lecturesAttendedThisMonth = $student->attendances()
                ->whereBetween('date', [
                    $effectiveStartDate->format('Y-m-d'),
                    $effectiveEndDate->format('Y-m-d')
                ])
                ->count();

            // Store results
            $monthlyClasses[] = $totalClassesThisMonth;
            $monthlyAttendance[] = $lecturesAttendedThisMonth;

            // Move to next month
            $currentMonth->addMonth();
            $monthIndex++;
        }

        // Combine the data for easier analysis
        $monthlyData = [];
        $currentMonth = $sessionStartDate->copy();
        for ($i = 0; $i < $monthIndex; $i++) {
            $monthName = $currentMonth->format('F-Y');
            $monthlyData[] = [
                'month' => $monthName,
                'date'  => $currentMonth->format('Y-m-d'),
                'total_classes' => $monthlyClasses[$i],
                'lectures_attended' => $monthlyAttendance[$i],
                'average' => $monthlyClasses[$i] != 0
                    ? round(($monthlyAttendance[$i] / $monthlyClasses[$i]) * 100, 2)
                    : 0
            ];

            $currentMonth->addMonth();
        }

        return $monthlyData;
    }
}
