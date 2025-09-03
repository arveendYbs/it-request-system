<?php
/**
 * Requests List Page
 * requests/index.php
 */

require_once '../includes/auth.php';
requireLogin();

$page_title = 'Requests';
$current_user = getCurrentUser();

// Build query based on user role and filters
$where_conditions = [];
$params = [];

// Role-based filtering
if ($current_user['role'] === 'User') {
    $where_conditions[] = "r.user_id = ?";
    $params[] = $current_user['id'];
} elseif ($current_user['role'] === 'Manager') {
    // Show requests from reporting employees + own requests
    $where_conditions[] = "(u.reporting_manager_id = ? OR r.user_id = ?)";
    $params[] = $current_user['id'];
    $params[] = $current_user['id'];
}

// Filter handling
$filters = [
    'status' => $_GET['status'] ?? '',
    'category' => $_GET['category'] ?? '',
    'company' => $_GET['company'] ?? '',
    'department' => $_GET['department'] ?? '',
    'search' => $_GET['search'] ?? ''
];

if (!empty($filters['status'])) {
    $where_conditions[] = "r.status = ?";
    $params[] = $filters['status'];
}

if (!empty($filters['category'])) {
    $where_conditions[] = "r.category_id = ?";
    $params[] = $filters['category'];
}

if (!empty($filters['company'])) {
    $where_conditions[] = "u.company_id = ?";
    $params[] = $filters['company'];
}

if (!empty($filters['department'])) {
    $where_conditions[] = "u.department_id = ?";
    $params[] = $filters['department'];
}

if (!empty($filters['search'])) {
    $where_conditions[] = "(r.title LIKE ? OR r.description LIKE ? OR u.name LIKE ?)";
    $search_term = '%' . $filters['search'] . '%';
    $params[] = $search_term;
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
    FROM requests r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    JOIN subcategories sc ON r.subcategory_id = sc.id
    JOIN companies co ON u.company_id = co.id
    JOIN departments d ON u.department_id = d.id
    $where_clause
";

$total_requests = fetchOne($pdo, $count_query, $params)['total'];
$total_pages = ceil($total_requests / $per_page);

// Get requests
$requests_query = "
    SELECT r.*, u.name as user_name, c.name as category_name, 
           sc.name as subcategory_name, co.name as company_name, 
           d.name as department_name,
           am.name as approved_by_manager_name,
           aim.name as approved_by_it_manager_name
    FROM requests r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    JOIN subcategories sc ON r.subcategory_id = sc.id
    JOIN companies co ON u.company_id = co.id
    JOIN departments d ON u.department_id = d.id
    LEFT JOIN users am ON r.approved_by_manager_id = am.id
    LEFT JOIN users aim ON r.approved_by_it_manager_id = aim.id
    $where_clause
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset
";

$requests = fetchAll($pdo, $requests_query, $params);

// Get filter options
$categories = fetchAll($pdo, "SELECT id, name FROM categories ORDER BY name");
$companies = fetchAll($pdo, "SELECT id, name FROM companies ORDER BY name");
$departments = fetchAll($pdo, "SELECT id, name FROM departments ORDER BY name");

$status_options = [
    'Pending HOD',
    'Approved by Manager', 
    'Pending IT HOD',
    'Approved',
    'Rejected'
];

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-file-text me-2"></i>Requests
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="create.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Request
            </a>
            <a href="/reports/export.php?<?php echo http_build_query($filters); ?>" class="btn btn-success">
                <i class="bi bi-file-excel me-1"></i>Export
            </a>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-2">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="">All Status</option>
                    <?php foreach ($status_options as $status): ?>
                        <option value="<?php echo htmlspecialchars($status); ?>" 
                                <?php echo ($filters['status'] === $status) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($status); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label for="category" class="form-label">Category</label>
                <select class="form-select" id="category" name="category">
                    <option value="">All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>" 
                                <?php echo ($filters['category'] == $category['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if (hasRole(['Admin', 'IT Manager'])): ?>
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
            <?php endif; ?>
            
            <div class="col-md-3">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       placeholder="Search title, description, or user..." 
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

<!-- Requests Table -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            Requests (<?php echo number_format($total_requests); ?> total)
        </h5>
        
        <?php if (!empty(array_filter($filters))): ?>
            <a href="?" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Clear Filters
            </a>
        <?php endif; ?>
    </div>
    
    <div class="card-body p-0">
        <?php if (empty($requests)): ?>
            <div class="text-center py-5">
                <i class="bi bi-inbox display-1 text-muted"></i>
                <h4 class="mt-3">No requests found</h4>
                <p class="text-muted">Try adjusting your filters or create a new request.</p>
                <a href="create.php" class="btn btn-primary">
                    <i class="bi bi-plus-lg me-1"></i>Create Request
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Requester</th>
                            <th>Category</th>
                            <?php if (hasRole(['Admin', 'IT Manager'])): ?>
                                <th>Company</th>
                            <?php endif; ?>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $request): ?>
                            <tr>
                                <td>
                                    <a href="view.php?id=<?php echo $request['id']; ?>" class="text-decoration-none fw-bold">
                                        #<?php echo $request['id']; ?>
                                    </a>
                                </td>
                                <td>
                                    <div class="fw-medium"><?php echo htmlspecialchars($request['title']); ?></div>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars(substr($request['description'], 0, 100)); ?>
                                        <?php if (strlen($request['description']) > 100) echo '...'; ?>
                                    </small>
                                </td>
                                <td>
                                    <div><?php echo htmlspecialchars($request['user_name']); ?></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($request['department_name']); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($request['category_name']); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted"><?php echo htmlspecialchars($request['subcategory_name']); ?></small>
                                </td>
                                <?php if (hasRole(['Admin', 'IT Manager'])): ?>
                                    <td>
                                        <small><?php echo htmlspecialchars($request['company_name']); ?></small>
                                    </td>
                                <?php endif; ?>
                                <td>
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
                                    <span class="badge bg-<?php echo $class; ?> status-badge">
                                        <?php echo htmlspecialchars($request['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div><?php echo date('M j, Y', strtotime($request['created_at'])); ?></div>
                                    <small class="text-muted"><?php echo date('g:i A', strtotime($request['created_at'])); ?></small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view.php?id=<?php echo $request['id']; ?>" 
                                           class="btn btn-outline-primary" title="View">
                                            <i class="bi bi-eye"></i>
                                        </a>
                                        
                                        <?php if ($auth->canEditRequest($request['id'])): ?>
                                            <a href="edit.php?id=<?php echo $request['id']; ?>" 
                                               class="btn btn-outline-secondary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($auth->canApproveRequest($request['id'])): ?>
                                            <a href="approve.php?id=<?php echo $request['id']; ?>" 
                                               class="btn btn-outline-success" title="Approve/Reject">
                                                <i class="bi bi-check-lg"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if (hasRole(['Admin']) || ($request['user_id'] == $current_user['id'] && $request['status'] === 'Pending Manager')): ?>
                                            <a href="delete.php?id=<?php echo $request['id']; ?>" 
                                               class="btn btn-outline-danger" title="Delete"
                                               onclick="return confirmDelete('Are you sure you want to delete this request?')">
                                                <i class="bi bi-trash"></i>
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
            <nav aria-label="Requests pagination">
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

<?php include '../includes/footer.php'; ?>