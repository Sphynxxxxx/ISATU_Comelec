<?php
session_start();
require_once "../../backend/connections/config.php"; 

// Logout functionality
if(isset($_GET['logout'])) {
    session_destroy();
    header("Location: ../admin.php");
    exit();
}

// Function to get candidate image
function getCandidateImage($photo_url) {
    if (!empty($photo_url)) {
        $image_path = "../../uploads/candidates/" . $photo_url;
        if (file_exists($image_path)) {
            return $image_path;
        }
    }
    
    return "../assets/images/default-candidate.png";
}

// Get all positions for filtering
$positions_query = "SELECT * FROM positions ORDER BY display_order";
$positions_result = $conn->query($positions_query);
$positions = [];

if ($positions_result->num_rows > 0) {
    while ($row = $positions_result->fetch_assoc()) {
        $positions[] = $row;
    }
}

// Define college names for display
$college_names = [
    'sr' => 'Student Republic',
    'cas' => 'College of Arts and Sciences',
    'cea' => 'College of Engineering and Architecture',
    'coe' => 'College of Education',
    'cit' => 'College of Industrial Technology'
];

// Handle filters
$position_filter = isset($_GET['position']) ? $_GET['position'] : '';
$college_filter = isset($_GET['college']) ? $_GET['college'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query with filters
$query = "SELECT c.*, p.name as position_name 
          FROM candidates c 
          JOIN positions p ON c.position_id = p.id 
          WHERE 1=1";

$params = [];
$types = "";

if (!empty($position_filter)) {
    $query .= " AND c.position_id = ?";
    $params[] = $position_filter;
    $types .= "i";
}

if (!empty($college_filter)) {
    $query .= " AND c.college_code = ?";
    $params[] = $college_filter;
    $types .= "s";
}

if (!empty($search)) {
    $query .= " AND (c.name LIKE ? OR c.student_id LIKE ?)";
    $search_param = "%" . $search . "%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "ss";
}

$query .= " ORDER BY p.display_order, c.name";

// Prepare and execute query
$stmt = $conn->prepare($query);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
$candidates = [];

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $candidates[] = $row;
    }
}

