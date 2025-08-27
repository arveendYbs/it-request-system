<?php
/**
 * Categories Management Page
 * categories/index.php
 */

require_once '../includes/auth.php';
requireRole(['Admin']);

$page_title = 'Categories & Subcategories Management';

// Handle CRUD operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'create_category') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($name)) {
                throw new Exception('Category name is required.');
            }
            
            // Check for duplicate category name
            $existing = fetchOne($pdo, "SELECT id FROM categories WHERE name = ?", [$name]);
            if ($existing) {
                throw new Exception('Category name already exists.');
            }
            
            executeQuery($pdo, "
                INSERT INTO categories (name, description, created_at)
                VALUES (?, ?, NOW())
            ", [$name, $description]);
            
            $_SESSION['success_message'] = 'Category created successfully!';
            
        } elseif ($action === 'update_category') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            
            if (empty($id) || empty($name)) {
                throw new Exception('ID and name are required.');
            }
            
            // Check for duplicate category name (excluding current)
            $existing = fetchOne($pdo, "SELECT id FROM categories WHERE name = ? AND id != ?", [$name, $id]);
            if ($existing) {
                throw new Exception('Category name already exists.');
            }
            
            executeQuery($pdo, "
                UPDATE categories 
                SET name = ?, description = ?, updated_at = NOW()
                WHERE id = ?
            ", [$name, $description, $id]);
            
            $_SESSION['success_message'] = 'Category updated successfully!';
            
        } elseif ($action === 'delete_category') {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('Category ID is required.');
            }
            
            // Check if category has subcategories
            $subcat_count = fetchOne($pdo, "SELECT COUNT(*) as count FROM subcategories WHERE category_id = ?", [$id])['count'];
            if ($subcat_count > 0) {
                throw new Exception("Cannot delete category. It has $subcat_count subcategory(ies).");
            }
            
            // Check if category has requests
            $request_count = fetchOne($pdo, "SELECT COUNT(*) as count FROM requests WHERE category_id = ?", [$id])['count'];
            if ($request_count > 0) {
                throw new Exception("Cannot delete category. It has $request_count request(s).");
            }
            
            executeQuery($pdo, "DELETE FROM categories WHERE id = ?", [$id]);
            
            $_SESSION['success_message'] = 'Category deleted successfully!';
            
        } elseif ($action === 'create_subcategory') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = $_POST['category_id'] ?? '';
            
            if (empty($name) || empty($category_id)) {
                throw new Exception('Subcategory name and category are required.');
            }
            
            // Check for duplicate subcategory name within same category
            $existing = fetchOne($pdo, "SELECT id FROM subcategories WHERE name = ? AND category_id = ?", [$name, $category_id]);
            if ($existing) {
                throw new Exception('Subcategory name already exists in this category.');
            }
            
            executeQuery($pdo, "
                INSERT INTO subcategories (name, description, category_id, created_at)
                VALUES (?, ?, ?, NOW())
            ", [$name, $description, $category_id]);
            
            $_SESSION['success_message'] = 'Subcategory created successfully!';
            
        } elseif ($action === 'update_subcategory') {
            $id = $_POST['id'] ?? '';
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $category_id = $_POST['category_id'] ?? '';
            
            if (empty($id) || empty($name) || empty($category_id)) {
                throw new Exception('ID, name, and category are required.');
            }
            
            // Check for duplicate subcategory name within same category (excluding current)
            $existing = fetchOne($pdo, "SELECT id FROM subcategories WHERE name = ? AND category_id = ? AND id != ?", [$name, $category_id, $id]);
            if ($existing) {
                throw new Exception('Subcategory name already exists in this category.');
            }
            
            executeQuery($pdo, "
                UPDATE subcategories 
                SET name = ?, description = ?, category_id = ?, updated_at = NOW()
                WHERE id = ?
            ", [$name, $description, $category_id, $id]);
            
            $_SESSION['success_message'] = 'Subcategory updated successfully!';
            
        } elseif ($action === 'delete_subcategory') {
            $id = $_POST['id'] ?? '';
            
            if (empty($id)) {
                throw new Exception('Subcategory ID is required.');
            }
            
            // Check if subcategory has requests
            $request_count = fetchOne($pdo, "SELECT COUNT(*) as count FROM requests WHERE subcategory_id = ?", [$id])['count'];
            if ($request_count > 0) {
                throw new Exception("Cannot delete subcategory. It has $request_count request(s).");
            }
            
            executeQuery($pdo, "DELETE FROM subcategories WHERE id = ?", [$id]);
            
            $_SESSION['success_message'] = 'Subcategory deleted successfully!';
        }
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = $e->getMessage();
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit();
}

