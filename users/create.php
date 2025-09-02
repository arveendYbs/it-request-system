<?php
/**
 * Create User Page
 * users/create.php
 */

require_once '../includes/auth.php';
requireRole(['Admin']);

$page_title = 'Add New User';
$current_user = getCurrentUser();

$errors = [];
$form_data = [
    'name' => '',
    'email' => '',
    'role' => 'User',
    'department_id' => '',
    'company_id' => '',
    'reporting_manager_id' => '',
    'site_id' => '',
    'is_active' => 1,
    'password' => ''
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
        'site_id' => $_POST['site_id'] ?? null,
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
    
    if (empty($form_data['password'])) {
        $errors[] = 'Password is required.';
    } elseif (strlen($form_data['password']) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
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
    
    // Check if email is already used
    if (!empty($form_data['email'])) {
        $existing_user = fetchOne($pdo, "SELECT id FROM users WHERE email = ?", [$form_data['email']]);
        if ($existing_user) {
            $errors[] = 'Email address is already in use.';
        }
    }
    
    // Validate department belongs to company
    if (!empty($form_data['department_id']) && !empty($form_data['company_id'])) {
        $department_check = fetchOne($pdo, 
            "SELECT id FROM departments WHERE id = ? AND company_id = ?",
            [$form_data['department_id'], $form_data['company_id']]
        );
        if (!$department_check) {
            $errors[] = 'Selected department does not belong to the selected company.';
        }
    }
    
    // Validate manager assignment
    if (!empty($form_data['reporting_manager_id'])) {
        $manager = fetchOne($pdo, "SELECT role FROM users WHERE id = ? AND is_active = 1", [$form_data['reporting_manager_id']]);
        if (!$manager) {
            $errors[] = 'Selected reporting manager not found or inactive.';
        } elseif (!in_array($manager['role'], ['Manager', 'IT Manager', 'Admin'])) {
            $errors[] = 'Selected reporting manager must have Manager, IT Manager, or Admin role.';
        }
    }
    
    if (empty($errors)) {
        try {
            // Hash the password
            $hashed_password = password_hash($form_data['password'], PASSWORD_DEFAULT);
            
            executeQuery($pdo, "
                INSERT INTO users (name, email, password, role, department_id, company_id, reporting_manager_id, site_id, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ", [
                $form_data['name'],
                $form_data['email'],
                $hashed_password,
                $form_data['role'],
                $form_data['department_id'],
                $form_data['company_id'],
                $form_data['reporting_manager_id'] ?: null,
                $form_data['site_id'] ?: null,
                $form_data['is_active']
            ]);
            
            $_SESSION['success_message'] = 'User created successfully!';
            header('Location: ./');
            exit();
            
        } catch (Exception $e) {
            $errors[] = 'Failed to create user: ' . $e->getMessage();
        }
    }
}

// Get dropdown options
$companies = fetchAll($pdo, "SELECT id, name FROM companies ORDER BY name");
$departments = fetchAll($pdo, "SELECT id, name, company_id FROM departments ORDER BY name");
$sites = fetchAll($pdo, "SELECT id, name FROM sites ORDER BY name");
// Get potential managers
$managers = fetchAll($pdo, "
    SELECT id, name, email, role 
    FROM users 
    WHERE role IN ('Manager', 'IT Manager', 'Admin') 
    AND is_active = 1
    ORDER BY name
");

$role_options = ['User', 'Manager', 'IT Manager', 'Admin'];

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-person-plus me-2"></i>Add New User
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="./" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Users
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
                                       value="<?php echo htmlspecialchars($form_data['name']); ?>" required
                                       placeholder="Enter full name">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($form_data['email']); ?>" required
                                       placeholder="user@company.com">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="password" class="form-label">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="password" name="password" required
                                       placeholder="Minimum 6 characters">
                                <div class="form-text">Password must be at least 6 characters long.</div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="role" class="form-label">Role <span class="text-danger">*</span></label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select Role</option>
                                    <?php foreach ($role_options as $role): ?>
                                        <option value="<?php echo htmlspecialchars($role); ?>" 
                                                <?php echo ($form_data['role'] === $role) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="company_id" class="form-label">Company <span class="text-danger">*</span></label>
                                <select class="form-select" id="company_id" name="company_id" required 
                                        onchange="loadDepartments()">
                                    <option value="">Select Company</option>
                                    <?php foreach ($companies as $company): ?>
                                        <option value="<?php echo $company['id']; ?>" 
                                                <?php echo ($form_data['company_id'] == $company['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($company['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
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
                    </div>
                    
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
                        <div class="form-text">Select a manager if this user reports to someone.</div>
                    </div>

                      
                    <div class="mb-3">
                        <label for="site_id" class="form-label">Site/Location</label>
                        <select class="form-select" id="site_id" name="site_id">
                            <option value="">No Site</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?php echo $site['id']; ?>" 
                                        <?php echo ($form_data['site_id'] == $site['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($site['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Select the primary site/location for this user.</div>
                    </div>
                    
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
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="./" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Create User
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
                    <i class="bi bi-info-circle me-2"></i>User Creation Guidelines
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Role Descriptions:</strong>
                    <ul class="small mt-2">
                        <li><strong>Admin:</strong> Full system access</li>
                        <li><strong>IT Manager:</strong> Can approve all requests</li>
                        <li><strong>Manager:</strong> Can approve team requests</li>
                        <li><strong>User:</strong> Can create requests</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <strong>Password Requirements:</strong>
                    <ul class="small mt-2">
                        <li>Minimum 6 characters</li>
                        <li>Use a strong, unique password</li>
                        <li>User can change it after first login</li>
                    </ul>
                </div>
                
                <div class="mb-3">
                    <strong>Department & Company:</strong>
                    <p class="small">Select the company first, then choose from available departments in that company.</p>
                </div>
                
                <div class="alert alert-info">
                    <small>
                        <i class="bi bi-lightbulb me-1"></i>
                        <strong>Tip:</strong> Users with Manager role can approve requests from their reporting employees.
                    </small>
                </div>
            </div>
        </div>
        
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="card-title mb-0">
                    <i class="bi bi-people me-2"></i>Current Users
                </h6>
            </div>
            <div class="card-body">
                <?php
                $user_counts = fetchAll($pdo, "
                    SELECT role, COUNT(*) as count 
                    FROM users 
                    WHERE is_active = 1 
                    GROUP BY role 
                    ORDER BY count DESC
                ");
                ?>
                
                <?php if (!empty($user_counts)): ?>
                    <?php foreach ($user_counts as $count): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span class="small"><?php echo htmlspecialchars($count['role']); ?>s</span>
                            <span class="badge bg-secondary"><?php echo $count['count']; ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-muted small">No active users found.</p>
                <?php endif; ?>
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
    
    // Form validation
    document.getElementById('userForm').addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const email = document.getElementById('email').value;
        const name = document.getElementById('name').value;
        const role = document.getElementById('role').value;
        const company = document.getElementById('company_id').value;
        const department = document.getElementById('department_id').value;
        
        // Basic validation
        if (!name.trim()) {
            e.preventDefault();
            alert('Please enter the user\'s full name.');
            document.getElementById('name').focus();
            return;
        }
        
        if (!email.trim()) {
            e.preventDefault();
            alert('Please enter a valid email address.');
            document.getElementById('email').focus();
            return;
        }
        
        if (!password || password.length < 6) {
            e.preventDefault();
            alert('Password must be at least 6 characters long.');
            document.getElementById('password').focus();
            return;
        }
        
        if (!role) {
            e.preventDefault();
            alert('Please select a role for the user.');
            document.getElementById('role').focus();
            return;
        }
        
        if (!company) {
            e.preventDefault();
            alert('Please select a company.');
            document.getElementById('company_id').focus();
            return;
        }
        
        if (!department) {
            e.preventDefault();
            alert('Please select a department.');
            document.getElementById('department_id').focus();
            return;
        }
    });
});
</script>

<?php include '../includes/footer.php'; ?>