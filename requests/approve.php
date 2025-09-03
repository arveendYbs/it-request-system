<?php
/**
 * Approve/Reject Request Page
 * requests/approve.php
 */

require_once '../includes/auth.php';
requireLogin();

$request_id = $_GET['id'] ?? '';
$current_user = getCurrentUser();

if (empty($request_id) || !is_numeric($request_id)) {
    $_SESSION['error_message'] = 'Invalid request ID.';
    header('Location: ./');
    exit();
}

// Get request details
$request = fetchOne($pdo, "
    SELECT r.*, u.name as user_name, u.email as user_email, u.reporting_manager_id,
           c.name as category_name, sc.name as subcategory_name,
           co.name as company_name, d.name as department_name,
           m.role as manager_role
    FROM requests r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    JOIN subcategories sc ON r.subcategory_id = sc.id
    JOIN companies co ON u.company_id = co.id
    JOIN departments d ON u.department_id = d.id
    LEFT JOIN users m ON u.reporting_manager_id = m.id
    WHERE r.id = ?
", [$request_id]);

if (!$request) {
    $_SESSION['error_message'] = 'Request not found.';
    header('Location: ./');
    exit();
}

// Check if user can approve this request
if (!$auth->canApproveRequest($request_id)) {
    $_SESSION['error_message'] = 'You do not have permission to approve this request.';
    header('Location: view.php?id=' . $request_id);
    exit();
}

$errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $remarks = trim($_POST['remarks'] ?? '');
    $approval_remarks = trim($_POST['approval_remarks'] ?? ''); // approval remarks 
    
    if (!in_array($action, ['approve', 'reject'])) {
        $errors[] = 'Invalid action.';
    }
    
    if ($action === 'reject' && empty($remarks)) {
        $errors[] = 'Rejection remarks are required.';
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            if ($action === 'approve') {
                if ($current_user['role'] === 'Manager') {
                    // Manager approval
                    executeQuery($pdo, "
                        UPDATE requests 
                        SET status = 'Pending IT HOD',
                            approved_by_manager_id = ?,
                            approved_by_manager_date = NOW(),
                            manager_remarks = ?, 
                            updated_at = NOW()
                        WHERE id = ?
                    ", [$current_user['id'], $approval_remarks, $request_id]);
                    
                    $_SESSION['success_message'] = 'Request approved successfully! It will now be reviewed by IT Manager.';
                    
                } elseif ($current_user['role'] === 'IT Manager' || $current_user['role'] === 'Admin') {
                    // IT Manager final approval
                    // Check if IT Manager is approving as direct manager or final approver
                    if ($current_user['role'] === 'IT Manager' && 
                        $request['reporting_manager_id'] == $current_user['id'] && 
                        $request['status'] === 'Pending IT HOD') {
                        // IT Manager is the direct reporting manager - give final approval
                        executeQuery($pdo, "
                            UPDATE requests 
                            SET status = 'Approved',
                                approved_by_manager_id = ?,
                                approved_by_manager_date = NOW(),
                                approved_by_it_manager_id = ?,
                                approved_by_it_manager_date = NOW(),
                                manager_remarks = ?,
                                it_manager_remarks = ?,
                                updated_at = NOW()
                            WHERE id = ?
                        ", [$current_user['id'], $current_user['id'], $request['manager_remarks'], $approval_remarks, $request_id]); // Note the changes here
                    } else {
                        // Regular IT Manager final approval
                        executeQuery($pdo, "
                            UPDATE requests 
                            SET status = 'Approved',
                                approved_by_it_manager_id = ?,
                                approved_by_it_manager_date = NOW(),
                                updated_at = NOW(),
                                it_manager_remarks = ?
                            WHERE id = ?
                        ", [$current_user['id'], $approval_remarks, $request_id]);
                    }
                    
                    $_SESSION['success_message'] = 'Request approved successfully!';
                }
                
            } else { // reject
                executeQuery($pdo, "
                    UPDATE requests 
                    SET status = 'Rejected',
                        rejection_remarks = ?,
                        rejected_by_id = ?,
                        rejected_date = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ", [$remarks, $current_user['id'], $request_id]);
                
                $_SESSION['success_message'] = 'Request rejected successfully.';
            }
            
            $pdo->commit();
            header('Location: view.php?id=' . $request_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to process request: ' . $e->getMessage();
        }
    }
}

$page_title = 'Approve Request #' . $request['id'];

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-check-lg me-2"></i>Approve Request #<?php echo $request['id']; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view.php?id=<?php echo $request['id']; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Request
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="bi bi-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <!-- Request Summary -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Request Summary</h5>
            </div>
            <div class="card-body">
                <h4 class="mb-3"><?php echo htmlspecialchars($request['title']); ?></h4>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Requester:</strong><br>
                        <?php echo htmlspecialchars($request['user_name']); ?><br>
                        <small class="text-muted"><?php echo htmlspecialchars($request['user_email']); ?></small>
                    </div>
                    <div class="col-md-6">
                        <strong>Department:</strong><br>
                        <?php echo htmlspecialchars($request['department_name']); ?>, <?php echo htmlspecialchars($request['company_name']); ?>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <strong>Category:</strong><br>
                        <span class="badge bg-secondary"><?php echo htmlspecialchars($request['category_name']); ?></span>
                        <small class="text-muted ms-2"><?php echo htmlspecialchars($request['subcategory_name']); ?></small>
                    </div>
                    <div class="col-md-6">
                        <strong>Current Status:</strong><br>
                        <?php
                        $status_class = [
                            'Pending Manager' => 'warning',
                            'Approved by Manager' => 'info',
                            'Pending IT HOD' => 'warning',
                            'Approved' => 'success',
                            'Rejected' => 'danger'
                        ];
                        $class = $status_class[$request['status']] ?? 'secondary';
                        ?>
                        <span class="badge bg-<?php echo $class; ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                    </div>
                </div>
                
                <div class="mb-3">
                    <strong>Description:</strong><br>
                    <p class="text-break mt-2"><?php echo nl2br(htmlspecialchars($request['description'])); ?></p>
                </div>
                
                <div class="mb-3">
                    <strong>Created:</strong>
                    <span class="text-muted"><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></span>
                </div>
            </div>
        </div>
        
        <!-- Approval Form -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clipboard-check me-2"></i>Review & Decision
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" id="approvalForm">
                    <div class="mb-4">
                        <label class="form-label">Decision <span class="text-danger">*</span></label>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="action" id="approve" value="approve" required>
                                    <label class="form-check-label" for="approve">
                                        <i class="bi bi-check-circle text-success me-2"></i>
                                        <strong>Approve Request</strong>
                                        <br>
                                        <small class="text-muted">
                                            <?php if ($current_user['role'] === 'Manager'): ?>
                                                Forward to IT Manager for final approval
                                            <?php else: ?>
                                                Grant final approval and complete the request
                                            <?php endif; ?>
                                        </small>
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="action" id="reject" value="reject" required>
                                    <label class="form-check-label" for="reject">
                                        <i class="bi bi-x-circle text-danger me-2"></i>
                                        <strong>Reject Request</strong>
                                        <br>
                                        <small class="text-muted">Deny the request with explanation</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    

                    <div class="mb-4" id="approvalRemarksSection" style="display: none;">
                        <label for="approval_remarks" class="form-label">Approval Remarks (Optional)</label>
                        <textarea class="form-control" id="approval_remarks" name="approval_remarks" rows="4" 
                                placeholder="Add any notes or context for the requester..."></textarea>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            These remarks will be visible to the requester.
                        </div>
                    </div>

                    <div class="mb-4" id="remarksSection" style="display: none;">
                        <label for="remarks" class="form-label">Rejection Remarks <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="4" 
                                  placeholder="Please provide a clear explanation for rejecting this request..."></textarea>
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Be specific about why the request is being rejected and what steps (if any) the requester can take.
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="bi bi-info-circle me-2"></i>Review Guidelines
                        </h6>
                        <ul class="mb-0 small">
                            <li>Verify that the request is complete and necessary</li>
                            <li>Check if the request follows company IT policies</li>
                            <li>Consider security implications and budget constraints</li>
                            <li>Provide clear feedback if rejecting the request</li>
                        </ul>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $request['id']; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary" id="submitBtn">
                            <i class="bi bi-check-lg me-1"></i>Submit Decision
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <!-- Approval Help -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-question-circle me-2"></i>Approval Guidelines
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Your Role:</strong><br>
                    <span class="badge bg-primary"><?php echo htmlspecialchars($current_user['role']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>What happens next:</strong><br>
                    <?php if ($current_user['role'] === 'Manager'): ?>
                        <small class="text-muted">
                            If approved, the request will be forwarded to the IT Manager for final approval.
                            If rejected, the requester will be notified with your remarks.
                        </small>
                    <?php else: ?>
                        <small class="text-muted">
                            If approved, the request will be marked as completed and the requester will be notified.
                            If rejected, the request will be closed with your remarks.
                        </small>
                    <?php endif; ?>
                </div>
                
                <hr>
                
                <div class="small">
                    <strong>Common rejection reasons:</strong>
                    <ul class="mt-2 mb-0">
                        <li>Insufficient information provided</li>
                        <li>Request doesn't follow IT policies</li>
                        <li>Security concerns</li>
                        <li>Budget constraints</li>
                        <li>Alternative solutions available</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Request History -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Request Timeline
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline-simple">
                    <div class="timeline-item">
                        <i class="bi bi-plus-circle text-primary"></i>
                        <div>
                            <strong>Request Created</strong><br>
                            <small class="text-muted">
                                <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                            </small>
                        </div>
                    </div>
                    
                    <?php if ($request['approved_by_manager_date']): ?>
                        <div class="timeline-item">
                            <i class="bi bi-check-circle text-success"></i>
                            <div>
                                <strong>Manager Approved</strong><br>
                                <small class="text-muted">
                                    <?php echo date('M j, Y g:i A', strtotime($request['approved_by_manager_date'])); ?>
                                </small>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="timeline-item current">
                        <i class="bi bi-arrow-right-circle text-warning"></i>
                        <div>
                            <strong>Awaiting Your Decision</strong><br>
                            <small class="text-muted">Current step</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline-simple .timeline-item {
    display: flex;
    align-items: start;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #eee;
}

.timeline-simple .timeline-item:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.timeline-simple .timeline-item i {
    margin-right: 10px;
    margin-top: 2px;
    font-size: 1.1rem;
}

.timeline-simple .timeline-item.current {
    background-color: #fff3cd;
    border-radius: 8px;
    padding: 10px;
    margin: 0 -10px 15px -10px;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const approveRadio = document.getElementById('approve');
    const rejectRadio = document.getElementById('reject');
    const approvalRemarksSection = document.getElementById('approvalRemarksSection'); // New: Reference to the approval remarks section
    const remarksSection = document.getElementById('remarksSection');
    const remarksTextarea = document.getElementById('remarks');
    const submitBtn = document.getElementById('submitBtn');
    
    function toggleRemarks() {
        if (rejectRadio.checked) {
            remarksSection.style.display = 'block';
            approvalRemarksSection.style.display = 'none'; // Hide the approval remarks
            remarksTextarea.required = true;
            submitBtn.innerHTML = '<i class="bi bi-x-lg me-1"></i>Reject Request';
            submitBtn.className = 'btn btn-danger';
        } else if (approveRadio.checked) {
            remarksSection.style.display = 'none';
            approvalRemarksSection.style.display = 'block'; // Show the approval remarks
            remarksTextarea.required = false; // Rejection remarks are no longer required
            remarksTextarea.value = ''; // Clear rejection remarks
            submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Approve Request';
            submitBtn.className = 'btn btn-success';
        }
    }
    
    approveRadio.addEventListener('change', toggleRemarks);
    rejectRadio.addEventListener('change', toggleRemarks);
    
    // Call the function on page load to set the initial state
    toggleRemarks();

    // Confirmation before submit
    document.getElementById('approvalForm').addEventListener('submit', function(e) {
        const action = document.querySelector('input[name="action"]:checked').value;
        const message = action === 'approve' 
            ? 'Are you sure you want to approve this request?' 
            : 'Are you sure you want to reject this request?';
            
        if (!confirm(message)) {
            e.preventDefault();
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>