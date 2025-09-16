<?php foreach ($expenseRecords as $expense): ?>
                                <tr data-id="<?php echo $expense['id']; ?>">
                                    <td>
                                        <input type="checkbox" class="form-check-input row-checkbox" 
                                               value="<?php echo $expense['id']; ?>">
                                    </td>
                                    <td><?php echo formatDisplayDate($expense['expense_date']); ?></td>
                                    <td>
                                        <code class="text-danger"><?php echo htmlspecialchars($expense['transaction_id']); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark">
                                            <?php echo htmlspecialchars($expense['category_name']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($expense['vendor_name'])): ?>
                                            <div class="fw-medium"><?php echo htmlspecialchars($expense['vendor_name']); ?></div>
                                            <?php if (!empty($expense['vendor_contact'])): ?>
                                                <small class="text-muted"><?php echo htmlspecialchars($expense['vendor_contact']); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Not specified</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-danger">
                                        <?php echo formatCurrency($expense['amount']); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">
                                            <?php echo getPaymentMethodDisplay($expense['payment_method']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'paid' => 'success',
                                            'approved' => 'primary',
                                            'pending' => 'warning',
                                            'rejected' => 'danger'
                                        ];
                                        $statusColor = $statusColors[$expense['status']] ?? 'secondary';
                                        ?>
                                        <span class="status-badge status-<?php echo $expense['status']; ?>">
                                            <?php echo ucfirst($expense['status']); ?>
                                        </span>
                                        
                                        <!-- Status workflow indicators -->
                                        <div class="mt-1">
                                            <?php if ($expense['status'] === 'pending'): ?>
                                                <small class="text-muted">
                                                    <i class="fas fa-clock me-1"></i>Awaiting approval
                                                </small>
                                            <?php elseif ($expense['status'] === 'approved'): ?>
                                                <small class="text-primary">
                                                    <i class="fas fa-check me-1"></i>Approved by 
                                                    <?php echo htmlspecialchars($expense['approved_by_name'] . ' ' . $expense['approved_by_lastname']); ?>
                                                </small>
                                            <?php elseif ($expense['status'] === 'paid'): ?>
                                                <small class="text-success">
                                                    <i class="fas fa-credit-card me-1"></i>Paid on 
                                                    <?php echo formatDisplayDate($expense['payment_date']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted">
                                            <?php 
                                            if ($expense['requested_by_name']) {
                                                echo htmlspecialchars($expense['requested_by_name'] . ' ' . $expense['requested_by_lastname']);
                                            } else {
                                                echo 'System';
                                            }
                                            ?>
                                            <br>
                                            <?php echo formatDisplayDate($expense['created_at']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <!-- View Button -->
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    onclick="viewExpense(<?php echo $expense['id']; ?>)" 
                                                    title="View Details">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            
                                            <!-- Edit Button (only for pending/approved records) -->
                                            <?php if (in_array($expense['status'], ['pending', 'approved']) && hasPermission('finance')): ?>
                                                <a href="edit_expense.php?id=<?php echo $expense['id']; ?>" 
                                                   class="btn btn-sm btn-outline-secondary" 
                                                   title="Edit">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Approve Button (only for pending records by admin/pastor) -->
                                            <?php if ($expense['status'] === 'pending' && ($_SESSION['user_role'] === 'administrator' || $_SESSION['user_role'] === 'pastor')): ?>
                                                <button type="button" class="btn btn-sm btn-success" 
                                                        onclick="approveExpense(<?php echo $expense['id']; ?>)" 
                                                        title="Approve">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Mark as Paid Button (only for approved records) -->
                                            <?php if ($expense['status'] === 'approved' && ($_SESSION['user_role'] === 'administrator' || $_SESSION['user_role'] === 'finance_officer')): ?>
                                                <button type="button" class="btn btn-sm btn-primary" 
                                                        onclick="markAsPaid(<?php echo $expense['id']; ?>)" 
                                                        title="Mark as Paid">
                                                    <i class="fas fa-credit-card"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Receipt Button -->
                                            <?php if (!empty($expense['receipt_path']) || !empty($expense['receipt_number'])): ?>
                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                        onclick="viewReceipt(<?php echo $expense['id']; ?>)" 
                                                        title="View Receipt">
                                                    <i class="fas fa-receipt"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <!-- Delete Button (only for administrators and pending records) -->
                                            <?php if ($expense['status'] === 'pending' && $_SESSION['user_role'] === 'administrator'): ?>
                                                <button type="button" class="btn btn-sm btn-outline-danger confirm-delete" 
                                                        onclick="deleteExpense(<?php echo $expense['id']; ?>)" 
                                                        title="Delete">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($pagination['total_pages'] > 1): ?>
                    <div class="d-flex justify-content-between align-items-center mt-4">
                        <div class="text-muted">
                            Showing <?php echo number_format($offset + 1); ?> to 
                            <?php echo number_format(min($offset + $limit, $totalRecords)); ?> of 
                            <?php echo number_format($totalRecords); ?> records
                        </div>
                        
                        <nav aria-label="Expense pagination">
                            <ul class="pagination mb-0">
                                <?php if ($pagination['has_previous']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['previous_page']])); ?>">
                                            <i class="fas fa-chevron-left"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                                
                                <?php
                                $start_page = max(1, $pagination['current_page'] - 2);
                                $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++):
                                ?>
                                    <li class="page-item <?php echo $i === $pagination['current_page'] ? 'active' : ''; ?>">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($pagination['has_next']): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $pagination['next_page']])); ?>">
                                            <i class="fas fa-chevron-right"></i>
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- No Records Found -->
                <div class="text-center py-5">
                    <i class="fas fa-receipt fa-4x text-muted mb-3"></i>
                    <h4 class="text-muted">No Expense Records Found</h4>
                    <?php if (!empty($search) || !empty($status) || !empty($category)): ?>
                        <p class="text-muted">Try adjusting your filters or search criteria.</p>
                        <a href="expenses.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Clear Filters
                        </a>
                    <?php else: ?>
                        <p class="text-muted">Start by recording your first expense transaction.</p>
                        <a href="add_expense.php" class="btn btn-danger">
                            <i class="fas fa-plus me-1"></i>Add First Expense Record
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- View Expense Modal -->
<div class="modal fade" id="viewExpenseModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-church-blue text-white">
                <h5 class="modal-title">
                    <i class="fas fa-eye me-2"></i>Expense Details
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="expenseDetailsContent">
                <!-- Content loaded via AJAX -->
                <div class="text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-church-primary" onclick="printExpenseDetails()">
                    <i class="fas fa-print me-1"></i>Print
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Mark as Paid Modal -->
<div class="modal fade" id="markPaidModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-credit-card me-2"></i>Mark Expense as Paid
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="markPaidForm">
                <div class="modal-body">
                    <input type="hidden" id="expense_id_paid" name="expense_id">
                    
                    <div class="mb-3">
                        <label for="payment_date" class="form-label">Payment Date</label>
                        <input type="date" class="form-control" id="payment_date" name="payment_date" 
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_reference" class="form-label">Payment Reference</label>
                        <input type="text" class="form-control" id="payment_reference" name="payment_reference" 
                               placeholder="Payment confirmation number">
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_notes" class="form-label">Payment Notes</label>
                        <textarea class="form-control" id="payment_notes" name="payment_notes" rows="2" 
                                  placeholder="Additional notes about the payment"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-credit-card me-1"></i>Mark as Paid
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize bulk actions
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const bulkActions = document.querySelector('.bulk-actions');
    
    selectAllCheckbox?.addEventListener('change', function() {
        rowCheckboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
        });
        toggleBulkActions();
    });
    
    rowCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', toggleBulkActions);
    });
    
    function toggleBulkActions() {
        const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkedBoxes.length > 0) {
            bulkActions?.classList.remove('d-none');
        } else {
            bulkActions?.classList.add('d-none');
        }
        
        // Update select all checkbox state
        if (selectAllCheckbox) {
            selectAllCheckbox.checked = checkedBoxes.length === rowCheckboxes.length;
            selectAllCheckbox.indeterminate = checkedBoxes.length > 0 && checkedBoxes.length < rowCheckboxes.length;
        }
    }
    
    // Mark as Paid form submission
    const markPaidForm = document.getElementById('markPaidForm');
    markPaidForm?.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const expenseId = formData.get('expense_id');
        
        ChurchCMS.showLoading('Marking expense as paid...');
        
        fetch('ajax/mark_expense_paid.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            ChurchCMS.hideLoading();
            
            if (data.success) {
                ChurchCMS.showToast('Expense marked as paid successfully!', 'success');
                bootstrap.Modal.getInstance(document.getElementById('markPaidModal')).hide();
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                ChurchCMS.showToast(data.message || 'Error marking expense as paid', 'error');
            }
        })
        .catch(error => {
            ChurchCMS.hideLoading();
            console.error('Error marking expense as paid:', error);
            ChurchCMS.showToast('Error marking expense as paid', 'error');
        });
    });
});

