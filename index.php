<?php
require_once 'config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $conn = getDBConnection();
    
    // Handle new Jira number submission
    if (isset($_POST['jira_number']) && !empty(trim($_POST['jira_number']))) {
        $jira_number = $conn->real_escape_string(trim($_POST['jira_number']));
        $sql = "INSERT INTO requests (jira_number, status) VALUES ('$jira_number', 'NEW')";
        if (!$conn->query($sql)) {
            $error = "Error adding Jira number: " . $conn->error;
        }
    }
    
    // Handle approval actions with checklists
    if (isset($_POST['approve']) && isset($_POST['request_id']) && isset($_POST['role'])) {
        $request_id = intval($_POST['request_id']);
        $role = $conn->real_escape_string($_POST['role']);
        
        // Get current request status
        $sql = "SELECT status FROM requests WHERE id = $request_id";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            $request = $result->fetch_assoc();
            $current_status = $request['status'];
            
            // Validate and update based on role and current status
            switch ($role) {
                case 'app_owner':
                    if ($current_status === 'NEW') {
                        // Validate ALL app owner checklist items are checked
                        if (isset($_POST['app_details_mentioned']) && isset($_POST['app_version_proper']) && isset($_POST['app_code_tested']) &&
                            $_POST['app_details_mentioned'] == '1' && $_POST['app_version_proper'] == '1' && $_POST['app_code_tested'] == '1') {
                            
                            $details_mentioned = intval($_POST['app_details_mentioned']);
                            $version_proper = intval($_POST['app_version_proper']);
                            $code_tested = intval($_POST['app_code_tested']);
                            $notes = $conn->real_escape_string($_POST['app_owner_notes'] ?? '');
                            
                            $update_sql = "UPDATE requests SET 
                                status = 'APP_OWNER_APPROVED',
                                app_details_mentioned = $details_mentioned,
                                app_version_proper = $version_proper,
                                app_code_tested = $code_tested,
                                app_owner_notes = '$notes',
                                app_owner_approved_at = NOW() 
                                WHERE id = $request_id";
                            
                            if (!$conn->query($update_sql)) {
                                $error = "Error updating approval: " . $conn->error;
                            } else {
                                $success = "Application Owner approval completed successfully!";
                            }
                        } else {
                            $error = "Please complete ALL Application Owner checklist items before approving!";
                        }
                    }
                    break;
                    
                case 'security':
                    if ($current_status === 'APP_OWNER_APPROVED') {
                        // Validate ALL security checklist items are checked
                        if (isset($_POST['security_guidance_followed']) && isset($_POST['security_others']) &&
                            $_POST['security_guidance_followed'] == '1' && $_POST['security_others'] == '1') {
                            
                            $guidance_followed = intval($_POST['security_guidance_followed']);
                            $others = intval($_POST['security_others']);
                            $notes = $conn->real_escape_string($_POST['security_notes'] ?? '');
                            
                            $update_sql = "UPDATE requests SET 
                                status = 'SECURITY_APPROVED',
                                security_guidance_followed = $guidance_followed,
                                security_others = $others,
                                security_notes = '$notes',
                                security_approved_at = NOW() 
                                WHERE id = $request_id";
                            
                            if (!$conn->query($update_sql)) {
                                $error = "Error updating approval: " . $conn->error;
                            } else {
                                $success = "Security approval completed successfully!";
                            }
                        } else {
                            $error = "Please complete ALL Security checklist items before approving!";
                        }
                    }
                    break;
                    
                case 'sre':
                    if ($current_status === 'SECURITY_APPROVED') {
                        // Validate ALL SRE checklist items are checked and risk category is selected
                        if (isset($_POST['sre_reliable']) && isset($_POST['sre_risk_category']) && 
                            isset($_POST['sre_monitoring_available']) && isset($_POST['sre_alerting_available']) && 
                            isset($_POST['sre_rollback_available']) &&
                            $_POST['sre_reliable'] == '1' && $_POST['sre_monitoring_available'] == '1' && 
                            $_POST['sre_alerting_available'] == '1' && $_POST['sre_rollback_available'] == '1' &&
                            !empty($_POST['sre_risk_category'])) {
                            
                            $reliable = intval($_POST['sre_reliable']);
                            $risk_category = $conn->real_escape_string($_POST['sre_risk_category']);
                            $monitoring = intval($_POST['sre_monitoring_available']);
                            $alerting = intval($_POST['sre_alerting_available']);
                            $rollback = intval($_POST['sre_rollback_available']);
                            $notes = $conn->real_escape_string($_POST['sre_notes'] ?? '');
                            
                            $update_sql = "UPDATE requests SET 
                                status = 'READY_TO_DEPLOY',
                                sre_reliable = $reliable,
                                sre_risk_category = '$risk_category',
                                sre_monitoring_available = $monitoring,
                                sre_alerting_available = $alerting,
                                sre_rollback_available = $rollback,
                                sre_notes = '$notes',
                                sre_approved_at = NOW() 
                                WHERE id = $request_id";
                            
                            if (!$conn->query($update_sql)) {
                                $error = "Error updating approval: " . $conn->error;
                            } else {
                                $success = "SRE approval completed successfully! Request is now READY TO DEPLOY!";
                            }
                        } else {
                            $error = "Please complete ALL SRE checklist items and select a risk category before approving!";
                        }
                    }
                    break;
            }
        } else {
            $error = "Request not found!";
        }
    }
    
    $conn->close();
    
    // Store messages in session for display after redirect
    if (isset($error)) {
        setcookie('error_message', $error, time() + 5, '/');
    }
    if (isset($success)) {
        setcookie('success_message', $success, time() + 5, '/');
    }
    
    // Redirect to avoid form resubmission
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Display messages from cookies (after redirect)
if (isset($_COOKIE['error_message'])) {
    $error = $_COOKIE['error_message'];
    setcookie('error_message', '', time() - 3600, '/'); // Clear cookie
}
if (isset($_COOKIE['success_message'])) {
    $success = $_COOKIE['success_message'];
    setcookie('success_message', '', time() - 3600, '/'); // Clear cookie
}

