<?php
/**
 * Edit User Page
 * users/edit.php
 */

require_once '../includes/auth.php';
requireRole(['Admin', 'Manager']);

$user_id = $_GET['id'] ?? '';
$current_user = getCurrentUser();

if (empty($user_id) || !is_numeric($user_id)) {
    $_SESSION['error_message'] = 'Invalid user ID.';
    header('Location: ./');
    exit();
}

// Get user details
$user = fetchOne($pdo, "
    SELECT u.*, d.name as department_name, c.name as company_name
    FROM users u
    JOIN departments d ON u.department_id = d.id
    JOIN companies c ON u.company_id = c.id
    WHERE u.id = ?
", [$user_id]);

if (!$user) {
    $_SESSION['error_message'] = 'User not found.';
    header('Location: ./');
    exit();
}

// Check permissions
$can_edit = false;
if (hasRole(['Admin'])) {
    $can_edit = true;
} elseif ($current_user['role'] === 'Manager' && $user['reporting_manager_id'] == $current_user['id']) {
    $can_edit = true; // Manager can edit reporting employees
}

if (!$can_edit) {
    $_SESSION['error_message'] = 'You do not have permission to edit this user.';
    header('Location: view.php?id=' . $user_id);
    exit();
}

$errors = [];
$form_data = [
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'department_id' => $user['department_id'],
    'company_id' => $user['company_id'],
    'reporting_manager_id' => $user['reporting_manager_id'],
    'is_active' => $user['is_active']
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'name' => trim($_POST['name'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'role' => $_POST['role'] ?? '',
        'department_id' => $_POST['department_id'] ?? '',
        'company_id' => $_POST['company_id'] ?? '',
        'reporting_manager_id' => $_POST['reporting_manager_id'] ?? null,
        'is_active' => isset($_POST['is_active']) ? 1 : 0,
        'password' => $_POST['password'] ?? ''
    ];
    
    // Validation
    if (empty($form_data['name'])) {
        $errors[] = 'Name is required.';
    }
    
    if (empty($form_data['email'])) {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($form_data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($form_data['role'])) {
        $errors[] = 'Role is required.';
    }
    
    if (empty($form_data['department_id'])) {
        $errors[] = 'Department is required.';
    }
    
    if (empty($form_data['company_id'])) {
        $errors[] = 'Company is required.';
    }
    
    // Check if email is already used by another user
    if ($form_data['email'] !== $user['email']) {
        $existing_user = fetchOne($pdo, "SELECT id FROM users WHERE email = ? AND id != ?", [$form_data['email'], $user_id]);
        if ($existing_user) {
            $errors[] = 'Email address is already in use by another user.';
        }
    }
    
    // Managers can only edit limited fields for their employees
    if ($current_user['role'] === 'Manager' && !hasRole(['Admin'])) {
        // Manager can only edit basic info, not role or company
        $form_data['role'] = $user['role'];
        $form_data['company_id'] = $user['company_id'];
        $form_data['is_active'] = $user['is_active'];
    }
    
    // Validate manager assignment
    if (!empty($form_data['reporting_manager_id'])) {
        $manager = fetchOne($pdo, "SELECT role FROM users WHERE id = ?", [$form_data['reporting_manager_id']]);
        if (!$manager || !in_array($manager['role'], ['Manager', 'IT Manager', 'Admin'])) {
            $errors[] = 'Selected reporting manager must have Manager, IT Manager, or Admin role.';
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Prepare update query
            $update_fields = [
                'name = ?',
                'email = ?',
                'role = ?',
                'department_id = ?',
                'company_id = ?',
                'reporting_manager_id = ?',
                'is_active = ?',
                'updated_at = NOW()'
            ];
            
            $update_params = [
                $form_data['name'],
                $form_data['email'],
                $form_data['role'],
                $form_data['department_id'],
                $form_data['company_id'],
                $form_data['reporting_manager_id'] ?: null,
                $form_data['is_active']
            ];
            
            // Add password update if provided
            if (!empty($form_data['password'])) {
                $update_fields[] = 'password = ?';
                $update_params[] = password_hash($form_data['password'], PASSWORD_DEFAULT);
            }
            
            $update_params[] = $user_id; // for WHERE clause
            
            // Execute update
            executeQuery($pdo, "
                UPDATE users 
                SET " . implode(', ', $update_fields) . "
                WHERE id = ?
            ", $update_params);
            
            $pdo->commit();
            $_SESSION['success_message'] = 'User updated successfully!';
            header('Location: view.php?id=' . $user_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = 'Failed to update user: ' . $e->getMessage();
        }
    }
}

// Get dropdown options
$companies = fetchAll($pdo, "SELECT id, name FROM companies ORDER BY name");
$departments = fetchAll($pdo, "SELECT id, name, company_id FROM departments ORDER BY name");

// Get potential managers (exclude current user and their subordinates)
$managers = fetchAll($pdo, "
    SELECT id, name, email, role 
    FROM users 
    WHERE role IN ('Manager', 'IT Manager', 'Admin') 
    AND id != ? 
    AND is_active = 1
    ORDER BY name
", [$user_id]);

$role_options = ['User', 'Manager', 'IT Manager', 'Admin'];

// Managers can't assign certain roles
if ($current_user['role'] === 'Manager' && !hasRole(['Admin'])) {
    $role_options = ['User', 'Manager'];
}

$page_title = 'Edit User - ' . $user['name'];

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-pencil me-2"></i>Edit User
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Profile
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="bi bi-exclamation-triangle me-2"></i>Please fix the following errors:</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">User Information</h5>
            </div>
            <div class="card-body">
                <form method="POST" id="userForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($form_data['name']); ?>" required>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required 
                                        <?php echo ($current_user['role'] === 'Manager' && !hasRole(['Admin'])) ? 'disabled' : ''; ?>>
                                    <option value="">Select Role</option>
                                    <?php foreach ($role_options as $role): ?>
                                        <option value="<?php echo htmlspecialchars($role); ?>" 
                                                <?php echo ($form_data['role'] === $role) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($current_user['role'] === 'Manager' && !hasRole(['Admin'])): ?>
                                    <input type="hidden" name="role" value="<?php echo htmlspecialchars($form_data['role']); ?>">
                                    <div class="form-text">You can only edit basic information for your team members.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                                <select class="form-select" id="company_id" name="company_id" required 
                                        onchange="loadDepartments()"
                                        <?php echo ($current_user['role'] === 'Manager' && !hasRole(['Admin'])) ? 'disabled' : ''; ?>>
                                    <option value="">Select Company</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>" 
                                                <?php echo ($form_data['company_id'] == $company['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if ($current_user['role'] === 'Manager' && !hasRole(['Admin'])): ?>
                                    <input type="hidden" name="company_id" value="<?php echo $form_data['company_id']; ?>">
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="department_id" class="form-label">Department <span class="text-danger">*</span></label>
                                <select class="form-select" id="department_id" name="department_id" required>
                                    <option value="">Select Department</option>
                                    <?php foreach ($departments as $department): ?>
                                        <option value="<?php echo $department['id']; ?>" 
                                                data-company="<?php echo $department['company_id']; ?>"
                                                <?php echo ($form_data['department_id'] == $department['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($department['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="reporting_manager_id" class="form-label">Reporting Manager</label>
                                <select class="form-select" id="reporting_manager_id" name="reporting_manager_id">
                                    <option value="">No Manager</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?php echo $manager['id']; ?>" 
                                                <?php echo ($form_data['reporting_manager_id'] == $manager['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($manager['name']); ?> (<?php echo $manager['role']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="password" name="password" 
                               placeholder="Leave blank to keep current password">
                        <div class="form-text">Only enter a password if you want to change it.</div>
                    </div>
                    
                    <?php if (hasRole(['Admin'])): ?>
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" 
                                       <?php echo $form_data['is_active'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is_active">
                                    Active User
                                </label>
                            </div>
                            <div class="form-text">Inactive users cannot log in to the system.</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $user_id; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Update User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-info-circle me-2"></i>Current Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Current Role:</strong><br>
                    <?php
                    $role_class = [
                        'Admin' => 'danger',
                        'IT Manager' => 'warning',
                        'Manager' => 'info',
                        'User' => 'secondary'
                    ];
                    $class = $role_class[$user['role']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?php echo $class; ?>"><?php echo htmlspecialchars($user['role']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Department:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($user['department_name']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Company:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($user['company_name']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Status:</strong><br>
                    <?php if ($user['is_active']): ?>
                        <span class="badge bg-success">Active</span>
                    <?php else: ?>
                        <span class="badge bg-secondary">Inactive</span>
                    <?php endif; ?>
                </div>
                
                <div class="mb-3">
                    <strong>Created:</strong><br>
                    <small class="text-muted"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></small>
                </div>
                
                <div class="mb-0">
                    <strong>Last Updated:</strong><br>
                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($user['updated_at'])); ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load departments based on selected company
function loadDepartments() {
    const companyId = document.getElementById('company_id').value;
    const departmentSelect = document.getElementById('department_id');
    const currentDepartment = '<?php echo $form_data['department_id']; ?>';
    
    // Reset department dropdown
    departmentSelect.innerHTML = '<option value="">Select Department</option>';
    
    if (!companyId) return;
    
    // Filter departments by company
    const departments = <?php echo json_encode($departments); ?>;
    departments.forEach(function(dept) {
        if (dept.company_id == companyId) {
            const option = document.createElement('option');
            option.value = dept.id;
            option.textContent = dept.name;
            if (dept.id == currentDepartment) {
                option.selected = true;
            }
            departmentSelect.appendChild(option);
        }
    });
}

// Initialize departments on page load
document.addEventListener('DOMContentLoaded', function() {
    loadDepartments();
});
</script>

<?php include '../includes/footer.php'; ?>