// View expense details
function viewExpense(expenseId) {
    const modal = new bootstrap.Modal(document.getElementById('viewExpenseModal'));
    const content = document.getElementById('expenseDetailsContent');
    
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // Load expense details via AJAX
    fetch(`ajax/get_expense_details.php?id=${expenseId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.html;
            } else {
                content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error loading expense details:', error);
            content.innerHTML = '<div class="alert alert-danger">Error loading expense details.</div>';
        });
}

// Approve expense record
function approveExpense(expenseId) {
    ChurchCMS.showConfirm(
        'Are you sure you want to approve this expense? Once approved, it can be processed for payment.',
        function() {
            ChurchCMS.showLoading('Approving expense...');
            
            fetch('ajax/approve_expense.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    expense_id: expenseId,
                    action: 'approve'
                })
            })
            .then(response => response.json())
            .then(data => {
                ChurchCMS.hideLoading();
                
                if (data.success) {
                    ChurchCMS.showToast('Expense approved successfully!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    ChurchCMS.showToast(data.message || 'Error approving expense', 'error');
                }
            })
            .catch(error => {
                ChurchCMS.hideLoading();
                console.error('Error approving expense:', error);
                ChurchCMS.showToast('Error approving expense', 'error');
            });
        },
        null,
        'Approve Expense'
    );
}

// Mark expense as paid
function markAsPaid(expenseId) {
    document.getElementById('expense_id_paid').value = expenseId;
    const modal = new bootstrap.Modal(document.getElementById('markPaidModal'));
    modal.show();
}

// Delete expense record
function deleteExpense(expenseId) {
    ChurchCMS.showConfirm(
        'Are you sure you want to delete this expense record? This action cannot be undone.',
        function() {
            ChurchCMS.showLoading('Deleting expense record...');
            
            fetch('ajax/delete_expense.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    expense_id: expenseId
                })
            })
            .then(response => response.json())
            .then(data => {
                ChurchCMS.hideLoading();
                
                if (data.success) {
                    ChurchCMS.showToast('Expense record deleted successfully!', 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    ChurchCMS.showToast(data.message || 'Error deleting expense record', 'error');
                }
            })
            .catch(error => {
                ChurchCMS.hideLoading();
                console.error('Error deleting expense:', error);
                ChurchCMS.showToast('Error deleting expense record', 'error');
            });
        },
        null,
        'Delete Expense Record'
    );
}

// Bulk approve selected records
function bulkApprove() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        ChurchCMS.showToast('Please select at least one record to approve', 'warning');
        return;
    }
    
    const expenseIds = Array.from(checkedBoxes).map(cb => cb.value);
    
    ChurchCMS.showConfirm(
        `Are you sure you want to approve ${expenseIds.length} selected expense record(s)?`,
        function() {
            ChurchCMS.showLoading('Approving selected records...');
            
            fetch('ajax/bulk_approve_expense.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    expense_ids: expenseIds
                })
            })
            .then(response => response.json())
            .then(data => {
                ChurchCMS.hideLoading();
                
                if (data.success) {
                    ChurchCMS.showToast(`${data.approved_count} record(s) approved successfully!`, 'success');
                    setTimeout(() => {
                        location.reload();
                    }, 1500);
                } else {
                    ChurchCMS.showToast(data.message || 'Error approving records', 'error');
                }
            })
            .catch(error => {
                ChurchCMS.hideLoading();
                console.error('Error bulk approving:', error);
                ChurchCMS.showToast('Error approving selected records', 'error');
            });
        },
        null,
        'Bulk Approve Expenses'
    );
}

// Bulk mark as paid
function bulkMarkPaid() {
    const checkedBoxes = document.querySelectorAll('.row-checkbox:checked');
    if (checkedBoxes.length === 0) {
        ChurchCMS.showToast('Please select at least one approved record to mark as paid', 'warning');
        return;
    }
    
    const expenseIds = Array.from(checkedBoxes).map(cb => cb.value);
    
    ChurchCMS.showConfirm(
        `Are you sure you want to mark ${expenseIds.length} selected expense record(s) as paid?`,
        function() {
            // Show bulk payment form
            const modalHtml = `
                <div class="modal fade" id="bulkPaymentModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-primary text-white">
                                <h5 class="modal-title">Bulk Mark as Paid</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <form id="bulkPaymentForm">
                                <div class="modal-body">
                                    <div class="mb-3">
                                        <label class="form-label">Payment Date</label>
                                        <input type="date" class="form-control" name="payment_date" value="${new Date().toISOString().split('T')[0]}" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Payment Reference</label>
                                        <input type="text" class="form-control" name="payment_reference" placeholder="Batch payment reference">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Notes</label>
                                        <textarea class="form-control" name="payment_notes" rows="2" placeholder="Bulk payment notes"></textarea>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-primary">Mark as Paid</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('bulkPaymentModal'));
            
            document.getElementById('bulkPaymentForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                formData.append('expense_ids', JSON.stringify(expenseIds));
                
                ChurchCMS.showLoading('Processing payments...');
                
                fetch('ajax/bulk_mark_paid.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    ChurchCMS.hideLoading();
                    modal.hide();
                    
                    if (data.success) {
                        ChurchCMS.showToast(`${data.paid_count} record(s) marked as paid successfully!`, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        ChurchCMS.showToast(data.message || 'Error processing payments', 'error');
                    }
                })
                .catch(error => {
                    ChurchCMS.hideLoading();
                    console.error('Error bulk marking as paid:', error);
                    ChurchCMS.showToast('Error processing payments', 'error');
                });
            });
            
            modal.show();
            
            // Clean up modal after hiding
            document.getElementById('bulkPaymentModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        },
        null,
        'Bulk Mark as Paid'
    );
}

