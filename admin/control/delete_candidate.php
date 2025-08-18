<?php
session_start();
require_once "../../backend/connections/config.php";


// Check if ID and college parameters are provided
if (!isset($_GET['id']) || !isset($_GET['college'])) {
    $_SESSION['error'] = "Missing required parameters.";
    header("Location: ../manage_candidates.php");
    exit();
}

$candidate_id = (int)$_GET['id'];
$college_code = $_GET['college'];

// Validate college code
$valid_colleges = ['sr', 'cas', 'cea', 'coe', 'cit', 'cci'];
if (!in_array($college_code, $valid_colleges)) {
    $_SESSION['error'] = "Invalid college code.";
    header("Location: ../manage_candidates.php");
    exit();
}

// First, get the candidate's photo URL to delete the file if it exists
$photo_query = "SELECT photo_url FROM candidates WHERE id = ?";
$stmt = $conn->prepare($photo_query);
$stmt->bind_param("i", $candidate_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $candidate = $result->fetch_assoc();
    $photo_url = $candidate['photo_url'];
    
    // Delete the candidate from the database
    $delete_query = "DELETE FROM candidates WHERE id = ?";
    $stmt = $conn->prepare($delete_query);
    $stmt->bind_param("i", $candidate_id);
    
    if ($stmt->execute()) {
        // Delete the photo file if it exists
        if (!empty($photo_url)) {
            $photo_path = "../../uploads/candidates/" . $photo_url;
            if (file_exists($photo_path)) {
                unlink($photo_path);
            }
        }
        
        $_SESSION['success'] = "Candidate deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete candidate: " . $conn->error;
    }
} else {
    $_SESSION['error'] = "Candidate not found.";
}

header("Location: register_candidate.php?college=" . $college_code);
exit();
?>