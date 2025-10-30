<?php
/**
 * Main Footer Template
 * Deliverance Church Management System
 */
?>

<?php if (isLoggedIn()): ?>
        </div> <!-- End container-fluid -->
    </div> <!-- End main-content -->

    <!-- Footer -->
    <footer class="bg-white border-top mt-5 py-4" style="margin-left: 250px; transition: margin-left 0.3s ease;">
        <div class="container-fluid px-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-church text-church-blue me-2"></i>
                        <span class="text-muted">
                            &copy; <?php echo date('Y'); ?> 
                            <strong><?php echo htmlspecialchars($churchInfo['church_name'] ?? 'Deliverance Church'); ?></strong>
                            - Church Management System
                        </span>
                    </div>
                </div>
                <div class="col-md-6 text-end">
                    <div class="d-flex align-items-center justify-content-end">
                        <!-- System Status Indicators -->
                        <div class="me-3">
                            <span class="badge bg-success me-1">
                                <i class="fas fa-database me-1"></i>DB Online
                            </span>
                            <span class="badge bg-info me-1">
                                <i class="fas fa-users me-1"></i><?php echo getRecordCount('members', ['membership_status' => 'active']); ?> Members
                            </span>
                            <?php if (isFeatureEnabled('sms')): ?>
                            <span class="badge bg-warning">
                                <i class="fas fa-sms me-1"></i>SMS Active
                            </span>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Version Info -->
                        <small class="text-muted">
                            v1.0.0 | 
                            <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#aboutModal">About</a> |
                            <a href="#" class="text-decoration-none" data-bs-toggle="modal" data-bs-target="#helpModal">Help</a>
                        </small>
                    </div>
                </div>
            </div>
            
            <!-- Additional Footer Info -->
            <div class="row mt-3 pt-3 border-top">
                <div class="col-md-8">
                    <div class="d-flex flex-wrap gap-3 small text-muted">
                        <span><i class="fas fa-clock me-1"></i>Last Login: <?php echo formatDisplayDateTime($_SESSION['login_time'] ?? date('Y-m-d H:i:s')); ?></span>
                        <span><i class="fas fa-user me-1"></i>Logged in as: <?php echo getUserRoleDisplay($_SESSION['user_role'] ?? ''); ?></span>
                        <span><i class="fas fa-server me-1"></i>Server: <?php echo $_SERVER['SERVER_NAME']; ?></span>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <div class="small text-muted">
                        <i class="fas fa-calendar-alt me-1"></i>
                        <?php echo date('l, F j, Y'); ?> | 
                        <span id="current-time"><?php echo date('H:i:s'); ?></span>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <!-- About Modal -->
    <div class="modal fade" id="aboutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-info-circle me-2"></i>About Church CMS
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-4">
                        <i class="fas fa-church fa-4x text-church-blue mb-3"></i>
                        <h4><?php echo htmlspecialchars($churchInfo['church_name'] ?? 'Deliverance Church'); ?></h4>
                        <p class="text-muted">Church Management System</p>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold">System Information</h6>
                            <ul class="list-unstyled small">
                                <li><strong>Version:</strong> 1.0.0</li>
                                <li><strong>Release Date:</strong> January 2025</li>
                                <li><strong>PHP Version:</strong> <?php echo PHP_VERSION; ?></li>
                                <li><strong>Developed By:</strong> Amos Nyamai - 0745600377</li>
                                <li><strong>Database:</strong> MySQL</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="fw-bold">Features</h6>
                            <ul class="list-unstyled small">
                                <li><i class="fas fa-check text-success me-1"></i> Member Management</li>
                                <li><i class="fas fa-check text-success me-1"></i> Attendance Tracking</li>
                                <li><i class="fas fa-check text-success me-1"></i> Financial Management</li>
                                <li><i class="fas fa-check text-success me-1"></i> SMS Broadcasting</li>
                                <li><i class="fas fa-check text-success me-1"></i> Equipment Management</li>
                                <li><i class="fas fa-check text-success me-1"></i> Visitor Management</li>
                                <li><i class="fas fa-check text-success me-1"></i> Reports & Analytics</li>
                            </ul>
                        </div>
                    </div>
                    
                    <?php if (isset($churchInfo['mission_statement']) && !empty($churchInfo['mission_statement'])): ?>
                    <div class="mt-4 p-3 bg-light rounded">
                        <h6 class="fw-bold">Mission Statement</h6>
                        <p class="mb-0 small"><?php echo htmlspecialchars($churchInfo['mission_statement']); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-church-blue text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-question-circle me-2"></i>Help & Support
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="fw-bold">Quick Help</h6>
                            <div class="accordion" id="helpAccordion">
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help1">
                                            How to add a new member?
                                        </button>
                                    </h2>
                                    <div id="help1" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body small">
                                            Go to <strong>Members > Add Member</strong> and fill in the required information. Make sure to include at least first name, last name, gender, and join date.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help2">
                                            How to record attendance?
                                        </button>
                                    </h2>
                                    <div id="help2" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body small">
                                            Use <strong>Attendance > Record Attendance</strong>. You can either check individual members or enter bulk attendance numbers.
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="accordion-item">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#help3">
                                            How to send SMS to members?
                                        </button>
                                    </h2>
                                    <div id="help3" class="accordion-collapse collapse" data-bs-parent="#helpAccordion">
                                        <div class="accordion-body small">
                                            Go to <strong>SMS > Send SMS</strong>, select recipients (all members, department, or individuals), choose a template or write a custom message, and send.
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6 class="fw-bold">Contact Support</h6>
                            <div class="mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2">
                                            <i class="fas fa-envelope text-church-blue me-2"></i>Email Support
                                        </h6>
                                        <p class="card-text small mb-0">
                                            For technical support, send an email to:<br>
                                            <a href="mailto:support@churchcms.org" class="text-decoration-none">support@churchcms.org</a>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2">
                                            <i class="fas fa-phone text-church-blue me-2"></i>Phone Support
                                        </h6>
                                        <p class="card-text small mb-0">
                                            Call us during business hours:<br>
                                            <strong>+254 700 000 000</strong><br>
                                            <small class="text-muted">Mon-Fri, 9:00 AM - 5:00 PM</small>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <div class="card border-0 bg-light">
                                    <div class="card-body p-3">
                                        <h6 class="card-title mb-2">
                                            <i class="fas fa-book text-church-blue me-2"></i>Documentation
                                        </h6>
                                        <p class="card-text small mb-0">
                                            Access the complete user manual and documentation online.
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- System Information -->
                            <h6 class="fw-bold mt-4">System Information</h6>
                            <div class="small text-muted">
                                <div class="mb-1"><strong>User:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? ''); ?></div>
                                <div class="mb-1"><strong>Role:</strong> <?php echo getUserRoleDisplay($_SESSION['user_role'] ?? ''); ?></div>
                                <div class="mb-1"><strong>Browser:</strong> <span id="browser-info"></span></div>
                                <div class="mb-1"><strong>IP:</strong> <?php echo getClientIP(); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

<?php endif; // End isLoggedIn check ?>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 bg-dark bg-opacity-50 d-none" style="z-index: 9999;">
    <div class="d-flex justify-content-center align-items-center h-100">
        <div class="text-center text-white">
            <div class="spinner-border mb-3" role="status" style="width: 3rem; height: 3rem;">
                <span class="visually-hidden">Loading...</span>
            </div>
            <div>Please wait...</div>
        </div>
    </div>
</div>

