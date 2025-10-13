<?php
/**
 * SMS/Communication Module Dashboard
 * Deliverance Church Management System
 * 
 * Main dashboard for SMS, WhatsApp, and Email communication
 */

// Include necessary files
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('sms') && !in_array($_SESSION['user_role'], ['administrator', 'pastor', 'secretary'])) {
    setFlashMessage('error', 'You do not have permission to access this module.');
    redirect(BASE_URL . 'modules/dashboard/');
}

// Page settings
$page_title = 'Communication Center';
$page_icon = 'fas fa-comments';
$breadcrumb = [
    ['title' => 'Communication Center']
];

// Get communication statistics
try {
    $db = Database::getInstance();
    
    // SMS Statistics
    $smsStats = [
        'total_sent' => $db->executeQuery("SELECT COUNT(*) as count FROM sms_individual WHERE status = 'sent'")->fetchColumn(),
        'pending' => $db->executeQuery("SELECT COUNT(*) as count FROM sms_individual WHERE status = 'pending'")->fetchColumn(),
        'failed' => $db->executeQuery("SELECT COUNT(*) as count FROM sms_individual WHERE status = 'failed'")->fetchColumn(),
        'this_month' => $db->executeQuery("SELECT COUNT(*) as count FROM sms_individual WHERE status = 'sent' AND MONTH(sent_at) = MONTH(NOW()) AND YEAR(sent_at) = YEAR(NOW())")->fetchColumn()
    ];
    
    // Email Statistics (placeholder for future implementation)
    $emailStats = [
        'total_sent' => 0,
        'pending' => 0,
        'failed' => 0,
        'this_month' => 0
    ];
    
    // WhatsApp Statistics (placeholder for future implementation)
    $whatsappStats = [
        'total_sent' => 0,
        'pending' => 0,
        'failed' => 0,
        'this_month' => 0
    ];
    
    // Get recent communication history
    $recentCommunications = $db->executeQuery("
        SELECT 
            sh.id,
            sh.recipient_type,
            sh.message,
            sh.total_recipients,
            sh.sent_count,
            sh.failed_count,
            sh.cost,
            sh.status,
            sh.sent_at,
            CONCAT(u.first_name, ' ', u.last_name) as sent_by_name,
            'SMS' as communication_type
        FROM sms_history sh
        LEFT JOIN users u ON sh.sent_by = u.id
        ORDER BY sh.sent_at DESC
        LIMIT 10
    ")->fetchAll();
    
    // Get SMS balance from church_info
    $churchInfo = $db->executeQuery("SELECT sms_balance FROM church_info WHERE id = 1")->fetch();
    $smsBalance = $churchInfo['sms_balance'] ?? 0;
    
    // Get templates count
    $templatesCount = $db->executeQuery("SELECT COUNT(*) as count FROM sms_templates WHERE is_active = 1")->fetchColumn();
    
} catch (Exception $e) {
    error_log("Error fetching communication stats: " . $e->getMessage());
    setFlashMessage('error', 'Error loading communication data.');
}

// Include header
include_once '../../includes/header.php';
?>

<div class="row mb-4">
    <!-- SMS Card -->
    <div class="col-md-4 mb-3">
        <div class="card border-left-primary shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-church-blue text-uppercase mb-1">
                            SMS Communication
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($smsStats['total_sent']); ?> Sent
                        </div>
                        <div class="small text-muted mt-1">
                            <?php echo number_format($smsStats['this_month']); ?> this month
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-sms fa-2x text-church-blue"></i>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <div class="small">
                            <span class="text-success">
                                <i class="fas fa-check-circle me-1"></i>
                                <?php echo number_format($smsStats['total_sent']); ?> Sent
                            </span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="small">
                            <span class="text-warning">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo number_format($smsStats['pending']); ?> Pending
                            </span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="small">
                            <span class="text-danger">
                                <i class="fas fa-times-circle me-1"></i>
                                <?php echo number_format($smsStats['failed']); ?> Failed
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Email Card -->
    <div class="col-md-4 mb-3">
        <div class="card border-left-success shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Email Communication
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($emailStats['total_sent']); ?> Sent
                        </div>
                        <div class="small text-muted mt-1">
                            <?php echo number_format($emailStats['this_month']); ?> this month
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-envelope fa-2x text-success"></i>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <div class="small text-info">
                            <i class="fas fa-info-circle me-1"></i>
                            Email integration coming soon!
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- WhatsApp Card -->
    <div class="col-md-4 mb-3">
        <div class="card border-left-success shadow h-100">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            WhatsApp Communication
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                            <?php echo number_format($whatsappStats['total_sent']); ?> Sent
                        </div>
                        <div class="small text-muted mt-1">
                            <?php echo number_format($whatsappStats['this_month']); ?> this month
                        </div>
                    </div>
                    <div class="col-auto">
                        <i class="fab fa-whatsapp fa-2x text-success"></i>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col">
                        <div class="small">
                            <span class="text-success">
                                <i class="fas fa-check-circle me-1"></i>
                                <?php echo number_format($whatsappStats['total_sent']); ?> Sent
                            </span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="small">
                            <span class="text-warning">
                                <i class="fas fa-clock me-1"></i>
                                <?php echo number_format($whatsappStats['pending']); ?> Pending
                            </span>
                        </div>
                    </div>
                    <div class="col">
                        <div class="small">
                            <span class="text-danger">
                                <i class="fas fa-times-circle me-1"></i>
                                <?php echo number_format($whatsappStats['failed']); ?> Failed
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row mb-4">
    <!-- Quick Actions -->
    <div class="col-md-8">
        <div class="card shadow">
            <div class="card-header bg-church-blue text-white">
                <h6 class="mb-0">
                    <i class="fas fa-bolt me-2"></i>Quick Communication Actions
                </h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <a href="send.php" class="btn btn-church-primary btn-lg w-100">
                            <i class="fas fa-sms me-2"></i>Send SMS
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="send_email.php" class="btn btn-success btn-lg w-100 disabled">
                            <i class="fas fa-envelope me-2"></i>Send Email
                            <small class="d-block">Coming Soon</small>
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="send_whatsapp.php" class="btn btn-success btn-lg w-100 disabled">
                            <i class="fab fa-whatsapp me-2"></i>Send WhatsApp
                            <small class="d-block">Coming Soon</small>
                        </a>
                    </div>
                    <div class="col-md-6 mb-3">
                        <a href="templates.php" class="btn btn-info btn-lg w-100">
                            <i class="fas fa-clipboard-list me-2"></i>Manage Templates
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- SMS Balance & Info -->
    <div class="col-md-4">
        <div class="card shadow">
            <div class="card-header bg-church-red text-white">
                <h6 class="mb-0">
                    <i class="fas fa-wallet me-2"></i>SMS Balance
                </h6>
            </div>
            <div class="card-body text-center">
                <div class="display-6 text-church-red mb-2">
                    <?php echo formatCurrency($smsBalance); ?>
                </div>
                <p class="text-muted mb-3">Available SMS Balance</p>
                <div class="row">
                    <div class="col-6">
                        <div class="small">
                            <div class="fw-bold"><?php echo number_format($templatesCount); ?></div>
                            <div class="text-muted">Templates</div>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="small">
                            <div class="fw-bold"><?php echo SMS_COST_PER_SMS; ?></div>
                            <div class="text-muted">Cost/SMS</div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="#" class="btn btn-sm btn-church-primary" data-bs-toggle="modal" data-bs-target="#topupModal">
                        <i class="fas fa-plus me-1"></i>Top Up Balance
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Communications -->
<div class="card shadow">
    <div class="card-header bg-church-blue text-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0">
            <i class="fas fa-history me-2"></i>Recent Communications
        </h6>
        <a href="history.php" class="btn btn-sm btn-outline-light">View All</a>
    </div>
    <div class="card-body">
        <?php if (empty($recentCommunications)): ?>
            <div class="text-center py-4">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <h5 class="text-muted">No Communications Yet</h5>
                <p class="text-muted">Start by sending your first message to church members.</p>
                <a href="send.php" class="btn btn-church-primary">
                    <i class="fas fa-sms me-1"></i>Send First Message
                </a>
            </div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Type</th>
                            <th>Recipients</th>
                            <th>Message</th>
                            <th>Status</th>
                            <th>Sent By</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentCommunications as $comm): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-primary">
                                        <i class="fas fa-sms me-1"></i><?php echo $comm['communication_type']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo ucfirst(str_replace('_', ' ', $comm['recipient_type'])); ?></div>
                                    <small class="text-muted">
                                        <?php echo $comm['sent_count']; ?>/<?php echo $comm['total_recipients']; ?> sent
                                        <?php if ($comm['failed_count'] > 0): ?>
                                            <span class="text-danger">(<?php echo $comm['failed_count']; ?> failed)</span>
                                        <?php endif; ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="text-truncate" style="max-width: 200px;">
                                        <?php echo htmlspecialchars($comm['message']); ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusIcon = '';
                                    switch ($comm['status']) {
                                        case 'completed':
                                            $statusClass = 'bg-success';
                                            $statusIcon = 'fa-check-circle';
                                            break;
                                        case 'sending':
                                            $statusClass = 'bg-warning';
                                            $statusIcon = 'fa-clock';
                                            break;
                                        case 'failed':
                                            $statusClass = 'bg-danger';
                                            $statusIcon = 'fa-times-circle';
                                            break;
                                        default:
                                            $statusClass = 'bg-secondary';
                                            $statusIcon = 'fa-question';
                                    }
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <i class="fas <?php echo $statusIcon; ?> me-1"></i>
                                        <?php echo ucfirst($comm['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($comm['sent_by_name']); ?></td>
                                <td>
                                    <div class="small">
                                        <?php echo formatDisplayDateTime($comm['sent_at']); ?>
                                    </div>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="view_communication.php?id=<?php echo $comm['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <?php if ($comm['status'] === 'failed'): ?>
                                            <a href="resend.php?id=<?php echo $comm['id']; ?>" 
                                               class="btn btn-outline-warning btn-sm" title="Resend">
                                                <i class="fas fa-redo"></i>
                                            </a>
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

<!-- SMS Top-up Modal -->
<div class="modal fade" id="topupModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-church-primary text-white">
                <h5 class="modal-title">
                    <i class="fas fa-plus-circle me-2"></i>Top Up SMS Balance
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="topup_balance.php">
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Current Balance: <strong><?php echo formatCurrency($smsBalance); ?></strong>
                    </div>
                    
                    <div class="mb-3">
                        <label for="topup_amount" class="form-label">Top-up Amount</label>
                        <div class="input-group">
                            <span class="input-group-text">Ksh</span>
                            <input type="number" class="form-control" id="topup_amount" name="amount" 
                                   min="100" max="50000" step="50" required>
                        </div>
                        <div class="form-text">Minimum: Ksh 100, Maximum: Ksh 50,000</div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="payment_method" class="form-label">Payment Method</label>
                        <select class="form-select" id="payment_method" name="payment_method" required>
                            <option value="">Select Payment Method</option>
                            <option value="mpesa">M-Pesa</option>
                            <option value="bank_transfer">Bank Transfer</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="reference" class="form-label">Reference Number</label>
                        <input type="text" class="form-control" id="reference" name="reference" 
                               placeholder="M-Pesa code or bank reference">
                        <div class="form-text">Enter transaction reference for tracking</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-church-primary">
                        <i class="fas fa-credit-card me-1"></i>Process Top-up
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh communication stats every 30 seconds
    setInterval(function() {
        // Only refresh if page is visible
        if (!document.hidden) {
            fetch('ajax/get_stats.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update SMS stats
                        document.querySelector('.sms-total').textContent = data.sms.total_sent.toLocaleString();
                        document.querySelector('.sms-month').textContent = data.sms.this_month.toLocaleString();
                        document.querySelector('.sms-pending').textContent = data.sms.pending.toLocaleString();
                        document.querySelector('.sms-failed').textContent = data.sms.failed.toLocaleString();
                        
                        // Update balance
                        document.querySelector('.sms-balance').textContent = 'Ksh ' + parseFloat(data.balance).toLocaleString();
                    }
                })
                .catch(error => {
                    console.error('Error refreshing stats:', error);
                });
        }
    }, 30000);
    
    // Estimate SMS count and cost
    const amountInput = document.getElementById('topup_amount');
    if (amountInput) {
        amountInput.addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const smsCount = Math.floor(amount / <?php echo SMS_COST_PER_SMS; ?>);
            const helpText = this.parentNode.nextElementSibling;
            
            if (amount > 0) {
                helpText.innerHTML = `This will add approximately <strong>${smsCount.toLocaleString()}</strong> SMS credits`;
            } else {
                helpText.innerHTML = 'Minimum: Ksh 100, Maximum: Ksh 50,000';
            }
        });
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?>