// Get categories with subcategory counts
$categories = fetchAll($pdo, "
    SELECT c.*,
           COUNT(sc.id) as subcategory_count,
           COUNT(r.id) as request_count
    FROM categories c
    LEFT JOIN subcategories sc ON c.id = sc.category_id
    LEFT JOIN requests r ON c.id = r.category_id
    GROUP BY c.id
    ORDER BY c.name
");

// Get subcategories with category info
$subcategories = fetchAll($pdo, "
    SELECT sc.*, c.name as category_name,
           COUNT(r.id) as request_count
    FROM subcategories sc
    JOIN categories c ON sc.category_id = c.id
    LEFT JOIN requests r ON sc.id = r.subcategory_id
    GROUP BY sc.id
    ORDER BY c.name, sc.name
");

include '../includes/header.php';
?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">
        <i class="bi bi-tags me-2"></i>Categories & Subcategories
    </h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCreateCategoryModal()">
                <i class="bi bi-plus-lg me-1"></i>Add Category
            </button>
            <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#subcategoryModal" onclick="openCreateSubcategoryModal()">
                <i class="bi bi-plus-lg me-1"></i>Add Subcategory
            </button>
        </div>
    </div>
</div>

<!-- Navigation Tabs -->
<ul class="nav nav-tabs mb-4" id="managementTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="categories-tab" data-bs-toggle="tab" data-bs-target="#categories" type="button" role="tab">
            <i class="bi bi-folder me-2"></i>Categories (<?php echo count($categories); ?>)
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="subcategories-tab" data-bs-toggle="tab" data-bs-target="#subcategories" type="button" role="tab">
            <i class="bi bi-tags me-2"></i>Subcategories (<?php echo count($subcategories); ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="managementTabsContent">
    <!-- Categories Tab -->
    <div class="tab-pane fade show active" id="categories" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Categories</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($categories)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-folder display-1 text-muted"></i>
                        <h4 class="mt-3">No categories found</h4>
                        <p class="text-muted">Create your first category to get started.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#categoryModal" onclick="openCreateCategoryModal()">
                            <i class="bi bi-plus-lg me-1"></i>Add Category
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Category Name</th>
                                    <th>Description</th>
                                    <th>Subcategories</th>
                                    <th>Requests</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($categories as $category): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-folder text-primary me-3" style="font-size: 1.5rem;"></i>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($category['name']); ?></div>
                                                    <small class="text-muted">ID: <?php echo $category['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if ($category['description']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($category['description'], 0, 80)); ?>
                                                <?php if (strlen($category['description']) > 80) echo '...'; ?></small>
                                            <?php else: ?>
                                                <small class="text-muted fst-italic">No description</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?php echo $category['subcategory_count']; ?> subcategories</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $category['request_count']; ?> requests</span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($category['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="openEditCategoryModal(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                                        title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                
                                                <?php if ($category['subcategory_count'] == 0 && $category['request_count'] == 0): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')" 
                                                            title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-secondary" disabled 
                                                            title="Cannot delete - has subcategories or requests">
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
    </div>

    <!-- Subcategories Tab -->
    <div class="tab-pane fade" id="subcategories" role="tabpanel">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Subcategories</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($subcategories)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-tags display-1 text-muted"></i>
                        <h4 class="mt-3">No subcategories found</h4>
                        <p class="text-muted">Create your first subcategory to get started.</p>
                        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#subcategoryModal" onclick="openCreateSubcategoryModal()">
                            <i class="bi bi-plus-lg me-1"></i>Add Subcategory
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Subcategory Name</th>
                                    <th>Parent Category</th>
                                    <th>Description</th>
                                    <th>Requests</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subcategories as $subcategory): ?>
                                    <tr>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-tag text-secondary me-3" style="font-size: 1.2rem;"></i>
                                                <div>
                                                    <div class="fw-medium"><?php echo htmlspecialchars($subcategory['name']); ?></div>
                                                    <small class="text-muted">ID: <?php echo $subcategory['id']; ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo htmlspecialchars($subcategory['category_name']); ?></span>
                                        </td>
                                        <td>
                                            <?php if ($subcategory['description']): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars(substr($subcategory['description'], 0, 60)); ?>
                                                <?php if (strlen($subcategory['description']) > 60) echo '...'; ?></small>
                                            <?php else: ?>
                                                <small class="text-muted fst-italic">No description</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?php echo $subcategory['request_count']; ?> requests</span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?php echo date('M j, Y', strtotime($subcategory['created_at'])); ?>
                                            </small>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" 
                                                        onclick="openEditSubcategoryModal(<?php echo htmlspecialchars(json_encode($subcategory)); ?>)" 
                                                        title="Edit">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                
                                                <?php if ($subcategory['request_count'] == 0): ?>
                                                    <button type="button" class="btn btn-outline-danger" 
                                                            onclick="deleteSubcategory(<?php echo $subcategory['id']; ?>, '<?php echo htmlspecialchars($subcategory['name']); ?>')" 
                                                            title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-secondary" disabled 
                                                            title="Cannot delete - has requests">
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
    </div>
</div>

<!-- Category Modal -->
<div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="categoryForm">
                <input type="hidden" name="action" id="categoryAction" value="create_category">
                <input type="hidden" name="id" id="categoryId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="categoryModalTitle">Add Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="categoryName" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="categoryName" name="name" required 
                               placeholder="e.g., Hardware">
                    </div>
                    
                    <div class="mb-3">
                        <label for="categoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="categoryDescription" name="description" rows="3" 
                                  placeholder="Brief description of the category..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="categorySaveButton">
                        <i class="bi bi-check-lg me-1"></i>Save Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Subcategory Modal -->
<div class="modal fade" id="subcategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" id="subcategoryForm">
                <input type="hidden" name="action" id="subcategoryAction" value="create_subcategory">
                <input type="hidden" name="id" id="subcategoryId">
                
                <div class="modal-header">
                    <h5 class="modal-title" id="subcategoryModalTitle">Add Subcategory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="subcategoryName" class="form-label">Subcategory Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="subcategoryName" name="name" required 
                               placeholder="e.g., Laptop/Desktop">
                    </div>
                    
                    <div class="mb-3">
                        <label for="subcategoryCategoryId" class="form-label">Parent Category <span class="text-danger">*</span></label>
                        <select class="form-select" id="subcategoryCategoryId" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>">
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="subcategoryDescription" class="form-label">Description</label>
                        <textarea class="form-control" id="subcategoryDescription" name="description" rows="3" 
                                  placeholder="Brief description of the subcategory..."></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="subcategorySaveButton">
                        <i class="bi bi-check-lg me-1"></i>Save Subcategory
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Forms (hidden) -->
<form method="POST" id="deleteCategoryForm" style="display: none;">
    <input type="hidden" name="action" value="delete_category">
    <input type="hidden" name="id" id="deleteCategoryId">
</form>

<form method="POST" id="deleteSubcategoryForm" style="display: none;">
    <input type="hidden" name="action" value="delete_subcategory">
    <input type="hidden" name="id" id="deleteSubcategoryId">
</form>

<script>
// Category functions
function openCreateCategoryModal() {
    document.getElementById('categoryAction').value = 'create_category';
    document.getElementById('categoryModalTitle').textContent = 'Add Category';
    document.getElementById('categorySaveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Category';
    
    // Reset form
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
}

function openEditCategoryModal(category) {
    document.getElementById('categoryAction').value = 'update_category';
    document.getElementById('categoryModalTitle').textContent = 'Edit Category';
    document.getElementById('categorySaveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Category';
    
    // Populate form
    document.getElementById('categoryId').value = category.id;
    document.getElementById('categoryName').value = category.name;
    document.getElementById('categoryDescription').value = category.description || '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('categoryModal')).show();
}

function deleteCategory(id, name) {
    if (confirm(`Are you sure you want to delete the category "${name}"?\n\nThis action cannot be undone.`)) {
        document.getElementById('deleteCategoryId').value = id;
        document.getElementById('deleteCategoryForm').submit();
    }
}

// Subcategory functions
function openCreateSubcategoryModal() {
    document.getElementById('subcategoryAction').value = 'create_subcategory';
    document.getElementById('subcategoryModalTitle').textContent = 'Add Subcategory';
    document.getElementById('subcategorySaveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Save Subcategory';
    
    // Reset form
    document.getElementById('subcategoryForm').reset();
    document.getElementById('subcategoryId').value = '';
}

function openEditSubcategoryModal(subcategory) {
    document.getElementById('subcategoryAction').value = 'update_subcategory';
    document.getElementById('subcategoryModalTitle').textContent = 'Edit Subcategory';
    document.getElementById('subcategorySaveButton').innerHTML = '<i class="bi bi-check-lg me-1"></i>Update Subcategory';
    
    // Populate form
    document.getElementById('subcategoryId').value = subcategory.id;
    document.getElementById('subcategoryName').value = subcategory.name;
    document.getElementById('subcategoryCategoryId').value = subcategory.category_id;
    document.getElementById('subcategoryDescription').value = subcategory.description || '';
    
    // Show modal
    new bootstrap.Modal(document.getElementById('subcategoryModal')).show();
}

function deleteSubcategory(id, name) {
    if (confirm(`Are you sure you want to delete the subcategory "${name}"?\n\nThis action cannot be undone.`)) {
        document.getElementById('deleteSubcategoryId').value = id;
        document.getElementById('deleteSubcategoryForm').submit();
    }
}

// Form validations
document.getElementById('categoryForm').addEventListener('submit', function(e) {
    const name = document.getElementById('categoryName').value.trim();
    if (!name) {
        e.preventDefault();
        alert('Please enter a category name.');
        document.getElementById('categoryName').focus();
    }
});

document.getElementById('subcategoryForm').addEventListener('submit', function(e) {
    const name = document.getElementById('subcategoryName').value.trim();
    const categoryId = document.getElementById('subcategoryCategoryId').value;
    
    if (!name) {
        e.preventDefault();
        alert('Please enter a subcategory name.');
        document.getElementById('subcategoryName').focus();
        return;
    }
    
    if (!categoryId) {
        e.preventDefault();
        alert('Please select a parent category.');
        document.getElementById('subcategoryCategoryId').focus();
    }
});
</script>

<?php include '../includes/footer.php'; ?>