// Current date and time
$current_datetime = date('F d, Y - h:i A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View All Candidates - ISATU Election System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        :root {
            --isatu-primary: #0c3b5d;    /* ISATU navy blue */
            --isatu-secondary: #f2c01d;  /* ISATU gold/yellow */
            --isatu-accent: #1a64a0;     /* ISATU lighter blue */
            --isatu-light: #e8f1f8;      /* Light blue background */
            --isatu-dark: #092c48;       /* Darker blue */
        }
        
        body {
            background-color: #f8f9fa;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            display: flex;
        }
        
        /* Sidebar styles */
        .sidebar {
            background-color: var(--isatu-primary);
            width: 280px;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            padding-top: 20px;
            color: white;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-collapsed {
            width: 70px;
        }
        
        .main-content {
            margin-left: 280px;
            width: calc(100% - 280px);
            transition: all 0.3s;
            padding: 20px;
            background-color: #f8f9fa;
            min-height: 100vh;
        }
        
        .main-content-expanded {
            margin-left: 70px;
            width: calc(100% - 70px);
        }
        
        .sidebar-header {
            padding: 0 20px 20px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
        }
        
        .isatu-logo {
            max-width: 100px;
            margin-bottom: 15px;
        }
        
        .logo-text {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            white-space: nowrap;
        }
        
        .logo-subtext {
            font-size: 0.9rem;
            opacity: 0.8;
            margin: 0;
            white-space: nowrap;
        }
        
        .nav-link {
            color: rgba(255, 255, 255, 0.85);
            border-radius: 8px;
            margin: 5px 15px;
            padding: 12px 15px;
            display: flex;
            align-items: center;
            transition: all 0.3s;
        }
        
        .nav-link:hover, .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        
        .nav-link i {
            margin-right: 15px;
            font-size: 1.2rem;
            width: 24px;
            text-align: center;
        }
        
        .nav-link span {
            white-space: nowrap;
        }
        
        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            background-color: var(--isatu-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            font-weight: bold;
            color: var(--isatu-primary);
        }
        
        .admin-name {
            font-size: 0.9rem;
            font-weight: 500;
            white-space: nowrap;
        }
        
        .admin-role {
            font-size: 0.75rem;
            opacity: 0.7;
            white-space: nowrap;
        }
        
        .toggle-btn {
            cursor: pointer;
            width: 30px;
            height: 30px;
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            transition: all 0.3s;
        }
        
        .toggle-btn:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        /* Page styles */
        .page-title {
            font-size: 1.8rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 25px;
            display: flex;
            align-items: center;
        }
        
        .page-title i {
            margin-right: 12px;
            font-size: 1.6rem;
        }
        
        /* Filter section */
        .filter-section {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .filter-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 15px;
        }
        
        /* Candidates grid */
        .candidates-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .candidate-card {
            background-color: white;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            position: relative;
        }
        
        .candidate-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.15);
        }
        
        .candidate-position {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: var(--isatu-secondary);
            color: var(--isatu-primary);
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 2;
        }
        
        .candidate-image-container {
            height: 250px;
            overflow: hidden;
            position: relative;
        }
        
        .candidate-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        
        .candidate-card:hover .candidate-image {
            transform: scale(1.05);
        }
        
        .candidate-details {
            padding: 20px;
            flex-grow: 1;
            display: flex;
            flex-direction: column;
        }
        
        .candidate-name {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--isatu-primary);
            margin-bottom: 5px;
        }
        
        .candidate-id {
            color: #6c757d;
            margin-bottom: 10px;
            font-size: 0.9rem;
        }
        
        .candidate-college {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-bottom: 15px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        
        .sr {
            background-color: rgba(0, 0, 0, 0.1);
            color: #000000;
        }
        
        .cas {
            background-color: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .cea {
            background-color: rgba(255, 140, 0, 0.1);
            color: #ff8c00;
        }
        
        .coe {
            background-color: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .cit {
            background-color: rgba(13, 110, 253, 0.1);
            color: #0d6efd;
        }
        
        .candidate-bio {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 15px;
            flex-grow: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }
        
        .candidate-actions {
            display: flex;
            gap: 10px;
            margin-top: auto;
        }
        
        .candidate-btn {
            flex: 1;
            text-align: center;
            padding: 8px;
            border-radius: 5px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        
        .view-btn {
            background-color: var(--isatu-primary);
            color: white;
        }
        
        .view-btn:hover {
            background-color: var(--isatu-dark);
            color: white;
        }
        
        .edit-btn {
            background-color: #ffc107;
            color: #212529;
        }
        
        .edit-btn:hover {
            background-color: #e0a800;
            color: #212529;
        }
        
        .delete-btn {
            background-color: #dc3545;
            color: white;
        }
        
        .delete-btn:hover {
            background-color: #c82333;
            color: white;
        }
        
        .empty-results {
            text-align: center;
            padding: 40px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
        }
        
        .empty-icon {
            font-size: 3rem;
            color: #dee2e6;
            margin-bottom: 15px;
        }
        
        .empty-text {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        /* Pagination styles */
        .pagination-container {
            display: flex;
            justify-content: center;
            margin: 30px 0;
        }
        
        .pagination .page-item.active .page-link {
            background-color: var(--isatu-primary);
            border-color: var(--isatu-primary);
        }
        
        .pagination .page-link {
            color: var(--isatu-primary);
        }
        
        .timestamp {
            font-size: 0.9rem;
            color: #6c757d;
            text-align: right;
            margin-top: 20px;
        }
        
        /* College-specific styling */
        .college-banner {
            padding: 15px;
            border-radius: 10px;
            background-size: cover;
            background-position: center;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        /* Custom background colors for each college */
        #sr-content .college-banner {
            background: linear-gradient(45deg, #000000, #2c2c2c);
        }
        
        #cas-content .college-banner {
            background: linear-gradient(45deg, #a70000, #dc3545);
        }
        
        #cea-content .college-banner {
            background: linear-gradient(45deg, #ff8c00, #ffc007);
        }
        
        #coe-content .college-banner {
            background: linear-gradient(45deg, #ffc107, #ffea00);
        }
        
        #cit-content .college-banner {
            background: linear-gradient(45deg, #0d6efd, #0dcaf0);
        }
        
        /* Custom color schemes for candidate cards based on college */
        #sr-content .candidate-card {
            border-top: 4px solid #000000;
        }
        
        #cas-content .candidate-card {
            border-top: 4px solid #dc3545;
        }
        
        #cea-content .candidate-card {
            border-top: 4px solid #ff8c00;
        }
        
        #coe-content .candidate-card {
            border-top: 4px solid #ffc107;
        }
        
        #cit-content .candidate-card {
            border-top: 4px solid #0d6efd;
        }
        
        /* Tab styling */
        .nav-tabs {
            border-bottom: 2px solid var(--isatu-light);
        }
        
        .nav-tabs .nav-link {
            color: #6c757d;
            font-weight: 500;
            border: none;
            padding: 10px 20px;
            margin-right: 5px;
            border-radius: 5px 5px 0 0;
            transition: all 0.3s;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--isatu-primary);
            background-color: rgba(12, 59, 93, 0.05);
            border: none;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--isatu-primary);
            background-color: transparent;
            border: none;
            border-bottom: 3px solid var(--isatu-primary);
            font-weight: 600;
        }
        
        /* Responsive tab navigation for mobile */
        @media (max-width: 768px) {
            .nav-tabs {
                flex-wrap: nowrap;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                white-space: nowrap;
                padding-bottom: 5px;
            }
            
            .nav-tabs .nav-item {
                float: none;
                display: inline-block;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <img src="../../assets/logo/ISAT-U-logo-2.png" alt="ISATU Logo" class="isatu-logo">
            <p class="logo-text">ISATU Admin</p>
            <p class="logo-subtext">Election System</p>
        </div>
        
        <div class="mt-4">
            <a href="../admin_dashboard.php" class="nav-link">
                <i class="bi bi-speedometer2"></i>
                <span>Dashboard</span>
            </a>
            <a href="../manage_students.php" class="nav-link">
                <i class="bi bi-people"></i>
                <span>Manage Students</span>
            </a>
            <a href="../manage_candidates.php" class="nav-link">
                <i class="bi bi-person-badge"></i>
                <span>Manage Candidates</span>
            </a>
            <a href="../election_results.php" class="nav-link">
                <i class="bi bi-bar-chart"></i>
                <span>Election Results</span>
            </a>
            <a href="view_all_candidates.php?logout=1" class="nav-link text-danger">
                <i class="bi bi-box-arrow-right"></i>
                <span>Logout</span>
            </a>
        </div>
        
        <div class="sidebar-footer">
            <div class="admin-info">
                <div class="admin-avatar">
                    A
                </div>
                <div>
                    <div class="admin-name">Admin</div>
                    <div class="admin-role">Administrator</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content" id="mainContent">
        <div class="page-title">
            <i class="bi bi-list-check"></i> View All Candidates
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="filter-title">
                <i class="bi bi-funnel me-2"></i> Filter Candidates
            </div>
            
            <form action="view_all_candidates.php" method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="position" class="form-label">Position</label>
                    <select name="position" id="position" class="form-select">
                        <option value="">All Positions</option>
                        <?php foreach ($positions as $position): ?>
                            <option value="<?php echo $position['id']; ?>" <?php echo ($position_filter == $position['id']) ? 'selected' : ''; ?>>
                                <?php echo $position['name']; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label for="college" class="form-label">College</label>
                    <select name="college" id="college" class="form-select">
                        <option value="">All Colleges</option>
                        <?php foreach ($college_names as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo ($college_filter == $code) ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name or ID">
                </div>
                
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="bi bi-search me-2"></i> Apply
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Candidates Section -->
        <?php if (empty($candidates)): ?>
        <div class="empty-results">
            <i class="bi bi-folder-x empty-icon"></i>
            <h4 class="empty-text">No candidates found</h4>
            <p class="text-muted">Try adjusting your search criteria or add new candidates.</p>
            <a href="manage_candidates.php" class="btn btn-primary">
                <i class="bi bi-person-plus me-2"></i> Register New Candidate
            </a>
        </div>
        <?php else: ?>
        
        <!-- Group candidates by college and position -->
        <?php
        // Organize candidates by college
        $candidates_by_college = [
            'sr' => [],
            'cas' => [],
            'cea' => [],
            'coe' => [],
            'cit' => []
        ];
        
        // Also organize by position within each college
        $candidates_by_college_position = [
            'sr' => [],
            'cas' => [],
            'cea' => [],
            'coe' => [],
            'cit' => []
        ];
        
        // Get all position names to use for grouping
        $position_names = [];
        foreach ($positions as $position) {
            $position_names[$position['id']] = $position['name'];
        }
        
        foreach ($candidates as $candidate) {
            $college_code = strtolower($candidate['college_code']);
            $position_id = $candidate['position_id'];
            $position_name = $candidate['position_name'];
            
            if (isset($candidates_by_college[$college_code])) {
                // Add to general college array
                $candidates_by_college[$college_code][] = $candidate;
                
                // Add to position-specific array within college
                if (!isset($candidates_by_college_position[$college_code][$position_name])) {
                    $candidates_by_college_position[$college_code][$position_name] = [];
                }
                
                $candidates_by_college_position[$college_code][$position_name][] = $candidate;
            }
        }
        
        // Define position display order (common positions across colleges)
        $position_display_order = [
            'President', 
            'Vice President', 
            'Secretary',
            'Treasurer',
            'Auditor',
            'PIO',
            'Senator',
            'Governor', 
            'Vice Governor',
            'Board Member'
        ];
        ?>
        
        <!-- College tabs navigation -->
        <ul class="nav nav-tabs mb-4" id="collegeTab" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all-content" type="button" role="tab" aria-selected="true">
                    All Candidates
                </button>
            </li>
            <?php foreach ($college_names as $code => $name): 
                if (!empty($candidates_by_college[$code])): // Only show tabs for colleges with candidates
            ?>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="<?php echo $code; ?>-tab" data-bs-toggle="tab" data-bs-target="#<?php echo $code; ?>-content" type="button" role="tab" aria-selected="false">
                    <?php echo $name; ?>
                </button>
            </li>
            <?php 
                endif;
            endforeach; 
            ?>
        </ul>
        
        <!-- Tab content for each college -->
        <div class="tab-content" id="collegeTabContent">
            <!-- All candidates tab -->
            <div class="tab-pane fade show active" id="all-content" role="tabpanel">
                <div class="candidates-container">
                    <?php foreach ($candidates as $candidate): ?>
                    <div class="candidate-card">
                        <div class="candidate-position"><?php echo $candidate['position_name']; ?></div>
                        <div class="candidate-image-container">
                            <img src="<?php echo getCandidateImage($candidate['photo_url']); ?>" alt="<?php echo $candidate['name']; ?>" class="candidate-image">
                        </div>
                        <div class="candidate-details">
                            <div class="candidate-name"><?php echo $candidate['name']; ?></div>
                            <div class="candidate-college <?php echo strtolower($candidate['college_code']); ?>">
                                <?php echo strtoupper($candidate['college_code']); ?>
                            </div>
                            <div class="candidate-bio">
                                <?php 
                                if (!empty($candidate['platform'])) {
                                    echo $candidate['platform'];
                                } else {
                                    echo 'No platform details available.';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delete Confirmation Modal -->
                    <div class="modal fade" id="deleteModal<?php echo $candidate['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $candidate['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $candidate['id']; ?>">Confirm Deletion</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete <strong><?php echo $candidate['name']; ?></strong> from the candidates list?</p>
                                    <p class="text-danger">This action cannot be undone.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <a href="control/delete_candidate.php?id=<?php echo $candidate['id']; ?>" class="btn btn-danger">Delete Candidate</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Individual college tabs -->
            <?php foreach ($college_names as $code => $name): 
                if (!empty($candidates_by_college[$code])): // Only create tabs for colleges with candidates
            ?>
            <div class="tab-pane fade" id="<?php echo $code; ?>-content" role="tabpanel">
                <div class="college-header mb-4">
                    <h3 class="text-center mb-3"><?php echo $name; ?> Candidates</h3>
                    <div class="college-banner py-4 mb-4 text-center">
                        <div class="college-banner-content">
                            <div class="d-flex justify-content-center align-items-center mb-3">
                                <?php if ($code == 'sr'): ?>
                                    <img src="../../assets/logo/STUDENT REPUBLIC LOGO.png" alt="SR Logo" class="college-logo me-2" style="width: 50px; height: 50px;">
                                <?php elseif ($code == 'cas'): ?>
                                    <img src="../../assets/logo/CASSC.jpg" alt="CAS Logo" class="college-logo me-2" style="width: 50px; height: 50px;">
                                <?php elseif ($code == 'cea'): ?>
                                    <img src="../../assets/logo/CEASC.jpg" alt="CEA Logo" class="college-logo me-2" style="width: 50px; height: 50px;">
                                <?php elseif ($code == 'coe'): ?>
                                    <img src="../../assets/logo/COESC.jpg" alt="COE Logo" class="college-logo me-2" style="width: 50px; height: 50px;">
                                <?php elseif ($code == 'cit'): ?>
                                    <img src="../../assets/logo/CITSC.png" alt="CIT Logo" class="college-logo me-2" style="width: 50px; height: 50px;">
                                <?php endif; ?>
                                <h4 class="mb-0 text-white"><?php echo $name; ?></h4>
                            </div>
                            <div class="college-count-large text-white"><?php echo count($candidates_by_college[$code]); ?> Candidates</div>
                        </div>
                    </div>
                </div>
                
                <div class="candidates-container">
                    <?php foreach ($candidates_by_college[$code] as $candidate): ?>
                    <div class="candidate-card">
                        <div class="candidate-position"><?php echo $candidate['position_name']; ?></div>
                        <div class="candidate-image-container">
                            <img src="<?php echo getCandidateImage($candidate['photo_url']); ?>" alt="<?php echo $candidate['name']; ?>" class="candidate-image">
                        </div>
                        <div class="candidate-details">
                            <div class="candidate-name"><?php echo $candidate['name']; ?></div>
                            <div class="candidate-college <?php echo $code; ?>">
                                <?php echo strtoupper($code); ?>
                            </div>
                            <div class="candidate-bio">
                                <?php 
                                if (!empty($candidate['platform'])) {
                                    echo $candidate['platform'];
                                } else {
                                    echo 'No platform details available.';
                                }
                                ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Delete Confirmation Modal -->
                    <div class="modal fade" id="deleteModal<?php echo $candidate['id']; ?>" tabindex="-1" aria-labelledby="deleteModalLabel<?php echo $candidate['id']; ?>" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="deleteModalLabel<?php echo $candidate['id']; ?>">Confirm Deletion</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                </div>
                                <div class="modal-body">
                                    <p>Are you sure you want to delete <strong><?php echo $candidate['name']; ?></strong> from the candidates list?</p>
                                    <p class="text-danger">This action cannot be undone.</p>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <a href="control/delete_candidate.php?id=<?php echo $candidate['id']; ?>" class="btn btn-danger">Delete Candidate</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php 
                endif;
            endforeach; 
            ?>
        </div>
        
        <!-- Add pagination here if needed -->
        <?php endif; ?>
        
        <div class="timestamp">
            <i class="bi bi-clock"></i> Last updated: <?php echo $current_datetime; ?>
        </div>
    </div>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle sidebar functionality
        document.getElementById('toggleBtn').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleIcon = document.getElementById('toggleIcon');
            
            sidebar.classList.toggle('sidebar-collapsed');
            mainContent.classList.toggle('main-content-expanded');
            
            if (sidebar.classList.contains('sidebar-collapsed')) {
                toggleIcon.classList.remove('bi-chevron-left');
                toggleIcon.classList.add('bi-chevron-right');
            } else {
                toggleIcon.classList.remove('bi-chevron-right');
                toggleIcon.classList.add('bi-chevron-left');
            }
        });
    </script>
</body>
</html>