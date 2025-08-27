<?php
/**
 * Companies Management Page
 * companies/index.php
 */

require_once '../includes/auth.php';
requireRole(['Admin']);

$page_title = 'Companies Management';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Company name is required.');
            }
            
            // Check for duplicate company name
            $existing = fetchOne($pdo, "SELECT id FROM companies WHERE name = ?", [$name]);
            if ($existing) {
                throw new Exception('Company name already exists.');
            }
            
            executeQuery($pdo, "
                INSERT INTO companies (name, description, created_at)
                VALUES (?, ?, NOW())
            ", [$name, $description]);
            
            $_SESSION['success_message'] = 'Company created successfully!';
            
        } elseif ($action === 'update') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($id) || empty($name)) {
                throw new Exception('ID and name are required.');
            }
            
            // Check for duplicate company name (excluding current)
            $existing = fetchOne($pdo, "SELECT id FROM companies WHERE name = ? AND id != ?", [$name, $id]);
            if ($existing) {
                throw new Exception('Company name already exists.');
            }
            
            executeQuery($pdo, "
                UPDATE companies 
                SET name = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ", [$name, $description, $id]);
            
            $_SESSION['success_message'] = 'Company updated successfully!';
            
        } elseif ($action === 'delete') {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('Company ID is required.');
            }
            
            // Check if company has departments
            $dept_count = fetchOne($pdo, "SELECT COUNT(*) as count FROM departments WHERE company_id = ?", [$id])['count'];
            if ($dept_count > 0) {
                throw new Exception("Cannot delete company. It has $dept_count department(s).");
            }
            
            // Check if company has users
            $user_count = fetchOne($pdo, "SELECT COUNT(*) as count FROM users WHERE company_id = ?", [$id])['count'];
            if ($user_count > 0) {
                throw new Exception("Cannot delete company. It has $user_count user(s).");
            }
            
            executeQuery($pdo, "DELETE FROM companies WHERE id = ?", [$id]);
            
            $_SESSION['success_message'] = 'Company deleted successfully!';
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get companies with department and user counts
$companies = fetchAll($pdo, "
    SELECT c.*,
           COUNT(DISTINCT d.id) as department_count,
           COUNT(DISTINCT u.id) as user_count
    FROM companies c
    LEFT JOIN departments d ON c.id = d.company_id
    LEFT JOIN users u ON c.id = u.company_id
    GROUP BY c.id
    ORDER BY c.name
");

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-buildings me-2"></i>Companies Management
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#companyModal" onclick="openCreateModal()">
            <i class="bi bi-plus-lg me-1"></i>Add Company
        </button>
    </div>
</div>

<!-- Companies Table -->
<div class="card">
    <div class="card-header">
        <h5 class="card-title mb-0">
            Companies (<?php echo count($companies); ?> total)
        </h5>
    </div>
    <div class="card-body p-0">
        <?php if (empty($companies)): ?>
            <div class="text-center py-5">
                <i class="bi bi-buildings display-1 text-muted"></i>
                <h4 class="mt-3">No companies found</h4>
                <p class="text-muted">Create your first company to get started.</p>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#companyModal" onclick="openCreateModal()">
                    <i class="bi bi-plus-lg me-1"></i>Add Company
                </button>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Description</th>
                            <th>Departments</th>
                            <th>Users</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-primary text-white rounded d-flex align-items-center justify-content-center me-3">
                                            <i class="bi bi-building"></i>
                                        </div>
                                        <div>
                                            <div class="fw-medium"><?php echo htmlspecialchars($company['name']); ?></div>
                                            <small class="text-muted">ID: <?php echo $company['id']; ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if ($company['description']): ?>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($company['description'], 0, 100)); ?>
                                        <?php if (strlen($company['description']) > 100) echo '...'; ?></small>
                                    <?php else: ?>
                                        <small class="text-muted fst-italic">No description</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $company['department_count']; ?> departments</span>
                                </td>
                                <td>
                                    <span class="badge bg-success"><?php echo $company['user_count']; ?> users</span>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($company['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button type="button" class="btn btn-outline-primary" 
                                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($company)); ?>)" 
                                                title="Edit">
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        
                                        <?php if ($company['department_count'] == 0 && $company['user_count'] == 0): ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteCompany(<?php echo $company['id']; ?>, '<?php echo htmlspecialchars($company['name']); ?>')" 
                                                    title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary" disabled 
                                                    title="Cannot delete - has departments or users">
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

<!-- Company Modal -->
<div class="modal fade" id="companyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="companyForm">
                <input type="hidden" name="action" id="modalAction" value="create">
                <input type="hidden" name="id" id="companyId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="modalTitle">Add Company</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="companyName" class="form-label">Company Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="companyName" name="name" required 
                               placeholder="e.g., Facebook Inc.">
                    </div>
                    
                    <div class="mb-3">
                        <label for="companyDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="companyDescription" name="description" rows="4" 
                                  placeholder="Brief description of the company..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveButton">
                        <i class="bi bi-check-lg me-1"></i>Save Company
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
    document.getElementById('modalTitle').textContent = 'Add Company';
    document.getElementById('saveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Company';
    
    // Reset form
    document.getElementById('companyForm').reset();
    document.getElementById('companyId').value = '';
}

function openEditModal(company) {
    document.getElementById('modalAction').value = 'update';
    document.getElementById('modalTitle').textContent = 'Edit Company';
    document.getElementById('saveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Company';
    
    // Populate form
    document.getElementById('companyId').value = company.id;
    document.getElementById('companyName').value = company.name;
    document.getElementById('companyDescription').value = company.description || '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('companyModal')).show();
}

function deleteCompany(id, name) {
    if (confirm(`Are you sure you want to delete the company "${name}"?\n\nThis action cannot be undone.`)) {
        document.getElementById('deleteId').value = id;
        document.getElementById('deleteForm').submit();
    }
}

// Form validation
document.getElementById('companyForm').addEventListener('submit', function(e) {
    const name = document.getElementById('companyName').value.trim();
    
    if (!name) {
        e.preventDefault();
        alert('Please enter a company name.');
        document.getElementById('companyName').focus();
        return;
    }
});
</script>

<?php include '../includes/footer.php'; ?>