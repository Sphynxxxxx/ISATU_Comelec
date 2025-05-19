<?php
session_start();
require_once "../../backend/connections/config.php";

// Get college filter
$selected_college = isset($_GET['college']) ? $_GET['college'] : 'all';

// Get college names for headers
$colleges = [
    'all' => 'All Colleges',
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];

// Function to get candidates by position
function getCandidatesByPosition($conn, $position_id, $college = 'all') {
    if ($college == 'all') {
        $candidates_query = "SELECT c.*, 
                          c.name as candidate_name,
                          c.photo_url,
                          COUNT(vd.id) as vote_count
                       FROM candidates c
                       LEFT JOIN vote_details vd ON c.id = vd.candidate_id
                       WHERE c.position_id = ?
                       GROUP BY c.id
                       ORDER BY vote_count DESC";
        
        $stmt = $conn->prepare($candidates_query);
        $stmt->bind_param("i", $position_id);
    } else {
        $candidates_query = "SELECT c.*, 
                          c.name as candidate_name,
                          c.photo_url,
                          COUNT(vd.id) as vote_count
                       FROM candidates c
                       LEFT JOIN vote_details vd ON c.id = vd.candidate_id
                       WHERE c.position_id = ? AND LOWER(c.college_code) = ?
                       GROUP BY c.id
                       ORDER BY vote_count DESC";
        
        $stmt = $conn->prepare($candidates_query);
        $stmt->bind_param("is", $position_id, $college);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $candidates = [];
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $candidates[] = $row;
        }
    }
    
    return $candidates;
}

// Function to get total votes cast for a position
function getTotalVotesByPosition($conn, $position_id, $college = 'all') {
    if ($college == 'all') {
        $votes_query = "SELECT COUNT(*) as total 
                      FROM vote_details vd
                      JOIN candidates c ON vd.candidate_id = c.id
                      WHERE c.position_id = ?";
        
        $stmt = $conn->prepare($votes_query);
        $stmt->bind_param("i", $position_id);
    } else {
        $votes_query = "SELECT COUNT(*) as total 
                      FROM vote_details vd
                      JOIN candidates c ON vd.candidate_id = c.id
                      WHERE c.position_id = ? AND LOWER(c.college_code) = ?";
        
        $stmt = $conn->prepare($votes_query);
        $stmt->bind_param("is", $position_id, $college);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        return $row['total'];
    }
    
    return 0;
}

// Function to calculate percentage
function calculatePercentage($votes, $total) {
    if ($total <= 0) return 0;
    return round(($votes / $total) * 100, 1);
}

// Set the file name and type
$college_name = $colleges[$selected_college];
$filename = "ISATU_Election_Results_" . $college_name . "_" . date('Y-m-d') . ".xls";

// Set headers for Excel download
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=\"$filename\"");
header("Pragma: no-cache");
header("Expires: 0");

// Get all positions
$positions_query = "SELECT * FROM positions ORDER BY display_order";
$positions_result = $conn->query($positions_query);
$positions = [];

if ($positions_result->num_rows > 0) {
    while ($row = $positions_result->fetch_assoc()) {
        $positions[] = $row;
    }
}

// Start generating Excel content
echo "<!DOCTYPE html>";
echo "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>";
echo "<head>";
echo "<meta http-equiv=\"Content-Type\" content=\"text/html; charset=utf-8\">";
echo "<style>";
echo "body { font-size: 12px; font-family: Arial, sans-serif; }";
echo "h1 { font-size: 12px; font-weight: bold; }";
echo "p { font-size: 12px; }";
echo "td, th { border: 1px solid #000000; padding: 5px; text-align: left; font-size: 12px; }";
echo "table { border-collapse: collapse; width: 100%; }";
echo ".header { background-color: #0c3b5d; color: white; font-weight: bold; font-size: 12px; }";
echo ".sub-header { background-color: #f2c01d; color: #0c3b5d; font-weight: bold; font-size: 12px; }";
echo "</style>";
echo "</head>";
echo "<body>";

// Title
echo "<div style='font-size: 12px; font-weight: bold;'>ISATU Election Results - $college_name</div>";
echo "<div style='font-size: 12px;'>Generated on: " . date('F d, Y - h:i A') . "</div>";
echo "<br>";

// Summary data
$total_candidates_query = "SELECT COUNT(*) as total FROM candidates";
if ($selected_college != 'all') {
    $total_candidates_query .= " WHERE LOWER(college_code) = '$selected_college'";
}
$total_candidates_result = $conn->query($total_candidates_query);
$total_candidates = $total_candidates_result->fetch_assoc()['total'];

$total_votes_query = "SELECT COUNT(*) as total FROM vote_details vd JOIN candidates c ON vd.candidate_id = c.id";
if ($selected_college != 'all') {
    $total_votes_query .= " WHERE LOWER(c.college_code) = '$selected_college'";
}
$total_votes_result = $conn->query($total_votes_query);
$total_votes = $total_votes_result->fetch_assoc()['total'];

// Summary table
echo "<table>";
echo "<tr class='header'><th colspan='2'>Election Summary</th></tr>";
echo "<tr><td>Total Positions</td><td>" . count($positions) . "</td></tr>";
echo "<tr><td>Total Candidates</td><td>" . $total_candidates . "</td></tr>";
echo "<tr><td>Total Votes Cast</td><td>" . $total_votes . "</td></tr>";
echo "</table>";
echo "<br>";

// Check if we have any candidates
$any_candidates = false;
foreach ($positions as $position) {
    $candidates = getCandidatesByPosition($conn, $position['id'], $selected_college);
    if (!empty($candidates)) {
        $any_candidates = true;
        break;
    }
}

if (!$any_candidates) {
    echo "<div style='font-size: 12px;'>No candidates found for the selected filter.</div>";
} else {
    // Process each position
    foreach ($positions as $position) {
        $candidates = getCandidatesByPosition($conn, $position['id'], $selected_college);
        
        // Skip positions with no candidates
        if (empty($candidates)) continue;
        
        // Get total votes for this position
        $total_position_votes = getTotalVotesByPosition($conn, $position['id'], $selected_college);
        
        // Position header
        echo "<table>";
        echo "<tr class='header'><th colspan='5'>" . $position['name'] . "</th></tr>";
        echo "<tr class='sub-header'>";
        echo "<th>Rank</th>";
        echo "<th>Candidate Name</th>";
        echo "<th>College</th>";
        echo "<th>Votes</th>";
        echo "<th>Percentage</th>";
        echo "</tr>";
        
        // Candidates
        foreach ($candidates as $index => $candidate) {
            $vote_percentage = calculatePercentage($candidate['vote_count'], $total_position_votes);
            $is_winner = ($index === 0 && $candidate['vote_count'] > 0) ? "✓ WINNER" : "";
            
            echo "<tr>";
            echo "<td>" . ($index + 1) . "</td>";
            echo "<td>" . $candidate['candidate_name'] . " " . $is_winner . "</td>";
            echo "<td>" . strtoupper($candidate['college_code']) . "</td>";
            echo "<td>" . $candidate['vote_count'] . "</td>";
            echo "<td>" . $vote_percentage . "%</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "<br>";
    }
}

echo "</body>";
echo "</html>";

// Exit to prevent any additional output
exit();
?>