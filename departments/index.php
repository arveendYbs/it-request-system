<?php
/**
 * Departments Management Page
 * departments/index.php
 */

require_once '../includes/auth.php';
requireRole(['Admin']);

$page_title = 'Departments Management';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $company_id = $_POST['company_id'] ?? '';
            
            if (empty($name) || empty($company_id)) {
                throw new Exception('Name and company are required.');
            }
            
            // Check for duplicate department name in same company
            $existing = fetchOne($pdo, "SELECT id FROM departments WHERE name = ? AND company_id = ?", [$name, $company_id]);
            if ($existing) {
                throw new Exception('Department name already exists in this company.');
            }
            
            executeQuery($pdo, "
                INSERT INTO departments (name, description, company_id, created_at)
                VALUES (?, ?, ?, NOW())
            ", [$name, $description, $company_id]);
            
            $_SESSION['success_message'] = 'Department created successfully!';
            
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $company_id = $_POST['company_id'] ?? '';
            
            if (empty($id) || empty($name) || empty($company_id)) {
                throw new Exception('ID, name, and company are required.');
            }
            
            // Check for duplicate department name in same company (excluding current)
            $existing = fetchOne($pdo, "SELECT id FROM departments WHERE name = ? AND company_id = ? AND id != ?", [$name, $company_id, $id]);
            if ($existing) {
                throw new Exception('Department name already exists in this company.');
            }
            
            executeQuery($pdo, "
                UPDATE departments 
                SET name = ?, description = ?, company_id = ?, updated_at = NOW()
                WHERE id = ?
            ", [$name, $description, $company_id, $id]);
            
            $_SESSION['success_message'] = 'Department updated successfully!';
            
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('Department ID is required.');
            }
            
            // Check if department has users
            $user_count = fetchOne($pdo, "SELECT COUNT(*) as count FROM users WHERE department_id = ?", [$id])['count'];
            if ($user_count > 0) {
                throw new Exception("Cannot delete department. It has $user_count user(s) assigned.");
            }
            
            executeQuery($pdo, "DELETE FROM departments WHERE id = ?", [$id]);
            
            $_SESSION['success_message'] = 'Department deleted successfully!';
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get departments with company info and user count
$departments = fetchAll($pdo, "
    SELECT d.*, c.name as company_name,
           COUNT(u.id) as user_count
    FROM departments d
    JOIN companies c ON d.company_id = c.id
    LEFT JOIN users u ON d.id = u.department_id
    GROUP BY d.id
    ORDER BY c.name, d.name
");

// Get companies for dropdowns
$companies = fetchAll($pdo, "SELECT id, name FROM companies ORDER BY name");

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-building me-2"></i>Departments Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal" onclick="openCreateModal()">
            <i class="bi bi-plus-lg me-1"></i>Add Department
        </button>
    </div>
</div>

<!-- Departments Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            Departments (<?php echo count($departments); ?> total)
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($departments)): ?>
            <div class="text-center py-5">
                <i class="bi bi-building display-1 text-muted"></i>
                <h4 class="mt-3">No departments found</h4>
                <p class="text-muted">Create your first department to get started.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#departmentModal" onclick="openCreateModal()">
                    <i class="bi bi-plus-lg me-1"></i>Add Department
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Department Name</th>
                            <th>Company</th>
                            <th>Description</th>
                            <th>Users</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($departments as $department): ?>
                            <tr>
                                <td>
                                    <div class="fw-medium"><?php echo htmlspecialchars($department['name']); ?></div>
                                    <small class="text-muted">ID: <?php echo $department['id']; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo htmlspecialchars($department['company_name']); ?></span>
                                </td>
                                <td>
                                    <?php if ($department['description']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars($department['description']); ?></small>
                                    <?php else: ?>
                                        <small class="text-muted fst-italic">No description</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $department['user_count']; ?> users</span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($department['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($department)); ?>)" 
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <?php if ($department['user_count'] == 0): ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteDepartment(<?php echo $department['id']; ?>, '<?php echo htmlspecialchars($department['name']); ?>')" 
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary" disabled 
                                                    title="Cannot delete - has users assigned">
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

<!-- Department Modal -->
<div class="modal fade" id="departmentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="departmentForm">
                <input type="hidden" name="action" id="modalAction" value="create">
                <input type="hidden" name="id" id="departmentId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Department</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="departmentName" class="form-label">Department Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="departmentName" name="name" required 
                               placeholder="e.g., Information Technology">
                    </div>
                    
                    <div class="mb-3">
                        <label for="companySelect" class="form-label">Company <span class="text-danger">*</span></label>
                        <select class="form-select" id="companySelect" name="company_id" required>
                            <option value="">Select Company</option>
                            <?php foreach ($companies as $company): ?>
                                <option value="<?php echo $company['id']; ?>">
                                    <?php echo htmlspecialchars($company['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="departmentDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="departmentDescription" name="description" rows="3" 
                                  placeholder="Brief description of the department..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveButton">
                        <i class="bi bi-check-lg me-1"></i>Save Department
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

<script>
function openCreateModal() {
    document.getElementById('modalAction').value = 'create';
    document.getElementById('modalTitle').textContent = 'Add Department';
    document.getElementById('saveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Department';
    
    // Reset form
    document.getElementById('departmentForm').reset();
    document.getElementById('departmentId').value = '';
}

function openEditModal(department) {
    document.getElementById('modalAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Edit Department';
    document.getElementById('saveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Department';
    
    // Populate form
    document.getElementById('departmentId').value = department.id;
    document.getElementById('departmentName').value = department.name;
    document.getElementById('companySelect').value = department.company_id;
    document.getElementById('departmentDescription').value = department.description || '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('departmentModal')).show();
}

function deleteDepartment(id, name) {
    if (confirm(`Are you sure you want to delete the department "${name}"?\n\nThis action cannot be undone.`)) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Form validation
document.getElementById('departmentForm').addEventListener('submit', function(e) {
    const name = document.getElementById('departmentName').value.trim();
    const company = document.getElementById('companySelect').value;
    
    if (!name) {
        e.preventDefault();
        alert('Please enter a department name.');
        document.getElementById('departmentName').focus();
        return;
    }
    
    if (!company) {
        e.preventDefault();
        alert('Please select a company.');
        document.getElementById('companySelect').focus();
        return;
    }
});
</script>

<?php include '../includes/footer.php'; ?>