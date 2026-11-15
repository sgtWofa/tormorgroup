<?php
require_once __DIR__ . '/../../../assets/vendor/autoload.php'; // Load mPDF

// Set timezone
date_default_timezone_set('Africa/Accra');
header('Content-Type: application/json; charset=utf-8');
ob_start(); // Start output buffering

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    $rawData = json_decode(file_get_contents('php://input'), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON input: ' . json_last_error_msg());
    }

    if (!isset($rawData['action'])) {
        throw new Exception('Missing action parameter');
    }

    $action = $rawData['action'];
    $info = ['success' => false, 'results' => []];

    // Handle Export Attendance Action
    if ($action === 'export_attendance') {
        if (!class_exists('Mpdf\Mpdf')) {
            throw new Exception('mPDF library is missing. Run "composer require mpdf/mpdf".');
        }

        $exportType = $rawData['exportType'] ?? 'csv';
        $mode = $rawData['mode'] ?? 'specific';
        if ($mode === 'specific') {
            if (empty($rawData['date'])) {
                throw new Exception('Date parameter is required for export');
            }
            $date = $rawData['date'];
            $unmarked = fetchUnmarkedUsersForDate($date);
            $marked = fetchMarkedUsersForDate($date);
            $allData = array_merge($marked, $unmarked);
        } else {
            if (empty($rawData['startDate']) || empty($rawData['endDate'])) {
                throw new Exception('Start and end dates are required for export');
            }
            $startDate = $rawData['startDate'];
            $endDate = $rawData['endDate'];
            $grouped = fetchAttendanceByRange($startDate, $endDate);
            $allData = [];
            foreach ($grouped as $date => $group) {
                $allData = array_merge($allData, $group['marked'], $group['unmarked']);
            }
        }

        $breakdown = calculateGenderBreakdown($allData);

        if ($exportType === 'csv') {
            exportCSV($allData, $breakdown);
        } else {
            exportPDF($allData, $breakdown);
        }
        exit;
    }

    throw new Exception('Unknown action');
} catch (Exception $e) {
    ob_clean(); // Clean output buffer to prevent corruption
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
} finally {
    ob_end_clean(); // Clean buffer to avoid corrupt output
}

// =================== PDF Export Function ===================
function exportPDF($data, $breakdown) {
    try {
        ob_end_clean(); // Ensure no previous output

        $mpdf = new \Mpdf\Mpdf();
        $html = '<h1>Attendance Export</h1>';
        $html .= '<table border="1" cellspacing="0" cellpadding="5">';
        $html .= '<tr>
                    <th>User ID</th><th>First Name</th><th>Last Name</th><th>Gender</th>
                    <th>Time</th><th>Remarks</th><th>Date</th>
                  </tr>';
        foreach ($data as $row) {
            $html .= '<tr>
                        <td>' . htmlspecialchars($row['id'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['first_name'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['last_name'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['gender'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['time'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['remarks'] ?? '') . '</td>
                        <td>' . htmlspecialchars($row['date'] ?? '') . '</td>
                      </tr>';
        }
        $html .= '</table>';
        $html .= '<h2>Attendance Summary</h2>';
        $html .= '<p>Male Present: ' . $breakdown['malePresent'] . '</p>';
        $html .= '<p>Male Absent: ' . $breakdown['maleAbsent'] . '</p>';
        $html .= '<p>Female Present: ' . $breakdown['femalePresent'] . '</p>';
        $html .= '<p>Female Absent: ' . $breakdown['femaleAbsent'] . '</p>';

        $mpdf->WriteHTML($html);

        // Send proper headers before outputting the PDF
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="attendance_export.pdf"');
        header('Content-Transfer-Encoding: binary');
        header('Accept-Ranges: bytes');

        $mpdf->Output('attendance_export.pdf', 'I'); // Inline display in browser
        exit;
    } catch (Exception $e) {
        error_log('PDF Export Error: ' . $e->getMessage());
        echo 'Error generating PDF';
    }
}

// =================== CSV Export Function ===================
function exportCSV($data, $breakdown) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment;filename="attendance_export.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['User ID', 'First Name', 'Last Name', 'Gender', 'Time', 'Remarks', 'Date']);

    foreach ($data as $row) {
        fputcsv($output, [
            $row['id'] ?? '',
            $row['first_name'] ?? '',
            $row['last_name'] ?? '',
            $row['gender'] ?? '',
            $row['time'] ?? '',
            $row['remarks'] ?? '',
            $row['date'] ?? ''
        ]);
    }

    fclose($output);
}

// =================== Supporting Functions ===================
function fetchUnmarkedUsersForDate($date) {
    $user = new \Performances\User();
    $query = "SELECT id, first_name, last_name, image, gender FROM users 
              WHERE id NOT IN (SELECT user_id FROM attendance WHERE date = :date)";
    $results = $user->query($query, ['date' => $date]);
    return $results ?: [];
}

function fetchMarkedUsersForDate($date) {
    $attendance = new \Performances\Attendance();
    $query = "SELECT u.id, u.first_name, u.last_name, u.image, u.gender, a.time, a.remarks, a.date
              FROM attendance a JOIN users u ON a.user_id = u.id
              WHERE a.date = :date ORDER BY a.time ASC";
    $results = $attendance->query($query, ['date' => $date]);
    return $results ?: [];
}

function fetchAttendanceByRange($startDate, $endDate) {
    $grouped = [];
    $dates = getDatesInRange($startDate, $endDate);
    foreach ($dates as $date) {
        $grouped[$date] = [
            'marked'   => fetchMarkedUsersForDate($date),
            'unmarked' => fetchUnmarkedUsersForDate($date),
        ];
    }
    return $grouped;
}

function calculateGenderBreakdown($data) {
    $malePresent = $maleAbsent = $femalePresent = $femaleAbsent = 0;
    foreach ($data as $row) {
        if ($row['remarks'] === 'PRESENT') {
            $row['gender'] === 'male' ? $malePresent++ : $femalePresent++;
        } else {
            $row['gender'] === 'male' ? $maleAbsent++ : $femaleAbsent++;
        }
    }
    return compact('malePresent', 'maleAbsent', 'femalePresent', 'femaleAbsent');
}
?>
