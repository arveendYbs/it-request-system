<?php
/**
 * Delete Request
 * requests/delete.php
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
    SELECT r.*, u.name as user_name
    FROM requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ?
", [$request_id]);

if (!$request) {
    $_SESSION['error_message'] = 'Request not found.';
    header('Location: ./');
    exit();
}

// Check permissions to delete
$can_delete = false;

if (hasRole(['Admin'])) {
    $can_delete = true; // Admin can delete any request
} elseif ($request['user_id'] == $current_user['id'] && $request['status'] === 'Pending Manager') {
    $can_delete = true; // Owner can delete only if status is Pending HOD (before first approval) // Change to Pending HOD
}

if (!$can_delete) {
    $_SESSION['error_message'] = 'You do not have permission to delete this request. Only request owners can delete requests before first approval, or administrators can delete any request.';
    header('Location: view.php?id=' . $request_id);
    exit();
}

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // Delete attachments files
        $attachments = fetchAll($pdo, "SELECT stored_filename FROM request_attachments WHERE request_id = ?", [$request_id]);
        foreach ($attachments as $attachment) {
            $file_path = '../uploads/' . $attachment['stored_filename'];
            if (file_exists($file_path)) {
                unlink($file_path);
            }
        }
        
        // Delete attachment records
        executeQuery($pdo, "DELETE FROM request_attachments WHERE request_id = ?", [$request_id]);
        
        // Delete request
        executeQuery($pdo, "DELETE FROM requests WHERE id = ?", [$request_id]);
        
        $pdo->commit();
        $_SESSION['success_message'] = 'Request deleted successfully.';
        header('Location: ./');
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error_message'] = 'Failed to delete request: ' . $e->getMessage();
        header('Location: view.php?id=' . $request_id);
        exit();
    }
}

$page_title = 'Delete Request #' . $request['id'];
include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-trash me-2"></i>Delete Request #<?php echo $request['id']; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view.php?id=<?php echo $request_id; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Request
        </a>
    </div>
</div>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card border-danger">
            <div class="card-header bg-danger text-white">
                <h5 class="card-title mb-0">
                    <i class="bi bi-exclamation-triangle me-2"></i>Confirm Deletion
                </h5>
            </div>
            <div class="card-body">
                <div class="alert alert-warning">
                    <strong>Warning!</strong> This action cannot be undone.
                </div>
                
                <p>Are you sure you want to delete the following request?</p>
                
                <div class="mb-3">
                    <strong>Request ID:</strong> #<?php echo $request['id']; ?><br>
                    <strong>Title:</strong> <?php echo htmlspecialchars($request['title']); ?><br>
                    <strong>Requester:</strong> <?php echo htmlspecialchars($request['user_name']); ?><br>
                    <strong>Status:</strong> 
                    <?php
                    $status_class = [
                        'Pending HOD' => 'info',
                        'Approved by Manager' => 'info',
                        'Pending IT HOD' => 'warning',
                        'Approved' => 'success',
                        'Rejected' => 'danger'
                    ];
                    $class = $status_class[$request['status']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?php echo $class; ?>"><?php echo htmlspecialchars($request['status']); ?></span><br>
                    <strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?>
                </div>
                
                <?php
                $attachment_count = fetchOne($pdo, "SELECT COUNT(*) as count FROM request_attachments WHERE request_id = ?", [$request_id])['count'];
                if ($attachment_count > 0): ?>
                    <div class="alert alert-info">
                        <i class="bi bi-paperclip me-1"></i>
                        This request has <?php echo $attachment_count; ?> attachment(s) that will also be permanently deleted.
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $request_id; ?>" class="btn btn-secondary">
                            <i class="bi bi-x-lg me-1"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-trash me-1"></i>Delete Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>