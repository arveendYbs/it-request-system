<?php
/**
 * View Request Page
 * requests/view.php
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

// Get request details with all related information
$request = fetchOne($pdo, "
    SELECT r.*, u.name as user_name, u.email as user_email,
           c.name as category_name, sc.name as subcategory_name,
           co.name as company_name, d.name as department_name,
           s.name as site_name,
           am.name as approved_by_manager_name,
           aim.name as approved_by_it_manager_name,
           rb.name as rejected_by_name
    FROM requests r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    JOIN subcategories sc ON r.subcategory_id = sc.id
    JOIN companies co ON u.company_id = co.id
    JOIN departments d ON u.department_id = d.id
    LEFT JOIN sites s ON r.site_id = s.id
    LEFT JOIN users am ON r.approved_by_manager_id = am.id
    LEFT JOIN users aim ON r.approved_by_it_manager_id = aim.id
    LEFT JOIN users rb ON r.rejected_by_id = rb.id
    WHERE r.id = ?
", [$request_id]);

if (!$request) {
    $_SESSION['error_message'] = 'Request not found.';
    header('Location: ./');
    exit();
}

// Check if user can view this request
$can_view = false;

if (hasRole(['Admin'])) {
    $can_view = true;
} elseif ($request['user_id'] == $current_user['id']) {
    $can_view = true;
} elseif (hasRole(['Manager']) && $auth->canManageUser($request['user_id'])) {
    $can_view = true;
} elseif (hasRole(['IT Manager'])) {
    $can_view = true;
}

if (!$can_view) {
    http_response_code(403);
    $_SESSION['error_message'] = 'You do not have permission to view this request.';
    header('Location: ./');
    exit();
}

// Get attachments
$attachments = fetchAll($pdo, "
    SELECT * FROM request_attachments WHERE request_id = ? ORDER BY uploaded_at
", [$request_id]);

$page_title = 'Request #' . $request['id'];

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-file-text me-2"></i>Request #<?php echo $request['id']; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="./" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Requests
            </a>
            
            <?php if ($auth->canEditRequest($request['id'])): ?>
                <a href="edit.php?id=<?php echo $request['id']; ?>" class="btn btn-outline-primary">
                    <i class="bi bi-pencil me-1"></i>Edit
                </a>
            <?php endif; ?>
            
            <?php if ($auth->canApproveRequest($request['id'])): ?>
                <a href="approve.php?id=<?php echo $request['id']; ?>" class="btn btn-success">
                    <i class="bi bi-check-lg me-1"></i>Approve/Reject
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8">
        <!-- Request Details -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Request Details</h5>
                <?php
                $status_class = [
                    'Pending HOD' => 'warning',
                    'Approved by Manager' => 'info',
                    'Pending IT HOD' => 'warning',
                    'Approved' => 'success',
                    'Rejected' => 'danger'
                ];
                $class = $status_class[$request['status']] ?? 'secondary';
                ?>
                <span class="badge bg-<?php echo $class; ?> fs-6">
                    <?php echo htmlspecialchars($request['status']); ?>
                </span>
            </div>
            <div class="card-body">
                <h4 class="mb-3"><?php echo htmlspecialchars($request['title']); ?></h4>
                
                <div class="mb-4">
                    <h6 class="text-muted">Description</h6>
                    <p class="text-break"><?php echo nl2br(htmlspecialchars($request['description'])); ?></p>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-muted">Category</h6>
                        <p>
                            <span class="badge bg-secondary me-2"><?php echo htmlspecialchars($request['category_name']); ?></span>
                            <small class="text-muted"><?php echo htmlspecialchars($request['subcategory_name']); ?></small>
                        </p>
                    </div>

                    <div class="col-md-6">
                        <h6 class="text-muted">Site/Location</h6>
                        <p>
                            <?php if ($request['site_name']): ?>
                                <span class="badge bg-info"><?php echo htmlspecialchars($request['site_name']); ?></span>
                            <?php else: ?>
                                <small class="text-muted">No site specified</small>
                            <?php endif; ?>
                        </p>
                    </div>
                
                    <div class="col-md-6">
                        <h6 class="text-muted">Created Date</h6>
                        <p><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></p>
                    </div>
            
                 <div class="col-md-6">
                        <h6 class="text-muted">Last Updated</h6>
                        <p><?php echo date('M j, Y g:i A', strtotime($request['updated_at'])); ?></p>
                    </div>
                </div>
                
                <?php if ($request['rejection_remarks']): ?>
                    <div class="alert alert-danger">
                        <h6 class="alert-heading">
                            <i class="bi bi-x-circle me-2"></i>Rejection Remarks
                        </h6>
                        <p class="mb-0"><?php echo nl2br(htmlspecialchars($request['rejection_remarks'])); ?></p>
                        <?php if ($request['rejected_by_name']): ?>
                            <hr>
                            <small class="text-muted">
                                Rejected by <?php echo htmlspecialchars($request['rejected_by_name']); ?>
                                on <?php echo date('M j, Y g:i A', strtotime($request['rejected_date'])); ?>
                            </small>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Attachments -->
        <?php if (!empty($attachments)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-paperclip me-2"></i>Attachments (<?php echo count($attachments); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <div class="me-3">
                                        <?php if (strpos($attachment['file_type'], 'image/') === 0): ?>
                                            <i class="bi bi-image text-primary" style="font-size: 2rem;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-file-pdf text-danger" style="font-size: 2rem;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($attachment['original_filename']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo number_format($attachment['file_size'] / 1024, 1); ?> KB •
                                            <?php echo date('M j, Y', strtotime($attachment['uploaded_at'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <a href="../uploads/<?php echo htmlspecialchars($attachment['stored_filename']); ?>" 
                                           class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="bi bi-download"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="col-lg-4">
        <!-- Request Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Request Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Requester:</strong><br>
                    <span><?php echo htmlspecialchars($request['user_name']); ?></span><br>
                    <small class="text-muted"><?php echo htmlspecialchars($request['user_email']); ?></small>
                </div>
                
                <div class="mb-3">
                    <strong>Department:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($request['department_name']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Company:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($request['company_name']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Status:</strong><br>
                    <span class="badge bg-<?php echo $class; ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Last Updated:</strong><br>
                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($request['updated_at'])); ?></small>
                </div>
            </div>
        </div>
        
        <!-- Approval Timeline -->
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Approval Timeline
                </h6>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <!-- Request Created -->
                    <div class="timeline-item">
                        <div class="timeline-marker bg-primary"></div>
                        <div class="timeline-content">
                            <h6 class="mb-1">Request Created</h6>
                            <p class="mb-1 text-muted">by <?php echo htmlspecialchars($request['user_name']); ?></p>
                            <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></small>
                        </div>
                    </div>
                    
                    <!-- Manager Approval -->
                    <?php if ($request['approved_by_manager_date']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Approved by Manager</h6>
                                <p class="mb-1 text-muted">by <?php echo htmlspecialchars($request['approved_by_manager_name']); ?></p>
                                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($request['approved_by_manager_date'])); ?></small>
                            </div>
                        </div>
                    <?php elseif (in_array($request['status'], ['Pending Manager'])): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-warning"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Pending Manager Approval</h6>
                                <small class="text-muted">Waiting for manager review</small>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- IT Manager Approval -->
                    <?php if ($request['approved_by_it_manager_date']): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-success"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Approved by IT Manager</h6>
                                <p class="mb-1 text-muted">by <?php echo htmlspecialchars($request['approved_by_it_manager_name']); ?></p>
                                <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($request['approved_by_it_manager_date'])); ?></small>
                            </div>
                        </div>
                    <?php elseif (in_array($request['status'], ['Approved by Manager', 'Pending IT HOD'])): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-warning"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Pending IT Manager Approval</h6>
                                <small class="text-muted">Waiting for IT manager review</small>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Rejection -->
                    <?php if ($request['status'] === 'Rejected'): ?>
                        <div class="timeline-item">
                            <div class="timeline-marker bg-danger"></div>
                            <div class="timeline-content">
                                <h6 class="mb-1">Request Rejected</h6>
                                <?php if ($request['rejected_by_name']): ?>
                                    <p class="mb-1 text-muted">by <?php echo htmlspecialchars($request['rejected_by_name']); ?></p>
                                <?php endif; ?>
                                <?php if ($request['rejected_date']): ?>
                                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($request['rejected_date'])); ?></small>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 30px;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.timeline-item {
    position: relative;
    margin-bottom: 30px;
}

.timeline-marker {
    position: absolute;
    left: -37px;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px #dee2e6;
}

.timeline-content {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    border-left: 3px solid #007bff;
}

.timeline-item:last-child {
    margin-bottom: 0;
}
</style>

<?php include '../includes/footer.php'; ?>