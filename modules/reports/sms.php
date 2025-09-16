<?php
/**
 * SMS Reports
 * Deliverance Church Management System
 * 
 * Generate various SMS-related reports and analytics
 */

// Include configuration and functions
require_once '../../config/config.php';
require_once '../../includes/functions.php';

// Check authentication and permissions
requireLogin();
if (!hasPermission('reports')) {
    header('Location: ' . BASE_URL . 'modules/dashboard/?error=access_denied');
    exit();
}

// Get report parameters
$reportType = sanitizeInput($_GET['type'] ?? 'summary');
$period = sanitizeInput($_GET['period'] ?? 'this_month');

// Page configuration
$page_title = 'SMS Reports';
$page_icon = 'fas fa-sms';
$breadcrumb = [
    ['title' => 'Reports', 'url' => 'index.php'],
    ['title' => 'SMS Reports']
];

// Initialize database
$db = Database::getInstance();

// Calculate date range
$dateRanges = [
    'today' => [date('Y-m-d'), date('Y-m-d')],
    'this_week' => [date('Y-m-d', strtotime('monday this week')), date('Y-m-d')],
    'this_month' => [date('Y-m-01'), date('Y-m-d')],
    'last_month' => [date('Y-m-01', strtotime('-1 month')), date('Y-m-t', strtotime('-1 month'))],
    'this_quarter' => [date('Y-m-01', strtotime('first day of this quarter')), date('Y-m-d')],
    'this_year' => [date('Y-01-01'), date('Y-m-d')]
];

list($dateFrom, $dateTo) = $dateRanges[$period] ?? $dateRanges['this_month'];

