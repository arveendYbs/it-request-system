<?php
/**
 * View User Profile Page
 * users/view.php
 */

require_once '../includes/auth.php';
requireLogin();

$user_id = $_GET['id'] ?? '';
$current_user = getCurrentUser();

if (empty($user_id) || !is_numeric($user_id)) {
    $_SESSION['error_message'] = 'Invalid user ID.';
    header('Location: ./');
    exit();
}

// Get user details
$user = fetchOne($pdo, "
    SELECT u.*, d.name as department_name, c.name as company_name, 
           m.name as manager_name, m.email as manager_email
    FROM users u
    JOIN departments d ON u.department_id = d.id
    JOIN companies c ON u.company_id = c.id
    LEFT JOIN users m ON u.reporting_manager_id = m.id
    WHERE u.id = ?
", [$user_id]);

if (!$user) {
    $_SESSION['error_message'] = 'User not found.';
    header('Location: ./');
    exit();
}

// Check if current user can view this profile
$can_view = false;

if (hasRole(['Admin'])) {
    $can_view = true;
} elseif ($user_id == $current_user['id']) {
    $can_view = true; // Own profile
} elseif (hasRole(['Manager']) && $user['reporting_manager_id'] == $current_user['id']) {
    $can_view = true; // Manager viewing reporting employee
}

if (!$can_view) {
    $_SESSION['error_message'] = 'You do not have permission to view this user profile.';
    header('Location: ./');
    exit();
}

// Get user statistics
$user_stats = [];

// Total requests
$user_stats['total_requests'] = fetchOne($pdo, 
    "SELECT COUNT(*) as count FROM requests WHERE user_id = ?", 
    [$user_id]
)['count'];

// Requests by status
$user_stats['requests_by_status'] = fetchAll($pdo, "
    SELECT status, COUNT(*) as count 
    FROM requests 
    WHERE user_id = ? 
    GROUP BY status
", [$user_id]);

// Recent requests
$recent_requests = fetchAll($pdo, "
    SELECT r.id, r.title, r.status, r.created_at, c.name as category_name
    FROM requests r
    JOIN categories c ON r.category_id = c.id
    WHERE r.user_id = ?
    ORDER BY r.created_at DESC
    LIMIT 10
", [$user_id]);

// If current user is manager, get reporting employees
$reporting_employees = [];
if (hasRole(['Manager', 'Admin'])) {
    $reporting_employees = fetchAll($pdo, "
        SELECT u.id, u.name, u.email, d.name as department_name,
               COUNT(r.id) as request_count
        FROM users u
        JOIN departments d ON u.department_id = d.id
        LEFT JOIN requests r ON u.id = r.user_id
        WHERE u.reporting_manager_id = ?
        GROUP BY u.id
        ORDER BY u.name
    ", [$user_id]);
}

$page_title = $user['name'] . ' - User Profile';

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-person me-2"></i><?php echo htmlspecialchars($user['name']); ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="./" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Users
            </a>
            
            <?php if (hasRole(['Admin']) || ($current_user['role'] === 'Manager' && $user['reporting_manager_id'] == $current_user['id'])): ?>
                <a href="edit.php?id=<?php echo $user['id']; ?>" class="btn btn-primary">
                    <i class="bi bi-pencil me-1"></i>Edit Profile
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4">
        <!-- User Profile Card -->
        <div class="card mb-4">
            <div class="card-body text-center">
                <div class="avatar-lg bg-primary text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3">
                    <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                </div>
                
                <h4 class="mb-1"><?php echo htmlspecialchars($user['name']); ?></h4>
                <p class="text-muted mb-3"><?php echo htmlspecialchars($user['email']); ?></p>
                
                <?php
                $role_class = [
                    'Admin' => 'danger',
                    'IT Manager' => 'warning',
                    'Manager' => 'info',
                    'User' => 'secondary'
                ];
                $class = $role_class[$user['role']] ?? 'secondary';
                ?>
                <span class="badge bg-<?php echo $class; ?> fs-6 mb-3">
                    <?php echo htmlspecialchars($user['role']); ?>
                </span>
                
                <?php if ($user['is_active']): ?>
                    <span class="badge bg-success ms-2">Active</span>
                <?php else: ?>
                    <span class="badge bg-secondary ms-2">Inactive</span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Contact Information -->
        <div class="card mb-4">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Contact Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Email:</strong><br>
                    <a href="mailto:<?php echo htmlspecialchars($user['email']); ?>" class="text-decoration-none">
                        <?php echo htmlspecialchars($user['email']); ?>
                    </a>
                </div>
                
                <div class="mb-3">
                    <strong>Department:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($user['department_name']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Company:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($user['company_name']); ?></span>
                </div>
                
                <?php if ($user['manager_name']): ?>
                    <div class="mb-3">
                        <strong>Reporting Manager:</strong><br>
                        <span class="text-muted"><?php echo htmlspecialchars($user['manager_name']); ?></span><br>
                        <small>
                            <a href="mailto:<?php echo htmlspecialchars($user['manager_email']); ?>" class="text-decoration-none">
                                <?php echo htmlspecialchars($user['manager_email']); ?>
                            </a>
                        </small>
                    </div>
                <?php else: ?>
                    <div class="mb-3">
                        <strong>Reporting Manager:</strong><br>
                        <span class="text-muted">No manager assigned</span>
                    </div>
                <?php endif; ?>
                
                <div class="mb-0">
                    <strong>Member since:</strong><br>
                    <small class="text-muted"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Request Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $user_stats['total_requests']; ?></h4>
                                <p class="card-text">Total Requests</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-file-text" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <?php
            $approved_count = 0;
            $pending_count = 0;
            $rejected_count = 0;
            
            foreach ($user_stats['requests_by_status'] as $status_stat) {
                switch ($status_stat['status']) {
                    case 'Approved':
                        $approved_count = $status_stat['count'];
                        break;
                    case 'Rejected':
                        $rejected_count = $status_stat['count'];
                        break;
                    default:
                        $pending_count += $status_stat['count'];
                        break;
                }
            }
            ?>
            
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $approved_count; ?></h4>
                                <p class="card-text">Approved</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h4 class="card-title"><?php echo $pending_count; ?></h4>
                                <p class="card-text">Pending</p>
                            </div>
                            <div class="align-self-center">
                                <i class="bi bi-clock" style="font-size: 2rem;"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Requests -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">
                    <i class="bi bi-clock-history me-2"></i>Recent Requests
                </h5>
                <a href="../requests/?user_id=<?php echo $user['id']; ?>" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (empty($recent_requests)): ?>
                    <p class="text-muted text-center py-4">No requests found.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_requests as $request): ?>
                                    <tr>
                                        <td>
                                            <a href="../requests/view.php?id=<?php echo $request['id']; ?>" class="text-decoration-none">
                                                #<?php echo $request['id']; ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($request['title']); ?></td>
                                        <td>
                                            <span class="badge bg-secondary">
                                                <?php echo htmlspecialchars($request['category_name']); ?>
                                            </span>
                                        </td>
                                        <td>
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
                                            <span class="badge bg-<?php echo $class; ?> status-badge">
                                                <?php echo htmlspecialchars($request['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($request['created_at'])); ?>
                                            </small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Reporting Employees (if user is a manager) -->
        <?php if (!empty($reporting_employees)): ?>
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-people me-2"></i>Reporting Employees (<?php echo count($reporting_employees); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Requests</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reporting_employees as $employee): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-sm bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                    <?php echo strtoupper(substr($employee['name'], 0, 2)); ?>
                                                </div>
                                                <?php echo htmlspecialchars($employee['name']); ?>
                                            </div>
                                        </td>
                                        <td><?php echo htmlspecialchars($employee['email']); ?></td>
                                        <td><?php echo htmlspecialchars($employee['department_name']); ?></td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $employee['request_count']; ?></span>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?php echo $employee['id']; ?>" 
                                               class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.avatar-lg {
    width: 80px;
    height: 80px;
    font-size: 2rem;
    font-weight: 600;
}

.avatar-sm {
    width: 30px;
    height: 30px;
    font-size: 0.75rem;
    font-weight: 600;
}
</style>

<?php include '../includes/footer.php'; ?>