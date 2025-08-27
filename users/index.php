<?php
/**
 * Users Management Page
 * users/index.php
 */

require_once '../includes/auth.php';
requireRole(['Admin', 'Manager']);

$page_title = 'Users Management';
$current_user = getCurrentUser();

// Build query based on user role
$where_conditions = [];
$params = [];

// Manager can only see their reporting employees + themselves
if ($current_user['role'] === 'Manager') {
    $where_conditions[] = "(u.reporting_manager_id = ? OR u.id = ?)";
    $params[] = $current_user['id'];
    $params[] = $current_user['id'];
}

// Filter handling
$filters = [
    'role' => $_GET['role'] ?? '',
    'department' => $_GET['department'] ?? '',
    'company' => $_GET['company'] ?? '',
    'status' => $_GET['status'] ?? '',
    'search' => $_GET['search'] ?? ''
];

if (!empty($filters['role'])) {
    $where_conditions[] = "u.role = ?";
    $params[] = $filters['role'];
}

if (!empty($filters['department'])) {
    $where_conditions[] = "u.department_id = ?";
    $params[] = $filters['department'];
}

if (!empty($filters['company'])) {
    $where_conditions[] = "u.company_id = ?";
    $params[] = $filters['company'];
}

if (!empty($filters['status'])) {
    $is_active = $filters['status'] === 'active' ? 1 : 0;
    $where_conditions[] = "u.is_active = ?";
    $params[] = $is_active;
}

if (!empty($filters['search'])) {
    $where_conditions[] = "(u.name LIKE ? OR u.email LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
    $params[] = $search_term;
}

// Pagination
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get total count
$count_query = "
    SELECT COUNT(*) as total
    FROM users u
    JOIN departments d ON u.department_id = d.id
    JOIN companies c ON u.company_id = c.id
    $where_clause
";

$total_users = fetchOne($pdo, $count_query, $params)['total'];
$total_pages = ceil($total_users / $per_page);

// Get users
$users_query = "
    SELECT u.*, d.name as department_name, c.name as company_name, 
           m.name as manager_name
    FROM users u
    JOIN departments d ON u.department_id = d.id
    JOIN companies c ON u.company_id = c.id
    LEFT JOIN users m ON u.reporting_manager_id = m.id
    $where_clause
    ORDER BY u.name ASC
    LIMIT $per_page OFFSET $offset
";

$users = fetchAll($pdo, $users_query, $params);

// Get filter options
$departments = fetchAll($pdo, "SELECT id, name FROM departments ORDER BY name");
$companies = fetchAll($pdo, "SELECT id, name FROM companies ORDER BY name");

$role_options = ['Admin', 'Manager', 'IT Manager', 'User'];

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-people me-2"></i>Users Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <?php if (hasRole(['Admin'])): ?>
            <div class="btn-group me-2">
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Add User
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="role" class="form-label">Role</label>
                <select class="form-select" id="role" name="role">
                    <option value="">All Roles</option>
                    <?php foreach ($role_options as $role): ?>
                        <option value="<?php echo htmlspecialchars($role); ?>" 
                                <?php echo ($filters['role'] === $role) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($role); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="department" class="form-label">Department</label>
                <select class="form-select" id="department" name="department">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $department): ?>
                        <option value="<?php echo $department['id']; ?>" 
                                <?php echo ($filters['department'] == $department['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($department['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="company" class="form-label">Company</label>
                <select class="form-select" id="company" name="company">
                    <option value="">All Companies</option>
                    <?php foreach ($companies as $company): ?>
                        <option value="<?php echo $company['id']; ?>" 
                                <?php echo ($filters['company'] == $company['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($company['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <option value="active" <?php echo ($filters['status'] === 'active') ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($filters['status'] === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search name or email..." 
                       value="<?php echo htmlspecialchars($filters['search']); ?>">
            </div>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search"></i>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            Users (<?php echo number_format($total_users); ?> total)
        </h5>
        
        <?php if (!empty(array_filter($filters))): ?>
            <a href="?" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Clear Filters
            </a>
        <?php endif; ?>
    </div>
    
    <div class="card-body p-0">
        <?php if (empty($users)): ?>
            <div class="text-center py-5">
                <i class="bi bi-people display-1 text-muted"></i>
                <h4 class="mt-3">No users found</h4>
                <p class="text-muted">Try adjusting your filters or add a new user.</p>
                <?php if (hasRole(['Admin'])): ?>
                    <a href="create.php" class="btn btn-primary">
                        <i class="bi bi-plus-lg me-1"></i>Add User
                    </a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Department</th>
                            <th>Company</th>
                            <th>Manager</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                            <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($user['name']); ?></div>
                                            <small class="text-muted">ID: <?php echo $user['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php
                                    $role_class = [
                                        'Admin' => 'danger',
                                        'IT Manager' => 'warning',
                                        'Manager' => 'info',
                                        'User' => 'secondary'
                                    ];
                                    $class = $role_class[$user['role']] ?? 'secondary';
                                    ?>
                                    <span class="badge bg-<?php echo $class; ?>">
                                        <?php echo htmlspecialchars($user['role']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($user['department_name']); ?></div>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo htmlspecialchars($user['company_name']); ?></small>
                                </td>
                                <td>
                                    <?php if ($user['manager_name']): ?>
                                        <small><?php echo htmlspecialchars($user['manager_name']); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted">No manager</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($user['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $user['id']; ?>" 
                                           class="btn btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        
                                        <?php if (hasRole(['Admin']) || ($current_user['role'] === 'Manager' && $user['reporting_manager_id'] == $current_user['id'])): ?>
                                            <a href="edit.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasRole(['Admin']) && $user['id'] != $current_user['id']): ?>
                                            <a href="toggle_status.php?id=<?php echo $user['id']; ?>" 
                                               class="btn btn-outline-<?php echo $user['is_active'] ? 'warning' : 'success'; ?>" 
                                               title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>"
                                               onclick="return confirm('Are you sure you want to <?php echo $user['is_active'] ? 'deactivate' : 'activate'; ?> this user?')">
                                                <i class="bi bi-<?php echo $user['is_active'] ? 'pause' : 'play'; ?>"></i>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if ($total_pages > 1): ?>
        <div class="card-footer">
            <nav aria-label="Users pagination">
                <ul class="pagination justify-content-center mb-0">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <?php
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for ($i = $start_page; $i <= $end_page; $i++):
                    ?>
                        <li class="page-item <?php echo ($i == $page) ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        </div>
    <?php endif; ?>
</div>

<style>
.avatar-sm {
    width: 35px;
    height: 35px;
    font-size: 0.875rem;
    font-weight: 600;
}
</style>

<?php include '../includes/footer.php'; ?>