// Export data function
function exportData(format) {
    const urlParams = new URLSearchParams(window.location.search);
    urlParams.set('export', format);
    
    ChurchCMS.showLoading('Preparing export...');
    
    const link = document.createElement('a');
    link.href = 'export_expenses.php?' + urlParams.toString();
    link.download = `expense_records_${new Date().toISOString().split('T')[0]}.${format}`;
    
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    setTimeout(() => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Export completed successfully!', 'success');
    }, 2000);
}

// Print expense details
function printExpenseDetails() {
    const printContent = document.getElementById('expenseDetailsContent').innerHTML;
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <html>
            <head>
                <title>Expense Details - <?php echo htmlspecialchars($churchInfo['church_name']); ?></title>
                <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    body { font-family: 'Segoe UI', sans-serif; }
                    .church-header { background: <?php echo CHURCH_BLUE; ?>; color: white; padding: 20px; text-align: center; }
                    @media print {
                        .no-print { display: none !important; }
                        body { background: white !important; }
                    }
                </style>
            </head>
            <body>
                <div class="church-header">
                    <h3><?php echo htmlspecialchars($churchInfo['church_name']); ?></h3>
                    <p>Expense Transaction Details</p>
                </div>
                <div class="container mt-4">
                    ${printContent}
                </div>
            </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.print();
}