try {
    // Get SMS statistics
    $smsStats = [
        'total_sent' => $db->executeQuery("
            SELECT COUNT(*) as count 
            FROM sms_individual 
            WHERE status = 'sent' 
            AND sent_at BETWEEN ? AND ?
        ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetch()['count'],
        
        'total_delivered' => $db->executeQuery("
            SELECT COUNT(*) as count 
            FROM sms_individual 
            WHERE status = 'delivered' 
            AND delivered_at BETWEEN ? AND ?
        ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetch()['count'],
        
        'total_failed' => $db->executeQuery("
            SELECT COUNT(*) as count 
            FROM sms_individual 
            WHERE status = 'failed' 
            AND sent_at BETWEEN ? AND ?
        ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetch()['count'],
        
        'total_cost' => $db->executeQuery("
            SELECT COALESCE(SUM(cost), 0) as total 
            FROM sms_individual 
            WHERE status = 'sent' 
            AND sent_at BETWEEN ? AND ?
        ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetch()['total'],
        
        'unique_recipients' => $db->executeQuery("
            SELECT COUNT(DISTINCT recipient_phone) as count 
            FROM sms_individual 
            WHERE status = 'sent' 
            AND sent_at BETWEEN ? AND ?
        ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetch()['count']
    ];
    
    // Get SMS data based on report type
    switch ($reportType) {
        case 'summary':
            // Daily SMS trends
            $dailyTrends = $db->executeQuery("
                SELECT 
                    DATE(sent_at) as date,
                    COUNT(*) as messages_sent,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as messages_delivered,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as messages_failed,
                    SUM(cost) as daily_cost
                FROM sms_individual
                WHERE sent_at BETWEEN ? AND ?
                GROUP BY DATE(sent_at)
                ORDER BY date DESC
                LIMIT 30
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();
            
            // Template usage
            $templateUsage = $db->executeQuery("
                SELECT 
                    st.name as template_name,
                    st.category,
                    COUNT(sh.id) as usage_count,
                    SUM(sh.sent_count) as total_sent
                FROM sms_templates st
                LEFT JOIN sms_history sh ON FIND_IN_SET(st.id, sh.recipient_filter) > 0
                    AND sh.sent_at BETWEEN ? AND ?
                WHERE st.is_active = 1
                GROUP BY st.id
                ORDER BY usage_count DESC
                LIMIT 10
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();
            
            $reportData = [
                'daily_trends' => $dailyTrends,
                'template_usage' => $templateUsage
            ];
            break;
            
        case 'detailed':
            // Detailed SMS history
            $detailedHistory = $db->executeQuery("
                SELECT 
                    sh.*,
                    u.first_name, u.last_name,
                    (SELECT COUNT(*) FROM sms_individual si WHERE si.batch_id = sh.batch_id) as total_messages,
                    (SELECT COUNT(*) FROM sms_individual si WHERE si.batch_id = sh.batch_id AND si.status = 'sent') as sent_messages,
                    (SELECT COUNT(*) FROM sms_individual si WHERE si.batch_id = sh.batch_id AND si.status = 'delivered') as delivered_messages,
                    (SELECT COUNT(*) FROM sms_individual si WHERE si.batch_id = sh.batch_id AND si.status = 'failed') as failed_messages
                FROM sms_history sh
                LEFT JOIN users u ON sh.sent_by = u.id
                WHERE sh.sent_at BETWEEN ? AND ?
                ORDER BY sh.sent_at DESC
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();
            
            $reportData = ['detailed_history' => $detailedHistory];
            break;
            
        case 'recipients':
            // Recipient analysis
            $topRecipients = $db->executeQuery("
                SELECT 
                    si.recipient_phone,
                    si.recipient_name,
                    m.first_name, m.last_name, m.member_number,
                    COUNT(*) as messages_received,
                    SUM(si.cost) as total_cost,
                    MAX(si.sent_at) as last_message_date
                FROM sms_individual si
                LEFT JOIN members m ON si.member_id = m.id
                WHERE si.sent_at BETWEEN ? AND ?
                    AND si.status = 'sent'
                GROUP BY si.recipient_phone
                ORDER BY messages_received DESC
                LIMIT 50
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();
            
            // Recipient type distribution
            $recipientTypes = $db->executeQuery("
                SELECT 
                    sh.recipient_type,
                    COUNT(sh.id) as batch_count,
                    SUM(sh.total_recipients) as total_recipients,
                    SUM(sh.sent_count) as total_sent,
                    SUM(sh.cost) as total_cost
                FROM sms_history sh
                WHERE sh.sent_at BETWEEN ? AND ?
                GROUP BY sh.recipient_type
                ORDER BY total_sent DESC
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();
            
            $reportData = [
                'top_recipients' => $topRecipients,
                'recipient_types' => $recipientTypes
            ];
            break;
            
        case 'costs':
            // Cost analysis
            $monthlyCosts = $db->executeQuery("
                SELECT 
                    DATE_FORMAT(sent_at, '%Y-%m') as month,
                    COUNT(*) as total_messages,
                    SUM(cost) as total_cost,
                    AVG(cost) as avg_cost_per_message
                FROM sms_individual
                WHERE sent_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    AND status = 'sent'
                GROUP BY DATE_FORMAT(sent_at, '%Y-%m')
                ORDER BY month DESC
            ")->fetchAll();
            
            // Cost by category
            $costByCategory = $db->executeQuery("
                SELECT 
                    st.category,
                    COUNT(si.id) as message_count,
                    SUM(si.cost) as total_cost
                FROM sms_individual si
                JOIN sms_history sh ON si.batch_id = sh.batch_id
                LEFT JOIN sms_templates st ON sh.recipient_filter LIKE CONCAT('%template_', st.id, '%')
                WHERE si.sent_at BETWEEN ? AND ?
                    AND si.status = 'sent'
                    AND st.category IS NOT NULL
                GROUP BY st.category
                ORDER BY total_cost DESC
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();
            
            $reportData = [
                'monthly_costs' => $monthlyCosts,
                'cost_by_category' => $costByCategory
            ];
            break;
            
        case 'performance':
            // Delivery performance
            $deliveryRates = $db->executeQuery("
                SELECT 
                    DATE(sent_at) as date,
                    COUNT(*) as total_sent,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
                    ROUND((SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as delivery_rate
                FROM sms_individual
                WHERE sent_at BETWEEN ? AND ?
                GROUP BY DATE(sent_at)
                ORDER BY date DESC
                LIMIT 30
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();
            
            // Failure analysis
            $failureReasons = $db->executeQuery("
                SELECT 
                    error_message,
                    COUNT(*) as failure_count,
                    ROUND((COUNT(*) / (SELECT COUNT(*) FROM sms_individual WHERE status = 'failed' AND sent_at BETWEEN ? AND ?)) * 100, 2) as percentage
                FROM sms_individual
                WHERE status = 'failed' 
                    AND sent_at BETWEEN ? AND ?
                    AND error_message IS NOT NULL
                    AND error_message != ''
                GROUP BY error_message
                ORDER BY failure_count DESC
                LIMIT 10
            ", [$dateFrom . ' 00:00:00', $dateTo . ' 23:59:59', $dateFrom . ' 00:00:00', $dateTo . ' 23:59:59'])->fetchAll();
            
            $reportData = [
                'delivery_rates' => $deliveryRates,
                'failure_reasons' => $failureReasons
            ];
            break;
            
        default:
            $reportData = [];
            break;
    }
    
} catch (Exception $e) {
    error_log("Error generating SMS report: " . $e->getMessage());
    setFlashMessage('error', 'Error generating report: ' . $e->getMessage());
    $smsStats = [];
    $reportData = [];
}

include_once '../../includes/header.php';
?>

<!-- SMS Reports Content -->
<div class="row">
    <!-- Filter Controls -->
    <div class="col-12 mb-4">
        <div class="card">
            <div class="card-body">
                <form method="GET" action="" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Report Type</label>
                        <select name="type" class="form-select" onchange="this.form.submit()">
                            <option value="summary" <?php echo $reportType === 'summary' ? 'selected' : ''; ?>>Summary Overview</option>
                            <option value="detailed" <?php echo $reportType === 'detailed' ? 'selected' : ''; ?>>Detailed History</option>
                            <option value="recipients" <?php echo $reportType === 'recipients' ? 'selected' : ''; ?>>Recipient Analysis</option>
                            <option value="costs" <?php echo $reportType === 'costs' ? 'selected' : ''; ?>>Cost Analysis</option>
                            <option value="performance" <?php echo $reportType === 'performance' ? 'selected' : ''; ?>>Performance Analysis</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4">
                        <label class="form-label">Period</label>
                        <select name="period" class="form-select" onchange="this.form.submit()">
                            <option value="today" <?php echo $period === 'today' ? 'selected' : ''; ?>>Today</option>
                            <option value="this_week" <?php echo $period === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                            <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                            <option value="last_month" <?php echo $period === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                            <option value="this_quarter" <?php echo $period === 'this_quarter' ? 'selected' : ''; ?>>This Quarter</option>
                            <option value="this_year" <?php echo $period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                        </select>
                    </div>
                    
                    <div class="col-md-4 d-flex align-items-end">
                        <button type="button" class="btn btn-outline-primary me-2" onclick="exportReport()">
                            <i class="fas fa-download me-2"></i>Export
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="printReport()">
                            <i class="fas fa-print me-2"></i>Print
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Summary Statistics -->
    <div class="col-12 mb-4">
        <div class="row g-3">
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-primary text-white">
                        <i class="fas fa-paper-plane"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($smsStats['total_sent'] ?? 0); ?></div>
                    <div class="stats-label">Messages Sent</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-success text-white">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($smsStats['total_delivered'] ?? 0); ?></div>
                    <div class="stats-label">Delivered</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-danger text-white">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($smsStats['total_failed'] ?? 0); ?></div>
                    <div class="stats-label">Failed</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-warning text-white">
                        <i class="fas fa-money-bill-wave"></i>
                    </div>
                    <div class="stats-number"><?php echo formatCurrency($smsStats['total_cost'] ?? 0); ?></div>
                    <div class="stats-label">Total Cost</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-info text-white">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo number_format($smsStats['unique_recipients'] ?? 0); ?></div>
                    <div class="stats-label">Unique Recipients</div>
                </div>
            </div>
            
            <div class="col-md-2">
                <div class="stats-card">
                    <div class="stats-icon bg-secondary text-white">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stats-number">
                        <?php 
                        $deliveryRate = 0;
                        if ($smsStats['total_sent'] > 0) {
                            $deliveryRate = round(($smsStats['total_delivered'] / $smsStats['total_sent']) * 100, 1);
                        }
                        echo $deliveryRate . '%';
                        ?>
                    </div>
                    <div class="stats-label">Delivery Rate</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Content -->
    <div class="col-12">
        <?php if ($reportType === 'summary'): ?>
            <!-- Summary Report -->
            <div class="row">
                <!-- Daily Trends Chart -->
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Daily SMS Trends
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="dailyTrendsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Template Usage -->
                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-pie me-2"></i>
                                Template Usage
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['template_usage'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Template</th>
                                                <th class="text-end">Uses</th>
                                                <th class="text-end">Sent</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['template_usage'] as $template): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($template['template_name']); ?></strong>
                                                        <br><small class="text-muted"><?php echo ucwords(str_replace('_', ' ', $template['category'])); ?></small>
                                                    </td>
                                                    <td class="text-end"><?php echo number_format($template['usage_count']); ?></td>
                                                    <td class="text-end"><?php echo number_format($template['total_sent']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle mb-2"></i>
                                    <br>No template usage data available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'detailed'): ?>
            <!-- Detailed History -->
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-history me-2"></i>
                        Detailed SMS History
                    </h6>
                </div>
                <div class="card-body">
                    <?php if (!empty($reportData['detailed_history'])): ?>
                        <div class="table-responsive">
                            <table class="table table-hover data-table">
                                <thead>
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Message Preview</th>
                                        <th>Recipient Type</th>
                                        <th>Total Recipients</th>
                                        <th>Sent</th>
                                        <th>Delivered</th>
                                        <th>Failed</th>
                                        <th>Cost</th>
                                        <th>Sent By</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['detailed_history'] as $sms): ?>
                                        <tr>
                                            <td><?php echo formatDisplayDateTime($sms['sent_at']); ?></td>
                                            <td>
                                                <div style="max-width: 200px;">
                                                    <?php echo htmlspecialchars(truncateText($sms['message'], 80)); ?>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?php echo ucwords(str_replace('_', ' ', $sms['recipient_type'])); ?>
                                                </span>
                                            </td>
                                            <td><?php echo number_format($sms['total_recipients']); ?></td>
                                            <td><?php echo number_format($sms['sent_messages']); ?></td>
                                            <td><span class="text-success"><?php echo number_format($sms['delivered_messages']); ?></span></td>
                                            <td><span class="text-danger"><?php echo number_format($sms['failed_messages']); ?></span></td>
                                            <td><?php echo formatCurrency($sms['cost']); ?></td>
                                            <td><?php echo htmlspecialchars(($sms['first_name'] ?? '') . ' ' . ($sms['last_name'] ?? '')); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'pending' => 'warning',
                                                    'sending' => 'info',
                                                    'completed' => 'success',
                                                    'failed' => 'danger'
                                                ];
                                                $class = $statusClass[$sms['status']] ?? 'secondary';
                                                ?>
                                                <span class="badge bg-<?php echo $class; ?>">
                                                    <?php echo ucfirst($sms['status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-4">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No SMS History</h5>
                            <p class="text-muted">No SMS messages found for the selected period.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        <?php elseif ($reportType === 'recipients'): ?>
            <!-- Recipients Analysis -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-users me-2"></i>
                                Top Recipients
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['top_recipients'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Recipient</th>
                                                <th>Phone</th>
                                                <th class="text-end">Messages</th>
                                                <th class="text-end">Cost</th>
                                                <th>Last Message</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['top_recipients'] as $recipient): ?>
                                                <tr>
                                                    <td>
                                                        <?php if (!empty($recipient['member_number'])): ?>
                                                            <strong><?php echo htmlspecialchars($recipient['first_name'] . ' ' . $recipient['last_name']); ?></strong>
                                                            <br><small class="text-muted">Member: <?php echo htmlspecialchars($recipient['member_number']); ?></small>
                                                        <?php else: ?>
                                                            <?php echo htmlspecialchars($recipient['recipient_name'] ?: 'Unknown'); ?>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($recipient['recipient_phone']); ?></td>
                                                    <td class="text-end"><strong><?php echo number_format($recipient['messages_received']); ?></strong></td>
                                                    <td class="text-end"><?php echo formatCurrency($recipient['total_cost']); ?></td>
                                                    <td><?php echo formatDisplayDate($recipient['last_message_date']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-user-slash fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">No Recipients Data</h5>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-doughnut me-2"></i>
                                Recipient Types
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="recipientTypesChart" height="250"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'costs'): ?>
            <!-- Cost Analysis -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-bar me-2"></i>
                                Monthly SMS Costs
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="monthlyCostsChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-tags me-2"></i>
                                Cost by Category
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['cost_by_category'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Category</th>
                                                <th class="text-end">Messages</th>
                                                <th class="text-end">Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['cost_by_category'] as $category): ?>
                                                <tr>
                                                    <td><?php echo ucwords(str_replace('_', ' ', $category['category'])); ?></td>
                                                    <td class="text-end"><?php echo number_format($category['message_count']); ?></td>
                                                    <td class="text-end"><?php echo formatCurrency($category['total_cost']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-info-circle mb-2"></i>
                                    <br>No category cost data available
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif ($reportType === 'performance'): ?>
            <!-- Performance Analysis -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-chart-line me-2"></i>
                                Delivery Performance
                            </h6>
                        </div>
                        <div class="card-body">
                            <canvas id="deliveryPerformanceChart" height="300"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Failure Reasons
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($reportData['failure_reasons'])): ?>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Reason</th>
                                                <th class="text-end">Count</th>
                                                <th class="text-end">%</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($reportData['failure_reasons'] as $reason): ?>
                                                <tr>
                                                    <td style="max-width: 150px;">
                                                        <?php echo htmlspecialchars(truncateText($reason['error_message'], 50)); ?>
                                                    </td>
                                                    <td class="text-end"><?php echo number_format($reason['failure_count']); ?></td>
                                                    <td class="text-end"><?php echo $reason['percentage']; ?>%</td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="text-center text-muted py-3">
                                    <i class="fas fa-check-circle fa-2x text-success mb-2"></i>
                                    <br>No failures in selected period!
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- JavaScript for Charts -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($reportType === 'summary' && !empty($reportData['daily_trends'])): ?>
        // Daily Trends Chart
        const dailyTrendsCtx = document.getElementById('dailyTrendsChart').getContext('2d');
        const dailyTrendsData = {
            labels: [<?php echo implode(',', array_map(function($item) { return '"' . date('M d', strtotime($item['date'])) . '"'; }, array_reverse($reportData['daily_trends']))); ?>],
            datasets: [
                {
                    label: 'Sent',
                    data: [<?php echo implode(',', array_map(function($item) { return $item['messages_sent']; }, array_reverse($reportData['daily_trends']))); ?>],
                    borderColor: '#03045e',
                    backgroundColor: 'rgba(3, 4, 94, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Delivered',
                    data: [<?php echo implode(',', array_map(function($item) { return $item['messages_delivered']; }, array_reverse($reportData['daily_trends']))); ?>],
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4
                },
                {
                    label: 'Failed',
                    data: [<?php echo implode(',', array_map(function($item) { return $item['messages_failed']; }, array_reverse($reportData['daily_trends']))); ?>],
                    borderColor: '#dc3545',
                    backgroundColor: 'rgba(220, 53, 69, 0.1)',
                    tension: 0.4
                }
            ]
        };
        
        new Chart(dailyTrendsCtx, {
            type: 'line',
            data: dailyTrendsData,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    <?php endif; ?>

    <?php if ($reportType === 'recipients' && !empty($reportData['recipient_types'])): ?>
        // Recipient Types Chart
        const recipientTypesCtx = document.getElementById('recipientTypesChart').getContext('2d');
        new Chart(recipientTypesCtx, {
            type: 'doughnut',
            data: {
                labels: [<?php echo implode(',', array_map(function($item) { return '"' . ucwords(str_replace('_', ' ', $item['recipient_type'])) . '"'; }, $reportData['recipient_types'])); ?>],
                datasets: [{
                    data: [<?php echo implode(',', array_map(function($item) { return $item['total_sent']; }, $reportData['recipient_types'])); ?>],
                    backgroundColor: ['#03045e', '#ff2400', '#28a745', '#ffc107', '#17a2b8']
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    <?php endif; ?>
});

function exportReport() {
    const reportType = '<?php echo $reportType; ?>';
    const period = '<?php echo $period; ?>';
    window.open(`export.php?type=sms&report_type=${reportType}&period=${period}&format=excel`, '_blank');
}

function printReport() {
    window.print();
}
</script>

<?php include_once '../../includes/footer.php'; ?>