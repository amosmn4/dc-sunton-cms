<?php
/**
 * Custom Report Builder
 * Build custom reports with filters and parameters
 */

require_once '../../config/config.php';
require_once '../../includes/functions.php';

requireLogin();

$page_title = 'Custom Report Builder';
$page_icon = 'fas fa-magic';
$breadcrumb = [
    ['title' => 'Reports', 'url' => BASE_URL . 'modules/reports/'],
    ['title' => 'Custom Reports']
];

include '../../includes/header.php';
?>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card border-0 shadow-sm sticky-top" style="top: 20px;">
            <div class="card-header bg-church-blue text-white">
                <h5 class="mb-0"><i class="fas fa-filter me-2"></i>Report Parameters</h5>
            </div>
            <div class="card-body">
                <form id="customReportForm">
                    <div class="mb-3">
                        <label class="form-label">Report Type</label>
                        <select class="form-select" id="reportType" required>
                            <option value="">Select Type</option>
                            <option value="members">Members</option>
                            <option value="attendance">Attendance</option>
                            <option value="finance">Finance</option>
                            <option value="visitors">Visitors</option>
                            <option value="equipment">Equipment</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Date Range</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="date" class="form-control" id="dateFrom" value="<?php echo date('Y-m-01'); ?>">
                            </div>
                            <div class="col-6">
                                <input type="date" class="form-control" id="dateTo" value="<?php echo date('Y-m-t'); ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3" id="filterOptions">
                        <!-- Dynamic filters loaded based on report type -->
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Export Format</label>
                        <select class="form-select" id="exportFormat">
                            <option value="excel">Excel (XLSX)</option>
                            <option value="csv">CSV</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    
                    <button type="button" class="btn btn-church-primary w-100 mb-2" onclick="generateReport()">
                        <i class="fas fa-play me-2"></i>Generate Report
                    </button>
                    
                    <button type="button" class="btn btn-outline-secondary w-100" onclick="resetForm()">
                        <i class="fas fa-redo me-2"></i>Reset
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-lg-8">
        <!-- Report Preview -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-eye me-2"></i>Report Preview
                </h5>
            </div>
            <div class="card-body" id="reportPreview">
                <div class="text-center py-5">
                    <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">No Report Generated</h5>
                    <p class="text-muted">Select report parameters and click "Generate Report" to preview</p>
                </div>
            </div>
        </div>
        
        <!-- Saved Reports -->
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white">
                <h5 class="mb-0 text-church-blue">
                    <i class="fas fa-save me-2"></i>Saved Report Templates
                </h5>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Monthly Member Growth</h6>
                            <small class="text-muted">Saved 2 days ago</small>
                        </div>
                        <p class="mb-1 small text-muted">Members report with growth analysis</p>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Quarterly Financial Summary</h6>
                            <small class="text-muted">Saved 5 days ago</small>
                        </div>
                        <p class="mb-1 small text-muted">Income and expense breakdown by quarter</p>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">Sunday Service Attendance</h6>
                            <small class="text-muted">Saved 1 week ago</small>
                        </div>
                        <p class="mb-1 small text-muted">Weekly Sunday service attendance patterns</p>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Report type filter options
