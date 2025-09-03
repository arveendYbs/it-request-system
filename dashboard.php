<?php
/**
 * Dashboard Page
 * dashboard.php
 */

require_once 'includes/auth.php';
requireLogin();

$page_title = 'Dashboard';
$current_user = getCurrentUser();

// Get dashboard statistics
$stats = [];

$stats_base_query = "";
$stats_params = [];

if ($current_user['role'] === 'User') {
    $stats_base_query = " WHERE r.user_id = ?";
    $stats_params = [$current_user['id']];
} elseif ($current_user['role'] === 'Manager') {
    $stats_base_query = " WHERE (r.user_id = ? OR u.reporting_manager_id = ?)";
    $stats_params = [$current_user['id'], $current_user['id']];
}

// Admin and IT Manager see all (no WHERE clause)

// Total requests
$total_query = "SELECT COUNT(*) as count FROM requests r";
if ($stats_base_query) {
    $total_query .= " JOIN users u ON r.user_id = u.id" . $stats_base_query;
}
$stats['total_requests'] = fetchOne($pdo, $total_query, $stats_params)['count'];

// Total requests
//$stats['total_requests'] = fetchOne($pdo, "SELECT COUNT(*) as count FROM requests")['count'];


// Requests by status
$status_query = "
    SELECT status, COUNT(*) as count 
    FROM requests r";
if ($stats_base_query) {
    $status_query .= " JOIN users u ON r.user_id = u.id" . $stats_base_query;
}
$status_query .= " GROUP BY status";
$status_stats = fetchAll($pdo, $status_query, $stats_params);

// Requests by category
$category_query = "
    SELECT c.name, COUNT(r.id) as count 
    FROM categories c 
    LEFT JOIN requests r ON c.id = r.category_id";
if ($stats_base_query) {
    $category_query .= " LEFT JOIN users u ON r.user_id = u.id" . $stats_base_query;
}
$category_query .= " GROUP BY c.id, c.name ORDER BY count DESC";
$category_stats = fetchAll($pdo, $category_query, $stats_params);
/*
// Requests by status
$status_stats = fetchAll($pdo, "
    SELECT status, COUNT(*) as count 
    FROM requests 
    GROUP BY status
");

// Requests by category
$category_stats = fetchAll($pdo, "
    SELECT c.name, COUNT(r.id) as count 
    FROM categories c 
    LEFT JOIN requests r ON c.id = r.category_id 
    GROUP BY c.id, c.name 
    ORDER BY count DESC
");
*/
// Recent requests (last 10)
/*
$recent_requests = fetchAll($pdo, "
    SELECT r.*, u.name as user_name, c.name as category_name, sc.name as subcategory_name
    FROM requests r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    JOIN subcategories sc ON r.subcategory_id = sc.id
    ORDER BY r.created_at DESC
    LIMIT 10
");
*/
$recent_requests_query = "
    SELECT r.*, u.name as user_name, c.name as category_name, sc.name as subcategory_name
    FROM requests r
    JOIN users u ON r.user_id = u.id
    JOIN categories c ON r.category_id = c.id
    JOIN subcategories sc ON r.subcategory_id = sc.id
    ";

if ($current_user['role'] === 'User'){
    $recent_requests_query .= " WHERE r.user_id = " . $current_user['id'];

} elseif ($current_user['role'] === 'Manager'){
    $recent_requests_query .= " WHERE (r.user_id = " . $current_user['id'] . " OR u.reporting_manager_id = " . $current_user['id'] . ")";

}

$recent_requests_query .= " ORDER BY r.created_at DESC LIMIT 10";
$recent_requests = fetchAll($pdo, $recent_requests_query);



// User-specific stats based on role
if ($current_user['role'] === 'User') {
    $my_requests = fetchAll($pdo, "
        SELECT status, COUNT(*) as count 
        FROM requests 
        WHERE user_id = ? 
        GROUP BY status
    ", [$current_user['id']]);
} elseif ($current_user['role'] === 'Manager') {
    $pending_approvals = fetchOne($pdo, "
        SELECT COUNT(*) as count 
        FROM requests r
        JOIN users u ON r.user_id = u.id
        WHERE u.reporting_manager_id = ? AND r.status = 'Pending HOD'
    ", [$current_user['id']])['count'];
} elseif ($current_user['role'] === 'IT Manager') {
    $pending_it_approvals = fetchOne($pdo, "
        SELECT COUNT(*) as count 
        FROM requests 
        WHERE status IN ('Approved by Manager', 'Pending IT HOD')
    ")['count'];
}


include 'includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-speedometer2 me-2"></i>Dashboard
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="requests/create.php" class="btn btn-primary">
                <i class="bi bi-plus-lg me-1"></i>New Request
            </a>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="row mb-4">
    <div class="col-md-3">
        <div class="card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex justify-content-between">
                    <div>
                        <h4 class="card-title"><?php echo $stats['total_requests']; ?></h4>
                        <p class="card-text">Total Requests</p>
                    </div>
                    <div class="align-self-center">
                        <i class="bi bi-file-text" style="font-size: 2rem;"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($current_user['role'] === 'User' && isset($my_requests)): ?>
        <?php 
        $my_total = array_sum(array_column($my_requests, 'count'));
        $my_pending = 0;
        foreach ($my_requests as $req) {
            if (in_array($req['status'], ['Pending HOD', 'Approved by Manager', 'Pending IT HOD'])) {
                $my_pending += $req['count'];
            }
        }
        ?>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo $my_total; ?></h4>
                            <p class="card-text">My Requests</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-person-check" style="font-size: 2rem;"></i>
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
                            <h4 class="card-title"><?php echo $my_pending; ?></h4>
                            <p class="card-text">Pending HOD</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($current_user['role'] === 'Manager' && isset($pending_approvals)): ?>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo $pending_approvals; ?></h4>
                            <p class="card-text">Pending Approvals</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-clock" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($current_user['role'] === 'IT Manager' && isset($pending_it_approvals)): ?>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4 class="card-title"><?php echo $pending_it_approvals; ?></h4>
                            <p class="card-text">Pending IT Approvals</p>
                        </div>
                        <div class="align-self-center">
                            <i class="bi bi-gear" style="font-size: 2rem;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
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
                <canvas id="statusChart" width="400" height="200"></canvas>
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
                <canvas id="categoryChart" width="400" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- Recent Requests -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="card-title mb-0">
            <i class="bi bi-clock-history me-2"></i>Recent Requests
        </h5>
                        <a href="requests/" class="btn btn-sm btn-outline-primary">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recent_requests)): ?>
            <p class="text-muted text-center py-4">No requests found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Request ID</th>
                            <th>Title</th>
                            <th>Requester</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Created</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_requests as $request): ?>
                            <tr>
                                <td>
                                    <a href="/requests/view.php?id=<?php echo $request['id']; ?>" class="text-decoration-none">
                                        #<?php echo $request['id']; ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($request['title']); ?></td>
                                <td><?php echo htmlspecialchars($request['user_name']); ?></td>
                                <td>
                                    <span class="badge bg-secondary">
                                        <?php echo htmlspecialchars($request['category_name']); ?>
                                    </span>
                                </td>
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
</script>

<?php include 'includes/footer.php'; ?>