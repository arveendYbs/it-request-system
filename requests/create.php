<?php
/**
 * Create Request Page
 * requests/create.php
 */

require_once '../includes/auth.php';
requireLogin();

$page_title = 'Create New Request';
$current_user = getCurrentUser();

$errors = [];
$form_data = [
    'title' => '',
    'description' => '',
    'category_id' => '',
    'subcategory_id' => ''
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category_id' => $_POST['category_id'] ?? '',
        'subcategory_id' => $_POST['subcategory_id'] ?? ''
    ];
    
    // Validation
    if (empty($form_data['title'])) {
        $errors[] = 'Title is required.';
    }
    
    if (empty($form_data['description'])) {
        $errors[] = 'Description is required.';
    }
    
    if (empty($form_data['category_id'])) {
        $errors[] = 'Category is required.';
    }
    
    if (empty($form_data['subcategory_id'])) {
        $errors[] = 'Subcategory is required.';
    }
    
    // Validate subcategory belongs to category
    if (!empty($form_data['category_id']) && !empty($form_data['subcategory_id'])) {
        $subcategory_check = fetchOne($pdo, 
            "SELECT id FROM subcategories WHERE id = ? AND category_id = ?",
            [$form_data['subcategory_id'], $form_data['category_id']]
        );
        
        if (!$subcategory_check) {
            $errors[] = 'Invalid subcategory selection.';
        }
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            // Determine initial status based on reporting structure
            $user = fetchOne($pdo, "SELECT reporting_manager_id FROM users WHERE id = ?", [$current_user['id']]);
            $initial_status = $user['reporting_manager_id'] ? 'Pending Manager' : 'Pending IT HOD';
            $initial_status = 'Pending Manager'; // Default status
            
            if ($user['reporting_manager_id']) {
                // User has a reporting manager
                if ($user['manager_role'] === 'IT Manager') {
                    // If reporting manager is IT Manager, skip regular manager approval
                    $initial_status = 'Pending IT HOD';
                } else {
                    // Regular manager approval needed first
                    $initial_status = 'Pending Manager';
                }
            } else {
                // No reporting manager, goes directly to IT Manager
                $initial_status = 'Pending IT HOD';
            }
            // Insert request
            $request_id = executeQuery($pdo, "
                INSERT INTO requests (title, description, category_id, subcategory_id, user_id, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [
                $form_data['title'],
                $form_data['description'],
                $form_data['category_id'],
                $form_data['subcategory_id'],
                $current_user['id'],
                $initial_status
            ]);
            
            $request_id = $pdo->lastInsertId();
            
            // Handle file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                $max_size = 5 * 1024 * 1024; // 5MB
                $max_files = 3;
                
                $file_count = count(array_filter($_FILES['attachments']['name']));
                if ($file_count > $max_files) {
                    throw new Exception("Maximum $max_files files allowed.");
                }
                
                for ($i = 0; $i < count($_FILES['attachments']['name']); $i++) {
                    if ($_FILES['attachments']['error'][$i] === UPLOAD_ERR_OK) {
                        $file_name = $_FILES['attachments']['name'][$i];
                        $file_tmp = $_FILES['attachments']['tmp_name'][$i];
                        $file_size = $_FILES['attachments']['size'][$i];
                        $file_type = $_FILES['attachments']['type'][$i];
                        
                        // Validate file type
                        if (!in_array($file_type, $allowed_types)) {
                            throw new Exception("File type not allowed for: $file_name");
                        }
                        
                        // Validate file size
                        if ($file_size > $max_size) {
                            throw new Exception("File too large: $file_name");
                        }
                        
                        // Generate unique filename
                        $extension = pathinfo($file_name, PATHINFO_EXTENSION);
                        $stored_filename = 'req_' . $request_id . '_' . time() . '_' . $i . '.' . $extension;
                        $file_path = $upload_dir . $stored_filename;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            // Save to database
                            executeQuery($pdo, "
                                INSERT INTO request_attachments (request_id, original_filename, stored_filename, file_size, file_type)
                                VALUES (?, ?, ?, ?, ?)
                            ", [$request_id, $file_name, $stored_filename, $file_size, $file_type]);
                        } else {
                            throw new Exception("Failed to upload file: $file_name");
                        }
                    }
                }
            }
            
            $pdo->commit();
            $_SESSION['success_message'] = 'Request created successfully!';
            header('Location: view.php?id=' . $request_id);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = $e->getMessage();
        }
    }
}