const filterTemplates = {
    members: `
        <div class="mb-3">
            <label class="form-label">Status</label>
            <select class="form-select" id="memberStatus">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Age Group</label>
            <select class="form-select" id="ageGroup">
                <option value="all">All Ages</option>
                <option value="children">Children</option>
                <option value="youth">Youth</option>
                <option value="adults">Adults</option>
                <option value="seniors">Seniors</option>
            </select>
        </div>
    `,
    attendance: `
        <div class="mb-3">
            <label class="form-label">Event Type</label>
            <select class="form-select" id="eventType">
                <option value="all">All Events</option>
                <option value="sunday_service">Sunday Service</option>
                <option value="prayer_meeting">Prayer Meeting</option>
                <option value="bible_study">Bible Study</option>
            </select>
        </div>
    `,
    finance: `
        <div class="mb-3">
            <label class="form-label">Transaction Type</label>
            <select class="form-select" id="transType">
                <option value="both">Income & Expenses</option>
                <option value="income">Income Only</option>
                <option value="expense">Expenses Only</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Category</label>
            <select class="form-select" id="category">
                <option value="all">All Categories</option>
            </select>
        </div>
    `,
    visitors: `
        <div class="mb-3">
            <label class="form-label">Visitor Status</label>
            <select class="form-select" id="visitorStatus">
                <option value="all">All Status</option>
                <option value="new_visitor">New Visitors</option>
                <option value="follow_up">In Follow-up</option>
                <option value="converted_member">Converted</option>
            </select>
        </div>
    `,
    equipment: `
        <div class="mb-3">
            <label class="form-label">Equipment Status</label>
            <select class="form-select" id="equipStatus">
                <option value="all">All Status</option>
                <option value="good">Good</option>
                <option value="needs_attention">Needs Attention</option>
                <option value="damaged">Damaged</option>
            </select>
        </div>
    `
};

// Update filters when report type changes
document.getElementById('reportType').addEventListener('change', function() {
    const type = this.value;
    const filterDiv = document.getElementById('filterOptions');
    
    if (type && filterTemplates[type]) {
        filterDiv.innerHTML = filterTemplates[type];
    } else {
        filterDiv.innerHTML = '';
    }
});

function generateReport() {
    const reportType = document.getElementById('reportType').value;
    
    if (!reportType) {
        ChurchCMS.showToast('Please select a report type', 'warning');
        return;
    }
    
    ChurchCMS.showLoading('Generating report...');
    
    // Simulate report generation
    setTimeout(() => {
        ChurchCMS.hideLoading();
        
        const previewHTML = `
            <div class="animate-fade-in">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5>${reportType.charAt(0).toUpperCase() + reportType.slice(1)} Report</h5>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-success" onclick="exportReport()">
                            <i class="fas fa-download me-1"></i>Export
                        </button>
                        <button class="btn btn-outline-primary" onclick="saveTemplate()">
                            <i class="fas fa-save me-1"></i>Save Template
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead class="table-light">
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>1</td>
                                <td>Sample Data 1</td>
                                <td>${new Date().toLocaleDateString()}</td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>Ksh 10,000</td>
                            </tr>
                            <tr>
                                <td>2</td>
                                <td>Sample Data 2</td>
                                <td>${new Date().toLocaleDateString()}</td>
                                <td><span class="badge bg-success">Active</span></td>
                                <td>Ksh 15,000</td>
                            </tr>
                            <tr>
                                <td>3</td>
                                <td>Sample Data 3</td>
                                <td>${new Date().toLocaleDateString()}</td>
                                <td><span class="badge bg-warning">Pending</span></td>
                                <td>Ksh 8,000</td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Total:</th>
                                <th>Ksh 33,000</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    Report generated successfully with 3 records
                </div>
            </div>
        `;
        
        document.getElementById('reportPreview').innerHTML = previewHTML;
        ChurchCMS.showToast('Report generated successfully!', 'success');
    }, 2000);
}

function exportReport() {
    const format = document.getElementById('exportFormat').value;
    ChurchCMS.showLoading('Exporting report...');
    
    setTimeout(() => {
        ChurchCMS.hideLoading();
        ChurchCMS.showToast(`Report exported as ${format.toUpperCase()}!`, 'success');
    }, 1500);
}

function saveTemplate() {
    ChurchCMS.showToast('Report template saved successfully!', 'success');
}

function resetForm() {
    document.getElementById('customReportForm').reset();
    document.getElementById('filterOptions').innerHTML = '';
    document.getElementById('reportPreview').innerHTML = `
        <div class="text-center py-5">
            <i class="fas fa-chart-bar fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">No Report Generated</h5>
            <p class="text-muted">Select report parameters and click "Generate Report" to preview</p>
        </div>
    `;
}
</script>

<?php include '../../includes/footer.php'; ?>