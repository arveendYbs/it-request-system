<?php
/**
 * Edit Request Page
 * requests/edit.php
 */

require_once '../includes/auth.php';
requireLogin();

$request_id = $_GET['id'] ?? '';
$current_user = getCurrentUser();

if (empty($request_id) || !is_numeric($request_id)) {
    $_SESSION['error_message'] = 'Invalid request ID.';
    header('Location: ./');
    exit();
}

// Get request details
$request = fetchOne($pdo, "
    SELECT r.*, u.name as user_name
    FROM requests r
    JOIN users u ON r.user_id = u.id
    WHERE r.id = ?
", [$request_id]);

if (!$request) {
    $_SESSION['error_message'] = 'Request not found.';
    header('Location: ./');
    exit();
}

// Check if user can edit this request
if (!$auth->canEditRequest($request_id)) {
    $_SESSION['error_message'] = 'You do not have permission to edit this request.';
    header('Location: view.php?id=' . $request_id);
    exit();
}

$errors = [];
$form_data = [
    'title' => $request['title'],
    'description' => $request['description'],
    'category_id' => $request['category_id'],
    'subcategory_id' => $request['subcategory_id'], 
    'site_id' => $request['site_id']
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form_data = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'category_id' => $_POST['category_id'] ?? '',
        'subcategory_id' => $_POST['subcategory_id'] ?? '',
        'site_id' => $_POST['site_id'] ?? ''
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
            
            // Update request
            executeQuery($pdo, "
                UPDATE requests 
                SET title = ?, description = ?, category_id = ?, subcategory_id = ?, site_id = ?, updated_at = NOW()
                WHERE id = ?
            ", [
                $form_data['title'],
                $form_data['description'],
                $form_data['category_id'],
                $form_data['subcategory_id'],
                $form_data['site_id'] ?: null,
                $request_id
            ]);
            
            // Handle new file uploads
            if (!empty($_FILES['attachments']['name'][0])) {
                $upload_dir = '../uploads/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
                $max_size = 5 * 1024 * 1024; // 5MB
                
                // Check current attachment count
                $current_attachments = fetchOne($pdo, 
                    "SELECT COUNT(*) as count FROM request_attachments WHERE request_id = ?",
                    [$request_id]
                )['count'];
                
                $new_files = count(array_filter($_FILES['attachments']['name']));
                $total_files = $current_attachments + $new_files;
                
                if ($total_files > 3) {
                    throw new Exception("Maximum 3 attachments allowed. You currently have $current_attachments file(s).");
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
            $_SESSION['success_message'] = 'Request updated successfully!';
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
$sites = fetchAll($pdo, "SELECT id, name FROM sites ORDER BY name ");
// Get current attachments
$attachments = fetchAll($pdo, "
    SELECT * FROM request_attachments WHERE request_id = ? ORDER BY uploaded_at
", [$request_id]);

$page_title = 'Edit Request #' . $request['id'];

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-pencil me-2"></i>Edit Request #<?php echo $request['id']; ?>
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="view.php?id=<?php echo $request_id; ?>" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i>Back to Request
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
                        <label for="site_id" class="form-label">Site/Location</label>
                        <select class="form-select" id="site_id" name="site_id">
                            <option value="">Select Site (Optional)</option>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?php echo $site['id']; ?>" 
                                        <?php echo ($form_data['site_id'] == $site['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($site['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Select the site/location where this request applies</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachments" class="form-label">Add More Attachments</label>
                        <input type="file" class="form-control" id="attachments" name="attachments[]" 
                               multiple accept=".pdf,.jpg,.jpeg,.png,.gif"
                               onchange="validateFileUpload(this)">
                        <div class="form-text">
                            <i class="bi bi-info-circle me-1"></i>
                            You currently have <?php echo count($attachments); ?> attachment(s). 
                            Maximum 3 files total, 5MB each. Allowed formats: PDF, JPEG, PNG, GIF
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="view.php?id=<?php echo $request_id; ?>" class="btn btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-lg me-1"></i>Update Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Current Attachments -->
        <?php if (!empty($attachments)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-paperclip me-2"></i>Current Attachments (<?php echo count($attachments); ?>)
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php foreach ($attachments as $attachment): ?>
                            <div class="col-md-6 mb-3">
                                <div class="d-flex align-items-center p-3 border rounded">
                                    <div class="me-3">
                                        <?php if (strpos($attachment['file_type'], 'image/') === 0): ?>
                                            <i class="bi bi-image text-primary" style="font-size: 2rem;"></i>
                                        <?php else: ?>
                                            <i class="bi bi-file-pdf text-danger" style="font-size: 2rem;"></i>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($attachment['original_filename']); ?></h6>
                                        <small class="text-muted">
                                            <?php echo number_format($attachment['file_size'] / 1024, 1); ?> KB •
                                            <?php echo date('M j, Y', strtotime($attachment['uploaded_at'])); ?>
                                        </small>
                                    </div>
                                    <div>
                                        <a href="../uploads/<?php echo htmlspecialchars($attachment['stored_filename']); ?>" 
                                           class="btn btn-sm btn-outline-primary me-2" target="_blank">
                                            <i class="bi bi-download"></i>
                                        </a>
                                        <a href="delete_attachment.php?id=<?php echo $attachment['id']; ?>&request_id=<?php echo $request_id; ?>" 
                                           class="btn btn-sm btn-outline-danger"
                                           onclick="return confirm('Are you sure you want to delete this attachment?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
                    <strong>Request ID:</strong><br>
                    <span class="text-muted">#<?php echo $request['id']; ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Status:</strong><br>
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
                    <span class="badge bg-<?php echo $class; ?>"><?php echo htmlspecialchars($request['status']); ?></span>
                </div>
                
                <div class="mb-3">
                    <strong>Created:</strong><br>
                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($request['created_at'])); ?></small>
                </div>
                
                <div class="mb-3">
                    <strong>Last Updated:</strong><br>
                    <small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($request['updated_at'])); ?></small>
                </div>
                
                <hr>
                
                <div class="alert alert-warning">
                    <h6 class="alert-heading">
                        <i class="bi bi-exclamation-triangle me-2"></i>Important Note
                    </h6>
                    <small>
                        You can only edit this request while it's in "Pending Manager" status. 
                        Once approved or rejected, editing will not be allowed.
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load subcategories when category changes
document.addEventListener('DOMContentLoaded', function() {
    // Load subcategories for pre-selected category
    const categoryId = document.getElementById('category_id').value;
    if (categoryId) {
        loadSubcategories(categoryId, document.getElementById('subcategory_id'));
        
        // Pre-select subcategory if available
        setTimeout(function() {
            const subcategorySelect = document.getElementById('subcategory_id');
            const preSelectedValue = '<?php echo $form_data['subcategory_id']; ?>';
            if (preSelectedValue) {
                subcategorySelect.value = preSelectedValue;
            }
        }, 500);
    }
});
</script>

<?php include '../includes/footer.php'; ?>