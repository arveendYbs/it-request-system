<?php
/**
 * Sites Management Page
 * sites/index.php
 */

require_once '../includes/auth.php';
require_once '../config/db.php';

requireRole(['Admin']);


$page_title = 'Sites Management';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Site name is required.');
            }
            
            // Check for duplicate site name
            $existing = fetchOne($pdo, "SELECT id FROM sites WHERE name = ?", [$name]);
            if ($existing) {
                throw new Exception('Site name already exists.');
            }
            
            executeQuery($pdo, "
                INSERT INTO sites (name, description, address, created_at)
                VALUES (?, ?, ?, NOW())
            ", [$name, $description, $address]);
            
            $_SESSION['success_message'] = 'Site created successfully!';
            
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $address = trim($_POST['address'] ?? '');
            
            if (empty($id) || empty($name)) {
                throw new Exception('ID and name are required.');
            }
            
            // Check for duplicate site name (excluding current)
            $existing = fetchOne($pdo, "SELECT id FROM sites WHERE name = ? AND id != ?", [$name, $id]);
            if ($existing) {
                throw new Exception('Site name already exists.');
            }
            
            executeQuery($pdo, "
                UPDATE sites 
                SET name = ?, description = ?, address = ?, updated_at = NOW()
                WHERE id = ?
            ", [$name, $description, $address, $id]);
            
            $_SESSION['success_message'] = 'Site updated successfully!';
            
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('Site ID is required.');
            }
            
            // Check if site has users
            $user_count = fetchOne($pdo, "SELECT COUNT(*) as count FROM users WHERE site_id = ?", [$id])['count'];
            if ($user_count > 0) {
                throw new Exception("Cannot delete site. It has $user_count user(s) assigned.");
            }
            
            // Check if site has requests
            $request_count = fetchOne($pdo, "SELECT COUNT(*) as count FROM requests WHERE site_id = ?", [$id])['count'];
            if ($request_count > 0) {
                throw new Exception("Cannot delete site. It has $request_count request(s).");
            }
            
            executeQuery($pdo, "DELETE FROM sites WHERE id = ?", [$id]);
            
            $_SESSION['success_message'] = 'Site deleted successfully!';
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}
//var_dump($pdo); 
//$test = fetchAll($pdo, "SELECT * FROM sites");
//var_dump($test);
//exit;

// Get sites with user and request counts
/* $sites = fetchAll($pdo, "
    SELECT s.id, s.name, s.description, s.address, s.created_at, s.updated_at
    FROM sites s
    ORDER BY s.name
"); */



// Get sites with user and request counts
$sites = fetchAll($pdo, "
    SELECT s.*,
           COUNT(DISTINCT u.id) as user_count,
           COUNT(DISTINCT r.id) as request_count
    FROM sites s
    LEFT JOIN users u ON s.id = u.site_id
    LEFT JOIN requests r ON s.id = r.site_id
    GROUP BY s.id
    ORDER BY s.name
");

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-geo-alt me-2"></i>Sites Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#siteModal" onclick="openCreateModal()">
            <i class="bi bi-plus-lg me-1"></i>Add Site
        </button>
    </div>
</div>

<!-- Sites Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            Sites (<?php echo count($sites); ?> total)
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($sites)): ?>
            <div class="text-center py-5">
                <i class="bi bi-geo-alt display-1 text-muted"></i>
                <h4 class="mt-3">No sites found</h4>
                <p class="text-muted">Create your first site to get started.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#siteModal" onclick="openCreateModal()">
                    <i class="bi bi-plus-lg me-1"></i>Add Site
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Site Name</th>
                            <th>Description</th>
                            <th>Address</th>
                            <th>Users</th>
                            <th>Requests</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sites as $site): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-success text-white rounded d-flex align-items-center justify-content-center me-3">
                                            <i class="bi bi-geo-alt"></i>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($site['name']); ?></div>
                                            <small class="text-muted">ID: <?php echo $site['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($site['description']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($site['description'], 0, 100)); ?>
                                        <?php if (strlen($site['description']) > 100) echo '...'; ?></small>
                                    <?php else: ?>
                                        <small class="text-muted fst-italic">No description</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($site['address']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($site['address'], 0, 80)); ?>
                                        <?php if (strlen($site['address']) > 80) echo '...'; ?></small>
                                    <?php else: ?>
                                        <small class="text-muted fst-italic">No address</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $site['user_count']; ?> users</span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $site['request_count']; ?> requests</span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($site['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($site)); ?>)" 
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <?php if ($site['user_count'] == 0 && $site['request_count'] == 0): ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteSite(<?php echo $site['id']; ?>, '<?php echo htmlspecialchars($site['name']); ?>')" 
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary" disabled 
                                                    title="Cannot delete - has users or requests">
                                                <i class="bi bi-trash"></i>
                                            </button>
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
</div>

<!-- Site Modal -->
<div class="modal fade" id="siteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="siteForm">
                <input type="hidden" name="action" id="modalAction" value="create">
                <input type="hidden" name="id" id="siteId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Site</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="siteName" class="form-label">Site Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="siteName" name="name" required 
                               placeholder="e.g., Headquarters, Office 1">
                    </div>
                    
                    <div class="mb-3">
                        <label for="siteDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="siteDescription" name="description" rows="3" 
                                  placeholder="Brief description of the site..."></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="siteAddress" class="form-label">Address</label>
                        <textarea class="form-control" id="siteAddress" name="address" rows="3" 
                                  placeholder="Full address of the site..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveButton">
                        <i class="bi bi-check-lg me-1"></i>Save Site
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Form (hidden) -->
<form method="POST" id="deleteForm" style="display: none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteId">
</form>

<style>
.avatar-sm {
    width: 40px;
    height: 40px;
    font-size: 1rem;
}
</style>

<script>
function openCreateModal() {
    document.getElementById('modalAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Add Site';
    document.getElementById('saveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Site';
    
    // Reset form
    document.getElementById('siteForm').reset();
    document.getElementById('siteId').value = '';
}

function openEditModal(site) {
    document.getElementById('modalAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Edit Site';
    document.getElementById('saveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Site';
    
    // Populate form
    document.getElementById('siteId').value = site.id;
    document.getElementById('siteName').value = site.name;
    document.getElementById('siteDescription').value = site.description || '';
    document.getElementById('siteAddress').value = site.address || '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('siteModal')).show();
}

function deleteSite(id, name) {
    if (confirm(`Are you sure you want to delete the site "${name}"?\n\nThis action cannot be undone.`)) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Form validation
document.getElementById('siteForm').addEventListener('submit', function(e) {
    const name = document.getElementById('siteName').value.trim();
    
    if (!name) {
        e.preventDefault();
        alert('Please enter a site name.');
        document.getElementById('siteName').focus();
        return;
    }
});
</script>

<?php include '../includes/footer.php'; ?>