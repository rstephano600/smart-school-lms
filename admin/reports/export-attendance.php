<?php
require_once '../../config.php';
require_once '../../includes/auth.php';
requireRole('admin');

$class_id = $_GET['class_id'] ?? '';
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');

// Set headers for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="attendance_report_' . date('Y-m-d') . '.csv"');

// Create output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Add headers
fputcsv($output, ['Admission No', 'Student Name', 'Class', 'Present Days', 'Absent Days', 'Late Days', 'Excused Days', 'Total Days', 'Attendance %', 'Status']);

// Get data
$query = "SELECT 
            s.admission_number,
            CONCAT(u.first_name, ' ', u.last_name) as student_name,
            c.name as class_name,
            COUNT(CASE WHEN a.status = 'present' THEN 1 END) as present_days,
            COUNT(CASE WHEN a.status = 'absent' THEN 1 END) as absent_days,
            COUNT(CASE WHEN a.status = 'late' THEN 1 END) as late_days,
            COUNT(CASE WHEN a.status = 'excused' THEN 1 END) as excused_days,
            COUNT(a.id) as total_days,
            ROUND((COUNT(CASE WHEN a.status = 'present' THEN 1 END) / COUNT(a.id)) * 100, 2) as attendance_percentage
          FROM students s
          JOIN users u ON s.user_id = u.id
          JOIN classes c ON s.class_id = c.id
          LEFT JOIN attendance a ON s.id = a.student_id AND a.date BETWEEN '$date_from' AND '$date_to'
          WHERE u.is_active = 1";

if (!empty($class_id)) {
    $query .= " AND s.class_id = $class_id";
}

$query .= " GROUP BY s.id ORDER BY attendance_percentage DESC";
$result = $conn->query($query);

// Write data rows
while ($row = $result->fetch_assoc()) {
    $status = $row['attendance_percentage'] >= 75 ? 'Good Standing' : 'Needs Improvement';
    fputcsv($output, [
        $row['admission_number'],
        $row['student_name'],
        $row['class_name'],
        $row['present_days'],
        $row['absent_days'],
        $row['late_days'],
        $row['excused_days'],
        $row['total_days'],
        $row['attendance_percentage'] . '%',
        $status
    ]);
}

fclose($output);
exit();
?>