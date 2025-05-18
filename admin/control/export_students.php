<?php
require_once "../../backend/connections/config.php";


// Define college names for reference
$college_names = [
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];

// Get college parameter
if (!isset($_GET['college']) || !array_key_exists($_GET['college'], $college_names)) {
    die("Invalid college specified");
}

$college_code = $_GET['college'];
$college_name = $college_names[$college_code];

// Set filename for download
$filename = 'ISATU_Students_' . $college_code . '_' . date('Y-m-d') . '.csv';

// Set headers for download
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

// Create a file pointer connected to the output stream
$output = fopen('php://output', 'w');

// Add UTF-8 BOM to fix Excel CSV encoding issues
fputs($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

// Add a title row
fputcsv($output, ['ILOILO SCIENCE AND TECHNOLOGY UNIVERSITY']);
fputcsv($output, ['Student Registration - ' . $college_name]);
fputcsv($output, ['Generated on: ' . date('F d, Y h:i A')]);

// Add an empty row for spacing
fputcsv($output, []);

// Set column headers
fputcsv($output, ['No.', 'Student ID', 'Date Registered', 'Status']);

// Fetch data from database
$query = "SELECT id, student_id, created_at FROM students WHERE college_code = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $college_code);
$stmt->execute();
$result = $stmt->get_result();

// Initialize counter
$count = 1;

// Output each row of data
while ($student = $result->fetch_assoc()) {
    fputcsv($output, [
        $count,
        $student['student_id'],
        date('F d, Y h:i A', strtotime($student['created_at'])),
        'Registered'
    ]);
    $count++;
}

// Close the file pointer
fclose($output);
exit;