// Get all requests for display
$conn = getDBConnection();
$sql = "SELECT * FROM requests ORDER BY created_at DESC";
$result = $conn->query($sql);
$requests = [];

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }
} else {
    $error = "Error fetching requests: " . $conn->error;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Approval Workflow</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .status-badge {
            font-size: 0.8rem;
            padding: 0.4em 0.6em;
        }
        .table-actions {
            white-space: nowrap;
        }
        .alert {
            margin-bottom: 1rem;
        }
        .checklist-item {
            margin-bottom: 0.5rem;
        }
        .checklist-group {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
        }
        .modal-checklist {
            max-height: 70vh;
            overflow-y: auto;
        }
        .checklist-header {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 0.5rem;
            margin-bottom: 1rem;
        }
        .form-check-input:required:invalid {
            border-color: #dc3545;
        }
        .validation-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <h1 class="mb-4">Approval Workflow System</h1>
        
        <!-- Display messages -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Add Jira Number Form -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">Add New Jira Number for Approval</h5>
            </div>
            <div class="card-body">
                <form method="POST" class="row g-3">
                    <div class="col-auto">
                        <label for="jira_number" class="form-label">Jira Number:</label>
                    </div>
                    <div class="col-auto">
                        <input type="text" class="form-control" id="jira_number" name="jira_number" 
                               placeholder="e.g., PROJ-123" required>
                    </div>
                    <div class="col-auto">
                        <button type="submit" class="btn btn-primary">Submit for Approval</button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Requests Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">Approval Requests</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($requests)): ?>
                    <div class="p-3 text-center text-muted">
                        No approval requests found. Add a Jira number above to get started.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>ID</th>
                                    <th>Jira Number</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Checklist Progress</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($request['id']); ?></td>
                                        <td><?php echo htmlspecialchars($request['jira_number']); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'NEW' => 'bg-secondary',
                                                'APP_OWNER_APPROVED' => 'bg-primary',
                                                'SECURITY_APPROVED' => 'bg-warning',
                                                'SRE_APPROVED' => 'bg-info',
                                                'READY_TO_DEPLOY' => 'bg-success'
                                            ];
                                            $class = $status_class[$request['status']] ?? 'bg-secondary';
                                            ?>
                                            <span class="badge <?php echo $class; ?> status-badge">
                                                <?php echo $request['status']; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i', strtotime($request['created_at'])); ?></td>
                                        <td>
                                            <?php
                                            $total_checks = 0;
                                            $completed_checks = 0;
                                            
                                            // App Owner checks
                                            if ($request['status'] !== 'NEW') {
                                                $total_checks += 3;
                                                $completed_checks += ($request['app_details_mentioned'] + $request['app_version_proper'] + $request['app_code_tested']);
                                            }
                                            
                                            // Security checks
                                            if ($request['status'] === 'SECURITY_APPROVED' || $request['status'] === 'SRE_APPROVED' || $request['status'] === 'READY_TO_DEPLOY') {
                                                $total_checks += 2;
                                                $completed_checks += ($request['security_guidance_followed'] + $request['security_others']);
                                            }
                                            
                                            // SRE checks
                                            if ($request['status'] === 'SRE_APPROVED' || $request['status'] === 'READY_TO_DEPLOY') {
                                                $total_checks += 5;
                                                $completed_checks += ($request['sre_reliable'] + ($request['sre_risk_category'] ? 1 : 0) + $request['sre_monitoring_available'] + $request['sre_alerting_available'] + $request['sre_rollback_available']);
                                            }
                                            
                                            if ($total_checks > 0) {
                                                $percentage = round(($completed_checks / $total_checks) * 100);
                                                $color = $percentage == 100 ? 'bg-success' : ($percentage >= 50 ? 'bg-warning' : 'bg-danger');
                                                echo "<div class='progress' style='height: 20px; width: 100px;'>
                                                    <div class='progress-bar $color' role='progressbar' style='width: {$percentage}%' aria-valuenow='{$percentage}' aria-valuemin='0' aria-valuemax='100'>{$percentage}%</div>
                                                </div>";
                                            } else {
                                                echo "<span class='text-muted'>Not started</span>";
                                            }
                                            ?>
                                        </td>
                                        <td class="table-actions">
                                            <!-- App Owner Approve Button -->
                                            <?php if ($request['status'] === 'NEW'): ?>
                                                <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#appOwnerModal<?php echo $request['id']; ?>">
                                                    Approve as App Owner
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Security Approve Button -->
                                            <?php if ($request['status'] === 'APP_OWNER_APPROVED'): ?>
                                                <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#securityModal<?php echo $request['id']; ?>">
                                                    Approve as Security
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- SRE Approve Button -->
                                            <?php if ($request['status'] === 'SECURITY_APPROVED'): ?>
                                                <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#sreModal<?php echo $request['id']; ?>">
                                                    Approve as SRE
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($request['status'] === 'READY_TO_DEPLOY'): ?>
                                                <span class="badge bg-success">Ready to Deploy!</span>
                                            <?php endif; ?>
                                            
                                            <!-- View Details Button -->
                                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#detailsModal<?php echo $request['id']; ?>">
                                                View Details
                                            </button>
                                        </td>
                                    </tr>
                                    
                                    <!-- App Owner Approval Modal -->
                                    <div class="modal fade" id="appOwnerModal<?php echo $request['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">App Owner Approval Checklist</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" class="needs-validation" novalidate>
                                                    <div class="modal-body modal-checklist">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <input type="hidden" name="role" value="app_owner">
                                                        
                                                        <div class="checklist-header">
                                                            <h6>All checklist items must be completed before approval</h6>
                                                        </div>
                                                        
                                                        <div class="checklist-group">
                                                            <h6>Application Checklist</h6>
                                                            <div class="checklist-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="app_details_mentioned" value="1" id="details<?php echo $request['id']; ?>" required>
                                                                    <label class="form-check-label" for="details<?php echo $request['id']; ?>">
                                                                        Are details properly mentioned?
                                                                    </label>
                                                                    <div class="validation-message">This item is required</div>
                                                                </div>
                                                            </div>
                                                            <div class="checklist-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="app_version_proper" value="1" id="version<?php echo $request['id']; ?>" required>
                                                                    <label class="form-check-label" for="version<?php echo $request['id']; ?>">
                                                                        Is version numbering proper?
                                                                    </label>
                                                                    <div class="validation-message">This item is required</div>
                                                                </div>
                                                            </div>
                                                            <div class="checklist-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="app_code_tested" value="1" id="tested<?php echo $request['id']; ?>" required>
                                                                    <label class="form-check-label" for="tested<?php echo $request['id']; ?>">
                                                                        Is code properly tested?
                                                                    </label>
                                                                    <div class="validation-message">This item is required</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="app_owner_notes<?php echo $request['id']; ?>" class="form-label">Additional Notes:</label>
                                                            <textarea class="form-control" id="app_owner_notes<?php echo $request['id']; ?>" name="app_owner_notes" rows="3" placeholder="Any additional comments..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="approve" class="btn btn-primary">Approve</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Security Approval Modal -->
                                    <div class="modal fade" id="securityModal<?php echo $request['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Security Approval Checklist</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" class="needs-validation" novalidate>
                                                    <div class="modal-body modal-checklist">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <input type="hidden" name="role" value="security">
                                                        
                                                        <div class="checklist-header">
                                                            <h6>All checklist items must be completed before approval</h6>
                                                        </div>
                                                        
                                                        <div class="checklist-group">
                                                            <h6>Security Checklist</h6>
                                                            <div class="checklist-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="security_guidance_followed" value="1" id="security_guidance<?php echo $request['id']; ?>" required>
                                                                    <label class="form-check-label" for="security_guidance<?php echo $request['id']; ?>">
                                                                        Does it follow security guidance?
                                                                    </label>
                                                                    <div class="validation-message">This item is required</div>
                                                                </div>
                                                            </div>
                                                            <div class="checklist-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="security_others" value="1" id="security_others<?php echo $request['id']; ?>" required>
                                                                    <label class="form-check-label" for="security_others<?php echo $request['id']; ?>">
                                                                        Other security requirements met
                                                                    </label>
                                                                    <div class="validation-message">This item is required</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="security_notes<?php echo $request['id']; ?>" class="form-label">Additional Notes:</label>
                                                            <textarea class="form-control" id="security_notes<?php echo $request['id']; ?>" name="security_notes" rows="3" placeholder="Any security-specific comments..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="approve" class="btn btn-warning">Approve</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- SRE Approval Modal -->
                                    <div class="modal fade" id="sreModal<?php echo $request['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">SRE Approval Checklist</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <form method="POST" class="needs-validation" novalidate>
                                                    <div class="modal-body modal-checklist">
                                                        <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                                        <input type="hidden" name="role" value="sre">
                                                        
                                                        <div class="checklist-header">
                                                            <h6>All checklist items must be completed before approval</h6>
                                                        </div>
                                                        
                                                        <div class="checklist-group">
                                                            <h6>SRE Checklist</h6>
                                                            <div class="checklist-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="sre_reliable" value="1" id="reliable<?php echo $request['id']; ?>" required>
                                                                    <label class="form-check-label" for="reliable<?php echo $request['id']; ?>">
                                                                        Is it reliable?
                                                                    </label>
                                                                    <div class="validation-message">This item is required</div>
                                                                </div>
                                                            </div>
                                                            <div class="checklist-item">
                                                                <label class="form-label">Risk Category:</label>
                                                                <select class="form-select" name="sre_risk_category" required>
                                                                    <option value="">Select Risk Category</option>
                                                                    <option value="1">Category 1 - Low Risk</option>
                                                                    <option value="2">Category 2 - Medium Risk</option>
                                                                    <option value="3">Category 3 - High Risk</option>
                                                                    <option value="4">Category 4 - Critical Risk</option>
                                                                </select>
                                                                <div class="validation-message">Please select a risk category</div>
                                                            </div>
                                                            <div class="checklist-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="sre_monitoring_available" value="1" id="monitoring<?php echo $request['id']; ?>" required>
                                                                    <label class="form-check-label" for="monitoring<?php echo $request['id']; ?>">
                                                                        Is monitoring available?
                                                                    </label>
                                                                    <div class="validation-message">This item is required</div>
                                                                </div>
                                                            </div>
                                                            <div class="checklist-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="sre_alerting_available" value="1" id="alerting<?php echo $request['id']; ?>" required>
                                                                    <label class="form-check-label" for="alerting<?php echo $request['id']; ?>">
                                                                        Is alerting available?
                                                                    </label>
                                                                    <div class="validation-message">This item is required</div>
                                                                </div>
                                                            </div>
                                                            <div class="checklist-item">
                                                                <div class="form-check">
                                                                    <input class="form-check-input" type="checkbox" name="sre_rollback_available" value="1" id="rollback<?php echo $request['id']; ?>" required>
                                                                    <label class="form-check-label" for="rollback<?php echo $request['id']; ?>">
                                                                        Is rollback plan available?
                                                                    </label>
                                                                    <div class="validation-message">This item is required</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                        
                                                        <div class="mb-3">
                                                            <label for="sre_notes<?php echo $request['id']; ?>" class="form-label">Additional Notes:</label>
                                                            <textarea class="form-control" id="sre_notes<?php echo $request['id']; ?>" name="sre_notes" rows="3" placeholder="Any SRE-specific comments..."></textarea>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" name="approve" class="btn btn-info">Approve</button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- View Details Modal -->
                                    <div class="modal fade" id="detailsModal<?php echo $request['id']; ?>" tabindex="-1">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">Approval Details - <?php echo htmlspecialchars($request['jira_number']); ?></h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <!-- App Owner Details -->
                                                    <?php if ($request['app_owner_approved_at']): ?>
                                                        <div class="checklist-group">
                                                            <h6>Application Owner Approval</h6>
                                                            <p><strong>Approved at:</strong> <?php echo date('Y-m-d H:i', strtotime($request['app_owner_approved_at'])); ?></p>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Details Mentioned:</strong> <?php echo $request['app_details_mentioned'] ? '✅ Yes' : '❌ No'; ?></p>
                                                                    <p><strong>Version Proper:</strong> <?php echo $request['app_version_proper'] ? '✅ Yes' : '❌ No'; ?></p>
                                                                    <p><strong>Code Tested:</strong> <?php echo $request['app_code_tested'] ? '✅ Yes' : '❌ No'; ?></p>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty($request['app_owner_notes'])): ?>
                                                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($request['app_owner_notes']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- Security Details -->
                                                    <?php if ($request['security_approved_at']): ?>
                                                        <div class="checklist-group">
                                                            <h6>Security Approval</h6>
                                                            <p><strong>Approved at:</strong> <?php echo date('Y-m-d H:i', strtotime($request['security_approved_at'])); ?></p>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Security Guidance Followed:</strong> <?php echo $request['security_guidance_followed'] ? '✅ Yes' : '❌ No'; ?></p>
                                                                    <p><strong>Other Requirements:</strong> <?php echo $request['security_others'] ? '✅ Yes' : '❌ No'; ?></p>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty($request['security_notes'])): ?>
                                                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($request['security_notes']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <!-- SRE Details -->
                                                    <?php if ($request['sre_approved_at']): ?>
                                                        <div class="checklist-group">
                                                            <h6>SRE Approval</h6>
                                                            <p><strong>Approved at:</strong> <?php echo date('Y-m-d H:i', strtotime($request['sre_approved_at'])); ?></p>
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <p><strong>Reliable:</strong> <?php echo $request['sre_reliable'] ? '✅ Yes' : '❌ No'; ?></p>
                                                                    <p><strong>Risk Category:</strong> <?php echo $request['sre_risk_category'] ? 'Category ' . $request['sre_risk_category'] : 'Not set'; ?></p>
                                                                    <p><strong>Monitoring Available:</strong> <?php echo $request['sre_monitoring_available'] ? '✅ Yes' : '❌ No'; ?></p>
                                                                    <p><strong>Alerting Available:</strong> <?php echo $request['sre_alerting_available'] ? '✅ Yes' : '❌ No'; ?></p>
                                                                    <p><strong>Rollback Plan Available:</strong> <?php echo $request['sre_rollback_available'] ? '✅ Yes' : '❌ No'; ?></p>
                                                                </div>
                                                            </div>
                                                            <?php if (!empty($request['sre_notes'])): ?>
                                                                <p><strong>Notes:</strong> <?php echo htmlspecialchars($request['sre_notes']); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="modal-footer">
                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            var forms = document.querySelectorAll('.needs-validation');
            
            Array.prototype.slice.call(forms).forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
            
            // Custom validation for checkboxes
            var checkboxes = document.querySelectorAll('input[type="checkbox"][required]');
            checkboxes.forEach(function(checkbox) {
                checkbox.addEventListener('change', function() {
                    if (!this.checked) {
                        this.setCustomValidity('This checklist item must be completed.');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            });
        });
    </script>
</body>
</html>