// Get categories for dropdown
$categories = fetchAll($pdo, "SELECT id, name FROM categories ORDER BY name");

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-plus-lg me-2"></i>Create New Request
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="./" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Requests
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
                <h5 class="card-title mb-0">Request Details</h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="requestForm">
                    <div class="mb-3">
                        <label for="title" class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="title" name="title" 
                               value="<?php echo htmlspecialchars($form_data['title']); ?>" 
                               placeholder="Brief description of your request" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="description" name="description" rows="5" 
                                  placeholder="Detailed description of your IT request..." required><?php echo htmlspecialchars($form_data['description']); ?></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="category_id" class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" id="category_id" name="category_id" required 
                                        onchange="loadSubcategories(this.value, document.getElementById('subcategory_id'))">
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                                <?php echo ($form_data['category_id'] == $category['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label for="subcategory_id" class="form-label">Subcategory <span class="text-danger">*</span></label>
                                <select class="form-select" id="subcategory_id" name="subcategory_id" required>
                                    <option value="">Select Subcategory</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachments" class="form-label">Attachments</label>
                        <input type="file" class="form-control" id="attachments" name="attachments[]" 
                               multiple accept=".pdf,.jpg,.jpeg,.png,.gif"
                               onchange="validateFileUpload(this)">
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            Maximum 3 files, 5MB each. Allowed formats: PDF, JPEG, PNG, GIF
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="./" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Create Request
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
                    <i class="bi bi-info-circle me-2"></i>Request Information
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>Requester:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($current_user['name']); ?></span>
                </div>
                
                <?php
                $user_details = fetchOne($pdo, "
                    SELECT u.*, d.name as department_name, c.name as company_name, m.name as manager_name
                    FROM users u
                    LEFT JOIN departments d ON u.department_id = d.id
                    LEFT JOIN companies c ON u.company_id = c.id
                    LEFT JOIN users m ON u.reporting_manager_id = m.id
                    WHERE u.id = ?
                ", [$current_user['id']]);
                ?>
                
                <div class="mb-3">
                    <strong>Department:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($user_details['department_name']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Company:</strong><br>
                    <span class="text-muted"><?php echo htmlspecialchars($user_details['company_name']); ?></span>
                </div>
                
                <?php if ($user_details['manager_name']): ?>
                    <div class="mb-3">
                        <strong>Reporting Manager:</strong><br>
                        <span class="text-muted"><?php echo htmlspecialchars($user_details['manager_name']); ?></span>
                    </div>
                <?php endif; ?>
                
                <hr>
                
                <div class="alert alert-info">
                    <h6 class="alert-heading">
                        <i class="bi bi-lightbulb me-2"></i>Approval Process
                    </h6>
                    <small>
                        <?php if ($user_details['manager_name']): ?>
                            1. Your manager will review and approve<br>
                            2. IT Manager will give final approval
                        <?php else: ?>
                            Your request will go directly to IT Manager for approval
                        <?php endif; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load subcategories when category changes
<?php if (!empty($form_data['category_id'])): ?>
    // Load subcategories for pre-selected category
    document.addEventListener('DOMContentLoaded', function() {
        loadSubcategories(<?php echo $form_data['category_id']; ?>, document.getElementById('subcategory_id'));
        
        // Pre-select subcategory if available
        setTimeout(function() {
            const subcategorySelect = document.getElementById('subcategory_id');
            const preSelectedValue = '<?php echo $form_data['subcategory_id']; ?>';
            if (preSelectedValue) {
                subcategorySelect.value = preSelectedValue;
            }
        }, 500);
    });
<?php endif; ?>
</script>

<?php include '../includes/footer.php'; ?>