// View receipt function
function viewReceipt(expenseId) {
    const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
    const content = document.getElementById('receiptContent');
    
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    fetch(`ajax/get_receipt.php?id=${expenseId}&type=expense`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.html;
            } else {
                content.innerHTML = `<div class="alert alert-danger">${data.message}</div>`;
            }
        })
        .catch(error => {
            console.error('Error loading receipt:', error);
            content.innerHTML = '<div class="alert alert-danger">Error loading receipt.</div>';
        });
}

// Real-time search
const searchInput = document.getElementById('search');
if (searchInput) {
    searchInput.addEventListener('input', ChurchCMS.debounce(function() {
        if (this.value.length >= 3 || this.value.length === 0) {
            document.querySelector('form[method="GET"]').submit();
        }
    }, 800));
}

// Status color coding for table
document.getElementById('status')?.addEventListener('change', function() {
    const table = document.getElementById('expenseTable');
    if (table) {
        table.className = 'table table-hover';
        
        if (this.value === 'paid') {
            table.classList.add('table-success');
        } else if (this.value === 'approved') {
            table.classList.add('table-primary');
        } else if (this.value === 'pending') {
            table.classList.add('table-warning');
        } else if (this.value === 'rejected') {
            table.classList.add('table-danger');
        }
    }
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    if ((e.ctrlKey || e.metaKey) && e.key === 'n') {
        e.preventDefault();
        window.location.href = 'add_expense.php';
    }
    
    if ((e.ctrlKey || e.metaKey) && e.key === 'f') {
        e.preventDefault();
        document.getElementById('search')?.focus();
    }
});

