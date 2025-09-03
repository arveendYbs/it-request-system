<?php
/**
 * Reports Page
 * reports/index.php
 */

require_once '../includes/auth.php';
requireLogin();

$page_title = 'Reports & Analytics';
$current_user = getCurrentUser();

// Build query based on user role and filters
$where_conditions = [];
$params = [];

// Role-based filtering (same as requests)
if ($current_user['role'] === 'User') {
    $where_conditions[] = "r.user_id = ?";
    $params[] = $current_user['id'];
} elseif ($current_user['role'] === 'Manager') {
    // Manager sees own requests + subordinates requests
    $where_conditions[] = "(r.user_id = ? OR u.reporting_manager_id = ?)";
    $params[] = $current_user['id'];
    $params[] = $current_user['id'];
}
// Admin and IT Manager can see all requests (no WHERE clause)

// Filter handling
$filters = [
    'status' => $_GET['status'] ?? '',
    'category' => $_GET['category'] ?? '',
    'company' => $_GET['company'] ?? '',
    'department' => $_GET['department'] ?? '',
    'site' => $_GET['site'] ?? '',
    'date_from' => $_GET['date_from'] ?? '',
    'date_to' => $_GET['date_to'] ?? ''
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

if (!empty($filters['site'])) {
    $where_conditions[] = "r.site_id = ?";
    $params[] = $filters['site'];
}

if (!empty($filters['date_from'])) {
    $where_conditions[] = "DATE(r.created_at) >= ?";
    $params[] = $filters['date_from'];
}

if (!empty($filters['date_to'])) {
    $where_conditions[] = "DATE(r.created_at) <= ?";
    $params[] = $filters['date_to'];
}

// Build WHERE clause
$where_clause = '';
if (!empty($where_conditions)) {
    $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
}

// Get summary statistics
$summary_query = "
    SELECT 
        COUNT(*) as total_requests,
        SUM(CASE WHEN r.status = 'Approved' THEN 1 ELSE 0 END) as approved_requests,
        SUM(CASE WHEN r.status = 'Rejected' THEN 1 ELSE 0 END) as rejected_requests,
        SUM(CASE WHEN r.status NOT IN ('Approved', 'Rejected') THEN 1 ELSE 0 END) as pending_requests
    FROM requests r
    JOIN users u ON r.user_id = u.id
    $where_clause
";

$summary = fetchOne($pdo, $summary_query, $params);

// Get requests by status
$status_query = "
    SELECT r.status, COUNT(*) as count 
    FROM requests r
    JOIN users u ON r.user_id = u.id
    $where_clause
    GROUP BY r.status
    ORDER BY count DESC
";

$status_stats = fetchAll($pdo, $status_query, $params);

// Get requests by category
$category_query = "
    SELECT c.name, COUNT(r.id) as count 
    FROM categories c 
    LEFT JOIN requests r ON c.id = r.category_id
    " . ($where_clause ? "LEFT JOIN users u ON r.user_id = u.id $where_clause" : "") . "
    GROUP BY c.id, c.name 
    ORDER BY count DESC
";

$category_stats = fetchAll($pdo, $category_query, $params);

// Get filter options
$categories = fetchAll($pdo, "SELECT id, name FROM categories ORDER BY name");
$companies = fetchAll($pdo, "SELECT id, name FROM companies ORDER BY name");
$departments = fetchAll($pdo, "SELECT id, name FROM departments ORDER BY name");
$sites = fetchAll($pdo, "SELECT id, name FROM sites ORDER BY name");

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
        <i class="bi bi-file-earmark-spreadsheet me-2"></i>Reports & Analytics
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-success" onclick="exportData()">
                <i class="bi bi-file-excel me-1"></i>Export to Excel
            </button>
        </div>
    </div>
</div>

<!-- Filter Panel -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="card-title mb-0">
            <i class="bi bi-funnel me-2"></i>Report Filters
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3" id="filterForm">
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
                <label for="site" class="form-label">Site</label>
                <select class="form-select" id="site" name="site">
                    <option value="">All Sites</option>
                    <?php foreach ($sites as $site): ?>
                        <option value="<?php echo $site['id']; ?>" 
                                <?php echo ($filters['site'] == $site['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($site['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-2">
                <label for="date_from" class="form-label">From Date</label>
                <input type="date" class="form-control" id="date_from" name="date_from" 
                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
            </div>
            
            <div class="col-md-2">
                <label for="date_to" class="form-label">To Date</label>
                <input type="date" class="form-control" id="date_to" name="date_to" 
                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
            </div>
            
            <div class="col-12">
                <div class="d-flex gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-search me-1"></i>Apply Filters
                    </button>
                    
                    <?php if (!empty(array_filter($filters))): ?>
                        <a href="?" class="btn btn-outline-secondary">
                            <i class="bi bi-x-lg me-1"></i>Clear Filters
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Summary Statistics -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="card-title"><?php echo number_format($summary['total_requests']); ?></h4>
                        <p class="card-text">Total Requests</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-file-text" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="card-title"><?php echo number_format($summary['approved_requests']); ?></h4>
                        <p class="card-text">Approved</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="card-title"><?php echo number_format($summary['pending_requests']); ?></h4>
                        <p class="card-text">Pending</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-clock" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-danger text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="card-title"><?php echo number_format($summary['rejected_requests']); ?></h4>
                        <p class="card-text">Rejected</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-x-circle" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-pie-chart me-2"></i>Requests by Status
                </h5>
            </div>
            <div class="card-body">
                <canvas id="statusChart" width="400" height="300"></canvas>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-bar-chart me-2"></i>Requests by Category
                </h5>
            </div>
            <div class="card-body">
                <canvas id="categoryChart" width="400" height="300"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Data Tables -->
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Status Breakdown</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-end">Count</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($status_stats as $stat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['status']); ?></td>
                                    <td class="text-end"><?php echo number_format($stat['count']); ?></td>
                                    <td class="text-end">
                                        <?php 
                                        $percentage = $summary['total_requests'] > 0 ? ($stat['count'] / $summary['total_requests']) * 100 : 0;
                                        echo number_format($percentage, 1) . '%'; 
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Category Breakdown</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th class="text-end">Count</th>
                                <th class="text-end">%</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($category_stats as $stat): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($stat['name']); ?></td>
                                    <td class="text-end"><?php echo number_format($stat['count']); ?></td>
                                    <td class="text-end">
                                        <?php 
                                        $percentage = $summary['total_requests'] > 0 ? ($stat['count'] / $summary['total_requests']) * 100 : 0;
                                        echo number_format($percentage, 1) . '%'; 
                                        ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Status Chart
const statusCtx = document.getElementById('statusChart').getContext('2d');
const statusData = <?php echo json_encode($status_stats); ?>;
const statusLabels = statusData.map(item => item.status);
const statusCounts = statusData.map(item => parseInt(item.count));

new Chart(statusCtx, {
    type: 'doughnut',
    data: {
        labels: statusLabels,
        datasets: [{
            data: statusCounts,
            backgroundColor: [
                '#ffc107', // warning - pending
                '#17a2b8', // info - approved by manager
                '#fd7e14', // warning - pending IT
                '#28a745', // success - approved
                '#dc3545'  // danger - rejected
            ],
            borderWidth: 2,
            borderColor: '#fff'
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: {
                position: 'bottom'
            }
        }
    }
});

// Category Chart
const categoryCtx = document.getElementById('categoryChart').getContext('2d');
const categoryData = <?php echo json_encode($category_stats); ?>;
const categoryLabels = categoryData.map(item => item.name);
const categoryCounts = categoryData.map(item => parseInt(item.count));

new Chart(categoryCtx, {
    type: 'bar',
    data: {
        labels: categoryLabels,
        datasets: [{
            label: 'Requests',
            data: categoryCounts,
            backgroundColor: '#007bff',
            borderColor: '#0056b3',
            borderWidth: 1
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    stepSize: 1
                }
            }
        },
        plugins: {
            legend: {
                display: false
            }
        }
    }
});

// Export function
function exportData() {
    const form = document.getElementById('filterForm');
    const formData = new FormData(form);
    const params = new URLSearchParams(formData);
    window.location.href = 'export.php?' + params.toString();
}
</script>

<?php include '../includes/footer.php'; ?>