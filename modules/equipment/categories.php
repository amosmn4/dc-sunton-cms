<?php
/**
 * Equipment Categories Management Page
 * Deliverance Church Management System
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('equipment')) {
    setFlashMessage('error', 'You do not have permission to access this page.');
    header('Location: ' . BASE_URL . 'modules/dashboard/');
    exit;
}

// Page variables
$page_title = 'Equipment Categories';
$page_icon = 'fas fa-tags';

// Breadcrumb
$breadcrumb = [
    ['title' => 'Equipment', 'url' => BASE_URL . 'modules/equipment/'],
    ['title' => 'Categories']
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = Database::getInstance();
        
        if (isset($_POST['action'])) {
            switch ($_POST['action']) {
                case 'add':
                    $validation = validateInput($_POST, [
                        'name' => ['required', 'max:100'],
                        'description' => ['max:500']
                    ]);
                    
                    if ($validation['valid']) {
                        $data = $validation['data'];
                        
                        // Check if category already exists
                        $existingStmt = $db->executeQuery("SELECT id FROM equipment_categories WHERE name = ?", [$data['name']]);
                        if ($existingStmt->fetch()) {
                            setFlashMessage('error', 'Category already exists.');
                        } else {
                            $insertData = [
                                'name' => $data['name'],
                                'description' => $data['description'] ?? ''
                            ];
                            
                            $categoryId = insertRecord('equipment_categories', $insertData);
                            
                            if ($categoryId) {
                                logActivity('Equipment category added', 'equipment_categories', $categoryId, null, $insertData);
                                setFlashMessage('success', 'Category added successfully!');
                            } else {
                                setFlashMessage('error', 'Failed to add category.');
                            }
                        }
                    } else {
                        setFlashMessage('error', 'Please check your input: ' . implode(', ', $validation['errors']));
                    }
                    break;
                    
                case 'edit':
                    $validation = validateInput($_POST, [
                        'id' => ['required', 'numeric'],
                        'name' => ['required', 'max:100'],
                        'description' => ['max:500']
                    ]);
                    
                    if ($validation['valid']) {
                        $data = $validation['data'];
                        
                        // Check if category name already exists (excluding current)
                        $existingStmt = $db->executeQuery("SELECT id FROM equipment_categories WHERE name = ? AND id != ?", [$data['name'], $data['id']]);
                        if ($existingStmt->fetch()) {
                            setFlashMessage('error', 'Category name already exists.');
                        } else {
                            $updateData = [
                                'name' => $data['name'],
                                'description' => $data['description'] ?? ''
                            ];
                            
                            $updated = updateRecord('equipment_categories', $updateData, ['id' => $data['id']]);
                            
                            if ($updated) {
                                logActivity('Equipment category updated', 'equipment_categories', $data['id'], null, $updateData);
                                setFlashMessage('success', 'Category updated successfully!');
                            } else {
                                setFlashMessage('error', 'Failed to update category.');
                            }
                        }
                    } else {
                        setFlashMessage('error', 'Please check your input: ' . implode(', ', $validation['errors']));
                    }
                    break;
                    
                case 'delete':
                    $categoryId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
                    
                    if ($categoryId) {
                        // Check if category is in use
                        $usageStmt = $db->executeQuery("SELECT COUNT(*) as count FROM equipment WHERE category_id = ?", [$categoryId]);
                        $usage = $usageStmt->fetch();
                        
                        if ($usage['count'] > 0) {
                            setFlashMessage('error', 'Cannot delete category. It is currently assigned to ' . $usage['count'] . ' equipment item(s).');
                        } else {
                            $deleted = deleteRecord('equipment_categories', ['id' => $categoryId]);
                            
                            if ($deleted) {
                                logActivity('Equipment category deleted', 'equipment_categories', $categoryId);
                                setFlashMessage('success', 'Category deleted successfully!');
                            } else {
                                setFlashMessage('error', 'Failed to delete category.');
                            }
                        }
                    }
                    break;
            }
        }
    } catch (Exception $e) {
        error_log("Equipment categories error: " . $e->getMessage());
        setFlashMessage('error', 'An error occurred while processing your request.');
    }
    
    // Redirect to prevent form resubmission
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Get all categories with equipment count
    $query = "SELECT ec.*, COUNT(e.id) as equipment_count
              FROM equipment_categories ec
              LEFT JOIN equipment e ON ec.id = e.category_id
              GROUP BY ec.id
              ORDER BY ec.name";
    
    $stmt = $db->executeQuery($query);
    $categories = $stmt->fetchAll();
    
    // Get total statistics
    $totalCategoriesStmt = $db->executeQuery("SELECT COUNT(*) as total FROM equipment_categories");
    $totalCategories = $totalCategoriesStmt->fetch()['total'];
    
    $totalEquipmentStmt = $db->executeQuery("SELECT COUNT(*) as total FROM equipment");
    $totalEquipment = $totalEquipmentStmt->fetch()['total'];
    
} catch (Exception $e) {
    error_log("Equipment categories error: " . $e->getMessage());
    setFlashMessage('error', 'An error occurred while loading categories.');
    $categories = [];
    $totalCategories = 0;
    $totalEquipment = 0;
}

include '../../includes/header.php';
?>

<div class="container-fluid px-4">
    <!-- Statistics Row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-primary">
                        <i class="fas fa-tags"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $totalCategories; ?></div>
                        <div class="stats-label">Total Categories</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-info">
                        <i class="fas fa-tools"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $totalEquipment; ?></div>
                        <div class="stats-label">Total Equipment</div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="stats-card bg-white">
                <div class="d-flex align-items-center">
                    <div class="stats-icon bg-success">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div>
                        <div class="stats-number"><?php echo $totalCategories > 0 ? round($totalEquipment / $totalCategories, 1) : 0; ?></div>
                        <div class="stats-label">Avg per Category</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Category Form -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-plus me-2"></i>Add New Category
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="add">
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required 
                                   placeholder="e.g., Audio Equipment">
                            <div class="invalid-feedback">Please provide a category name.</div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <input type="text" class="form-control" id="description" name="description" 
                                   placeholder="Brief description of this category">
                        </div>
                    </div>
                    
                    <div class="col-md-2 d-flex align-items-end">
                        <div class="mb-3 w-100">
                            <button type="submit" class="btn btn-church-primary w-100">
                                <i class="fas fa-plus me-1"></i>Add Category
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Categories List -->
    <div class="card">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list me-2"></i>Equipment Categories
                </h5>
                
                <div class="btn-group">
                    <button class="btn btn-sm btn-outline-light" onclick="resetDefaultCategories()">
                        <i class="fas fa-refresh me-1"></i>Reset Defaults
                    </button>
                </div>
            </div>
        </div>
        
        <div class="card-body p-0">
            <?php if (!empty($categories)): ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Category Name</th>
                                <th>Description</th>
                                <th class="text-center">Equipment Count</th>
                                <th class="text-center">Created</th>
                                <th class="text-center no-print">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categories as $category): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="category-icon bg-church-blue text-white rounded-circle me-2 d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                                                <i class="fas fa-tag"></i>
                                            </div>
                                            <strong><?php echo htmlspecialchars($category['name']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if (!empty($category['description'])): ?>
                                            <?php echo htmlspecialchars($category['description']); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No description</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($category['equipment_count'] > 0): ?>
                                            <a href="<?php echo BASE_URL; ?>modules/equipment/?category=<?php echo $category['id']; ?>" 
                                               class="badge bg-primary text-decoration-none">
                                                <?php echo $category['equipment_count']; ?> items
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">0 items</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <small class="text-muted">
                                            <?php echo formatDisplayDate($category['created_at']); ?>
                                        </small>
                                    </td>
                                    <td class="text-center no-print">
                                        <div class="btn-group btn-group-sm">
                                            <button type="button" class="btn btn-outline-primary" 
                                                    onclick="editCategory(<?php echo htmlspecialchars(json_encode($category)); ?>)" 
                                                    title="Edit Category">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if ($category['equipment_count'] == 0): ?>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>')" 
                                                    title="Delete Category">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php else: ?>
                                            <button type="button" class="btn btn-outline-secondary" disabled 
                                                    title="Cannot delete - category has equipment assigned">
                                                <i class="fas fa-lock"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No Categories Found</h5>
                    <p class="text-muted">Create your first equipment category to organize your inventory.</p>
                    <button type="button" class="btn btn-church-primary" onclick="resetDefaultCategories()">
                        <i class="fas fa-plus me-2"></i>Create Default Categories
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" class="needs-validation" novalidate>
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" id="edit_id">
                
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Category
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="edit_name" class="form-label">Category Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="edit_name" name="name" required>
                        <div class="invalid-feedback">Please provide a category name.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="edit_description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-save me-1"></i>Update Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Edit category function
function editCategory(category) {
    document.getElementById('edit_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_description').value = category.description || '';
    
    const modal = new bootstrap.Modal(document.getElementById('editCategoryModal'));
    modal.show();
}

// Delete category function
function deleteCategory(id, name) {
    ChurchCMS.showConfirm(
        `Are you sure you want to delete the category "${name}"?`,
        function() {
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="${id}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
    );
}

// Reset default categories
function resetDefaultCategories() {
    ChurchCMS.showConfirm(
        'This will add all default equipment categories. Existing categories will not be affected. Continue?',
        function() {
            ChurchCMS.showLoading('Creating default categories...');
            
            const defaultCategories = [
                {name: 'Audio Equipment', description: 'Microphones, speakers, mixers, amplifiers'},
                {name: 'Visual Equipment', description: 'Projectors, screens, cameras, lighting'},
                {name: 'Musical Instruments', description: 'Keyboards, guitars, drums, traditional instruments'},
                {name: 'Furniture & Fixtures', description: 'Chairs, tables, podiums, altar furniture'},
                {name: 'Office Equipment', description: 'Computers, printers, phones, stationery'},
                {name: 'Maintenance Tools', description: 'Tools for building and equipment maintenance'},
                {name: 'Kitchen Equipment', description: 'Cooking and serving equipment'},
                {name: 'Cleaning Equipment', description: 'Cleaning supplies and tools'}
            ];
            
            let created = 0;
            let total = defaultCategories.length;
            
            defaultCategories.forEach((category, index) => {
                const form = new FormData();
                form.append('action', 'add');
                form.append('name', category.name);
                form.append('description', category.description);
                
                fetch(window.location.href, {
                    method: 'POST',
                    body: form
                })
                .then(() => {
                    created++;
                    if (created === total) {
                        ChurchCMS.hideLoading();
                        ChurchCMS.showToast('Default categories created successfully!', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    }
                })
                .catch(error => {
                    console.error('Error creating category:', error);
                    if (created === total - 1) {
                        ChurchCMS.hideLoading();
                        ChurchCMS.showToast('Some categories may not have been created', 'warning');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    }
                });
            });
        }
    );
}

// Form validation
document.addEventListener('DOMContentLoaded', function() {
    // Validate all forms with needs-validation class
    const forms = document.querySelectorAll('.needs-validation');
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
    
    // Auto-focus on category name input
    const nameInput = document.getElementById('name');
    if (nameInput) {
        nameInput.focus();
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Auto-suggest category names
const commonCategories = [
    'Audio Equipment', 'Visual Equipment', 'Musical Instruments', 
    'Furniture', 'Office Equipment', 'Kitchen Equipment',
    'Cleaning Equipment', 'Maintenance Tools', 'Security Equipment',
    'Computer Equipment', 'Networking Equipment', 'Vehicles'
];

document.getElementById('name').addEventListener('input', function() {
    const value = this.value.toLowerCase();
    
    if (value.length > 2) {
        const suggestions = commonCategories.filter(cat => 
            cat.toLowerCase().includes(value) && cat.toLowerCase() !== value
        );
        
        if (suggestions.length > 0) {
            // You could implement a dropdown suggestion list here
            console.log('Suggestions:', suggestions);
        }
    }
});

// Show equipment count details on hover
document.querySelectorAll('.badge[data-equipment-count]').forEach(badge => {
    badge.addEventListener('mouseenter', function() {
        // Could show a tooltip with equipment details
    });
});
</script>

<?php include '../../includes/footer.php'; ?>