// Double-click to view details
document.querySelectorAll('#expenseTable tbody tr').forEach(row => {
    row.addEventListener('dblclick', function() {
        const expenseId = this.dataset.id;
        if (expenseId) {
            viewExpense(expenseId);
        }
    });
});
</script>

<?php
// Include footer
include '../../includes/footer.php';
?><?php
/**
 * Expense Management
 * Deliverance Church Management System
 * 
 * View, search, and manage all expense records
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('finance')) {
    setFlashMessage('error', 'Access denied. You do not have permission to view financial data.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page configuration
$page_title = 'Expense Management';
$page_icon = 'fas fa-minus-circle';
$page_description = 'View and manage all church expense records';

$breadcrumb = [
    ['title' => 'Finance', 'url' => 'index.php'],
    ['title' => 'Expense Management']
];

$page_actions = [
    [
        'title' => 'Add Expense',
        'url' => 'add_expense.php',
        'icon' => 'fas fa-plus',
        'class' => 'btn-danger'
    ],
    [
        'title' => 'Import Expenses',
        'url' => 'import_expenses.php',
        'icon' => 'fas fa-upload',
        'class' => 'btn-info'
    ],
    [
        'title' => 'Export Expenses',
        'url' => '#',
        'icon' => 'fas fa-download',
        'class' => 'btn-secondary',
        'onclick' => 'exportExpenseData()'
    ]
];

// Get filter parameters
$status = $_GET['status'] ?? '';
$category = $_GET['category'] ?? '';
$payment_method = $_GET['payment_method'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';
$search = $_GET['search'] ?? '';

// Pagination
$page = (int) ($_GET['page'] ?? 1);
$limit = (int) ($_GET['limit'] ?? DEFAULT_PAGE_SIZE);
$offset = ($page - 1) * $limit;

try {
    $db = Database::getInstance();
    
    // Build query conditions
    $conditions = [];
    $params = [];
    
    if (!empty($status)) {
        $conditions[] = "e.status = ?";
        $params[] = $status;
    }
    
    if (!empty($category)) {
        $conditions[] = "e.category_id = ?";
        $params[] = $category;
    }
    
    if (!empty($payment_method)) {
        $conditions[] = "e.payment_method = ?";
        $params[] = $payment_method;
    }
    
    if (!empty($start_date)) {
        $conditions[] = "e.expense_date >= ?";
        $params[] = $start_date;
    }
    
    if (!empty($end_date)) {
        $conditions[] = "e.expense_date <= ?";
        $params[] = $end_date;
    }
    
    if (!empty($search)) {
        $conditions[] = "(e.transaction_id LIKE ? OR e.vendor_name LIKE ? OR e.description LIKE ? OR e.reference_number LIKE ?)";
        $searchTerm = "%{$search}%";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
    
    $whereClause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";
    
    // Get total count for pagination
    $countQuery = "
        SELECT COUNT(*) as total
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        {$whereClause}
    ";
    $stmt = $db->executeQuery($countQuery, $params);
    $totalRecords = $stmt->fetchColumn();
    
    // Get expense records
    $query = "
        SELECT e.*, ec.name as category_name,
               u1.first_name as requested_by_name, u1.last_name as requested_by_lastname,
               u2.first_name as approved_by_name, u2.last_name as approved_by_lastname,
               u3.first_name as paid_by_name, u3.last_name as paid_by_lastname
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        LEFT JOIN users u1 ON e.requested_by = u1.id
        LEFT JOIN users u2 ON e.approved_by = u2.id
        LEFT JOIN users u3 ON e.paid_by = u3.id
        {$whereClause}
        ORDER BY e.created_at DESC
        LIMIT {$limit} OFFSET {$offset}
    ";
    
    $stmt = $db->executeQuery($query, $params);
    $expenseRecords = $stmt->fetchAll();
    
    // Get pagination data
    $pagination = generatePagination($totalRecords, $page, $limit);
    
    // Get expense categories for filter
    $stmt = $db->executeQuery("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY name");
    $expenseCategories = $stmt->fetchAll();
    
    // Get summary statistics for current filters
    $summaryQuery = "
        SELECT 
            COUNT(*) as total_records,
            COALESCE(SUM(CASE WHEN e.status = 'paid' THEN e.amount ELSE 0 END), 0) as total_paid,
            COALESCE(SUM(CASE WHEN e.status = 'approved' THEN e.amount ELSE 0 END), 0) as total_approved,
            COALESCE(SUM(CASE WHEN e.status = 'pending' THEN e.amount ELSE 0 END), 0) as total_pending,
            COALESCE(SUM(e.amount), 0) as total_amount
        FROM expenses e
        JOIN expense_categories ec ON e.category_id = ec.id
        {$whereClause}
    ";
    $stmt = $db->executeQuery($summaryQuery, $params);
    $summary = $stmt->fetch();
    
} catch (Exception $e) {
    error_log("Expense management error: " . $e->getMessage());
    setFlashMessage('error', 'Error loading expense data. Please try again.');
    $expenseRecords = [];
    $expenseCategories = [];
    $pagination = generatePagination(0, 1, $limit);
    $summary = [
        'total_records' => 0, 
        'total_paid' => 0, 
        'total_approved' => 0, 
        'total_pending' => 0, 
        'total_amount' => 0
    ];
}

// Include header
include '../../includes/header.php';
?>

<!-- Expense Management Content -->
<div class="expense-management">
    <!-- Summary Cards -->
    <div class="row mb-4">
        <div class="col-md-5">
            <div class="stats-card">
                <div class="stats-icon bg-info text-white">
                    <i class="fas fa-list"></i>
                </div>
                <div class="stats-number"><?php echo number_format($summary['total_records']); ?></div>
                <div class="stats-label">Total Records</div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="stats-card">
                <div class="stats-icon bg-success text-white">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stats-number"><?php echo formatCurrency($summary['total_paid']); ?></div>
                <div class="stats-label">Paid Expenses</div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="stats-card">
                <div class="stats-icon bg-primary text-white">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stats-number"><?php echo formatCurrency($summary['total_approved']); ?></div>
                <div class="stats-label">Approved (Unpaid)</div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="stats-card">
                <div class="stats-icon bg-warning text-white">
                    <i class="fas fa-hourglass-half"></i>
                </div>
                <div class="stats-number"><?php echo formatCurrency($summary['total_pending']); ?></div>
                <div class="stats-label">Pending Approval</div>
            </div>
        </div>
        <div class="col-md-5">
            <div class="stats-card">
                <div class="stats-icon bg-danger text-white">
                    <i class="fas fa-money-bill-wave"></i>
                </div>
                <div class="stats-number"><?php echo formatCurrency($summary['total_amount']); ?></div>
                <div class="stats-label">Total Amount</div>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h6 class="mb-0">
                <i class="fas fa-filter me-2"></i>Filter Expense Records
            </h6>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <!-- Search -->
                <div class="col-md-3">
                    <label for="search" class="form-label">Search</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Transaction ID, vendor, description..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <!-- Status Filter -->
                <div class="col-md-2">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <option value="">All Status</option>
                        <?php foreach (['pending', 'approved', 'paid', 'rejected'] as $statusOption): ?>
                            <option value="<?php echo $statusOption; ?>" <?php echo $status === $statusOption ? 'selected' : ''; ?>>
                                <?php echo ucfirst($statusOption); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Category Filter -->
                <div class="col-md-2">
                    <label for="category" class="form-label">Category</label>
                    <select class="form-select" id="category" name="category">
                        <option value="">All Categories</option>
                        <?php foreach ($expenseCategories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Payment Method Filter -->
                <div class="col-md-2">
                    <label for="payment_method" class="form-label">Payment Method</label>
                    <select class="form-select" id="payment_method" name="payment_method">
                        <option value="">All Methods</option>
                        <?php foreach (PAYMENT_METHODS as $key => $label): ?>
                            <option value="<?php echo $key; ?>" <?php echo $payment_method === $key ? 'selected' : ''; ?>>
                                <?php echo $label; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Date Range -->
                <div class="col-md-1">
                    <label for="start_date" class="form-label">From</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" 
                           value="<?php echo htmlspecialchars($start_date); ?>">
                </div>
                
                <div class="col-md-1">
                    <label for="end_date" class="form-label">To</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" 
                           value="<?php echo htmlspecialchars($end_date); ?>">
                </div>
                
                <!-- Actions -->
                <div class="col-md-1 d-flex align-items-end">
                    <div class="btn-group w-100">
                        <button type="submit" class="btn btn-church-primary">
                            <i class="fas fa-search"></i>
                        </button>
                        <a href="expenses.php" class="btn btn-outline-secondary" title="Clear Filters">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Expense Records Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h5 class="mb-0">
                <i class="fas fa-list me-2"></i>Expense Records
                <span class="badge bg-light text-dark ms-2"><?php echo number_format($totalRecords); ?> records</span>
            </h5>
            <div class="btn-group">
                <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-1"></i>Export
                </button>
                <ul class="dropdown-menu">
                    <li><a class="dropdown-item" href="#" onclick="exportData('excel')">
                        <i class="fas fa-file-excel me-2 text-success"></i>Excel
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportData('pdf')">
                        <i class="fas fa-file-pdf me-2 text-danger"></i>PDF
                    </a></li>
                    <li><a class="dropdown-item" href="#" onclick="exportData('csv')">
                        <i class="fas fa-file-csv me-2 text-info"></i>CSV
                    </a></li>
                </ul>
            </div>
        </div>
        
        <div class="card-body">
            <?php if (!empty($expenseRecords)): ?>
                <!-- Bulk Actions -->
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="selectAll">
                        <label class="form-check-label" for="selectAll">
                            Select All
                        </label>
                    </div>
                    <div class="bulk-actions d-none">
                        <div class="btn-group">
                            <?php if ($_SESSION['user_role'] === 'administrator' || $_SESSION['user_role'] === 'pastor'): ?>
                                <button type="button" class="btn btn-sm btn-success" onclick="bulkApprove()">
                                    <i class="fas fa-check me-1"></i>Approve Selected
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" onclick="bulkMarkPaid()">
                                    <i class="fas fa-credit-card me-1"></i>Mark as Paid
                                </button>
                            <?php endif; ?>
                            <?php if ($_SESSION['user_role'] === 'administrator'): ?>
                                <button type="button" class="btn btn-sm btn-danger" onclick="bulkDelete()">
                                    <i class="fas fa-trash me-1"></i>Delete Selected
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Expense Table -->
                <div class="table-responsive">
                    <table class="table table-hover" id="expenseTable">
                        <thead>
                            <tr>
                                <th width="40">
                                    <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                                </th>
                                <th>Date</th>
                                <th>Transaction ID</th>
                                <th>Category</th>
                                <th>Vendor/Payee</th>
                                <th>Amount</th>
                                <th>Payment Method</th>
                                <th>Status</th>
                                <th>Requested By</th>
                                <th width="140">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expenseRecords as $expense): ?>
                                <tr data-id="<?php echo $expense['