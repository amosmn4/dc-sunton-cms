/**
 * Dashboard JavaScript
 * Deliverance Church Management System
 * 
 * Handles dashboard charts, widgets, and interactive elements
 */

// Global dashboard variables
let financialChart = null;
let attendanceChart = null;

// Initialize dashboard when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initializeCharts();
    initializeWidgets();
    loadDashboardData();
});

/**
 * Initialize all charts
 */
function initializeCharts() {
    // Financial Overview Chart
    const financialCtx = document.getElementById('financialChart');
    if (financialCtx) {
        initializeFinancialChart(financialCtx);
    }
    
    // Attendance Trends Chart
    const attendanceCtx = document.getElementById('attendanceChart');
    if (attendanceCtx) {
        initializeAttendanceChart(attendanceCtx);
    }
}

/**
 * Initialize Financial Overview Chart
 */
function initializeFinancialChart(ctx) {
    // Get last 6 months data
    fetchFinancialData().then(data => {
        financialChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.months,
                datasets: [{
                    label: 'Income',
                    data: data.income,
                    backgroundColor: 'rgba(40, 167, 69, 0.8)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }, {
                    label: 'Expenses',
                    data: data.expenses,
                    backgroundColor: 'rgba(255, 36, 0, 0.8)',
                    borderColor: 'rgba(255, 36, 0, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    title: {
                        display: false
                    },
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Ksh ' + 
                                       context.parsed.y.toLocaleString('en-KE', {
                                           minimumFractionDigits: 2,
                                           maximumFractionDigits: 2
                                       });
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return 'Ksh ' + value.toLocaleString('en-KE');
                            }
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }).catch(error => {
        console.error('Error loading financial data:', error);
        ctx.getContext('2d').fillText('Error loading chart data', 10, 50);
    });
}

/**
 * Initialize Attendance Trends Chart
 */
function initializeAttendanceChart(ctx) {
    fetchAttendanceData().then(data => {
        attendanceChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.weeks,
                datasets: [{
                    label: 'Sunday Service',
                    data: data.sunday_service,
                    borderColor: '#03045e',
                    backgroundColor: 'rgba(3, 4, 94, 0.1)',
                    tension: 0.4,
                    fill: true
                }, {
                    label: 'Prayer Meeting',
                    data: data.prayer_meeting,
                    borderColor: '#ff2400',
                    backgroundColor: 'rgba(255, 36, 0, 0.1)',
                    tension: 0.4,
                    fill: false
                }, {
                    label: 'Bible Study',
                    data: data.bible_study,
                    borderColor: '#28a745',
                    backgroundColor: 'rgba(40, 167, 69, 0.1)',
                    tension: 0.4,
                    fill: false
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 10
                        }
                    }
                },
                interaction: {
                    mode: 'nearest',
                    axis: 'x',
                    intersect: false
                }
            }
        });
    }).catch(error => {
        console.error('Error loading attendance data:', error);
        ctx.getContext('2d').fillText('Error loading chart data', 10, 50);
    });
}

/**
 * Initialize dashboard widgets
 */
function initializeWidgets() {
    // Animate stat cards
    animateStatCards();
    
    // Initialize real-time updates
    initializeRealTimeUpdates();
    
    // Initialize interactive elements
    initializeInteractiveElements();
}

/**
 * Animate stat cards with counting effect
 */
function animateStatCards() {
    const statNumbers = document.querySelectorAll('.stats-number');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const target = entry.target;
                const finalValue = parseInt(target.textContent.replace(/[^\d]/g, ''));
                animateCounter(target, 0, finalValue, 2000);
                observer.unobserve(target);
            }
        });
    });
    
    statNumbers.forEach(stat => observer.observe(stat));
}

/**
 * Animate counter from start to end value
 */
function animateCounter(element, start, end, duration) {
    const range = end - start;
    const increment = range / (duration / 16);
    let current = start;
    
    const timer = setInterval(() => {
        current += increment;
        if (current >= end) {
            current = end;
            clearInterval(timer);
        }
        
        element.textContent = Math.floor(current).toLocaleString();
    }, 16);
}

/**
 * Initialize real-time updates
 */
function initializeRealTimeUpdates() {
    // Update clock
    updateClock();
    setInterval(updateClock, 1000);
    
    // Check for notifications every 30 seconds
    setInterval(checkNotifications, 30000);
    
    // Refresh dashboard data every 5 minutes
    setInterval(refreshDashboardData, 300000);
}

/**
 * Update dashboard clock
 */
function updateClock() {
    const clockElements = document.querySelectorAll('.dashboard-clock, .current-time');
    const now = new Date();
    const timeString = now.toLocaleTimeString('en-GB', { 
        hour12: false,
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
    });
    
    clockElements.forEach(element => {
        element.textContent = timeString;
    });
}

/**
 * Check for new notifications
 */
function checkNotifications() {
    if (!window.BASE_URL) return;
    
    fetch(`${BASE_URL}api/notifications.php?action=check_dashboard`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotificationBadge(data.count);
                
                if (data.urgent && data.urgent.length > 0) {
                    showUrgentNotifications(data.urgent);
                }
            }
        })
        .catch(error => {
            console.error('Error checking notifications:', error);
        });
}

/**
 * Update notification badge
 */
function updateNotificationBadge(count) {
    const badges = document.querySelectorAll('.notification-badge');
    badges.forEach(badge => {
        badge.textContent = count;
        badge.style.display = count > 0 ? 'inline' : 'none';
    });
}

/**
 * Show urgent notifications
 */
function showUrgentNotifications(notifications) {
    notifications.forEach(notification => {
        ChurchCMS.showToast(notification.message, 'warning', 8000);
    });
}

/**
 * Initialize interactive elements
 */
function initializeInteractiveElements() {
    // Birthday reminder interactions
    initializeBirthdayReminders();
    
    // Visitor follow-up interactions
    initializeVisitorFollowups();
    
    // Equipment maintenance interactions
    initializeEquipmentMaintenance();
    
    // Quick action interactions
    initializeQuickActions();
}

/**
 * Initialize birthday reminder interactions
 */
function initializeBirthdayReminders() {
    const birthdayCards = document.querySelectorAll('.birthday-card');
    
    birthdayCards.forEach(card => {
        // Add click to call functionality
        const phoneLinks = card.querySelectorAll('a[href^="tel:"]');
        phoneLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                const phone = this.getAttribute('href').replace('tel:', '');
                ChurchCMS.showToast(`Calling ${phone}...`, 'info');
            });
        });
        
        // Add birthday greeting functionality
        const greetBtn = card.querySelector('.btn-greet');
        if (greetBtn) {
            greetBtn.addEventListener('click', function() {
                const memberName = this.dataset.memberName;
                const memberId = this.dataset.memberId;
                sendBirthdayGreeting(memberId, memberName);
            });
        }
    });
}

/**
 * Send birthday greeting
 */
function sendBirthdayGreeting(memberId, memberName) {
    const message = `Happy Birthday ${memberName}! May God bless you with another year of His grace and favor. - Deliverance Church`;
    
    if (confirm(`Send birthday SMS to ${memberName}?`)) {
        fetch(`${BASE_URL}api/sms.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'send_birthday_sms',
                member_id: memberId,
                message: message
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                ChurchCMS.showToast('Birthday greeting sent successfully!', 'success');
            } else {
                ChurchCMS.showToast('Failed to send greeting: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error sending birthday greeting:', error);
            ChurchCMS.showToast('Error sending greeting', 'error');
        });
    }
}

/**
 * Initialize visitor follow-up interactions
 */
function initializeVisitorFollowups() {
    const followupBtns = document.querySelectorAll('.btn-followup');
    
    followupBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const visitorId = this.dataset.visitorId;
            const visitorName = this.dataset.visitorName;
            showFollowupModal(visitorId, visitorName);
        });
    });
}

/**
 * Show visitor follow-up modal
 */
function showFollowupModal(visitorId, visitorName) {
    const modalHtml = `
        <div class="modal fade" id="followupModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header bg-church-blue text-white">
                        <h5 class="modal-title">Follow-up: ${visitorName}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <form id="followupForm">
                            <input type="hidden" name="visitor_id" value="${visitorId}">
                            <div class="mb-3">
                                <label class="form-label">Follow-up Type</label>
                                <select class="form-select" name="followup_type" required>
                                    <option value="phone_call">Phone Call</option>
                                    <option value="visit">Home Visit</option>
                                    <option value="sms">SMS</option>
                                    <option value="email">Email</option>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3" required 
                                          placeholder="Describe the follow-up activity..."></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Next Follow-up Date</label>
                                <input type="date" class="form-control" name="next_followup_date" 
                                       min="${new Date().toISOString().split('T')[0]}">
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-church-primary" onclick="submitFollowup()">
                            <i class="fas fa-save me-1"></i>Save Follow-up
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    const modal = new bootstrap.Modal(document.getElementById('followupModal'));
    modal.show();
    
    document.getElementById('followupModal').addEventListener('hidden.bs.modal', function() {
        this.remove();
    });
}

/**
 * Submit visitor follow-up
 */
function submitFollowup() {
    const form = document.getElementById('followupForm');
    const formData = new FormData(form);
    
    fetch(`${BASE_URL}api/visitors.php`, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            ChurchCMS.showToast('Follow-up recorded successfully!', 'success');
            bootstrap.Modal.getInstance(document.getElementById('followupModal')).hide();
            setTimeout(() => location.reload(), 1000);
        } else {
            ChurchCMS.showToast('Error: ' + data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error submitting follow-up:', error);
        ChurchCMS.showToast('Error submitting follow-up', 'error');
    });
}

/**
 * Initialize equipment maintenance interactions
 */
function initializeEquipmentMaintenance() {
    const maintenanceBtns = document.querySelectorAll('.btn-maintenance');
    
    maintenanceBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const equipmentId = this.dataset.equipmentId;
            const equipmentName = this.dataset.equipmentName;
            scheduleMaintenanceReminder(equipmentId, equipmentName);
        });
    });
}

/**
 * Schedule maintenance reminder
 */
function scheduleMaintenanceReminder(equipmentId, equipmentName) {
    if (confirm(`Schedule maintenance reminder for ${equipmentName}?`)) {
        fetch(`${BASE_URL}api/equipment.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'schedule_maintenance',
                equipment_id: equipmentId
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                ChurchCMS.showToast('Maintenance reminder scheduled!', 'success');
            } else {
                ChurchCMS.showToast('Error: ' + data.message, 'error');
            }
        })
        .catch(error => {
            console.error('Error scheduling maintenance:', error);
            ChurchCMS.showToast('Error scheduling maintenance', 'error');
        });
    }
}

/**
 * Initialize quick action interactions
 */
function initializeQuickActions() {
    const quickActionCards = document.querySelectorAll('.quick-action-card');
    
    quickActionCards.forEach(card => {
        card.addEventListener('click', function(e) {
            // Add ripple effect
            const ripple = document.createElement('div');
            ripple.className = 'ripple';
            this.appendChild(ripple);
            
            const rect = this.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            
            setTimeout(() => ripple.remove(), 600);
        });
    });
}

/**
 * Load dashboard data
 */
function loadDashboardData() {
    // This would typically load fresh data from the server
    if (typeof dashboardData !== 'undefined') {
        updateStatCards(dashboardData.stats);
    }
}

/**
 * Update stat cards with new data
 */
function updateStatCards(stats) {
    const statElements = {
        totalMembers: document.querySelector('[data-stat="total-members"] .stats-number'),
        newMembers: document.querySelector('[data-stat="new-members"] .stats-number'),
        visitors: document.querySelector('[data-stat="visitors"] .stats-number'),
        attendance: document.querySelector('[data-stat="attendance"] .stats-number')
    };
    
    Object.keys(statElements).forEach(key => {
        const element = statElements[key];
        if (element && stats[key] !== undefined) {
            element.textContent = stats[key].toLocaleString();
        }
    });
}

/**
 * Refresh dashboard data
 */
function refreshDashboardData() {
    fetch(`${BASE_URL}api/dashboard.php?action=refresh`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateStatCards(data.stats);
                updateCharts(data.charts);
                ChurchCMS.showToast('Dashboard updated', 'success', 2000);
            }
        })
        .catch(error => {
            console.error('Error refreshing dashboard:', error);
        });
}

/**
 * Update charts with new data
 */
function updateCharts(chartData) {
    if (financialChart && chartData.financial) {
        financialChart.data.datasets[0].data = chartData.financial.income;
        financialChart.data.datasets[1].data = chartData.financial.expenses;
        financialChart.update();
    }
    
    if (attendanceChart && chartData.attendance) {
        attendanceChart.data.datasets.forEach((dataset, index) => {
            dataset.data = chartData.attendance.datasets[index];
        });
        attendanceChart.update();
    }
}

/**
 * Fetch financial data from server
 */
async function fetchFinancialData() {
    try {
        const response = await fetch(`${BASE_URL}api/dashboard.php?action=financial_data`);
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error fetching financial data:', error);
        // Return dummy data for demo
        return {
            months: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
            income: [50000, 45000, 55000, 60000, 52000, 58000],
            expenses: [35000, 38000, 42000, 45000, 40000, 43000]
        };
    }
}

/**
 * Fetch attendance data from server
 */
async function fetchAttendanceData() {
    try {
        const response = await fetch(`${BASE_URL}api/dashboard.php?action=attendance_data`);
        const data = await response.json();
        
        if (data.success) {
            return data.data;
        } else {
            throw new Error(data.message);
        }
    } catch (error) {
        console.error('Error fetching attendance data:', error);
        // Return dummy data for demo
        const weeks = [];
        const sundayService = [];
        const prayerMeeting = [];
        const bibleStudy = [];
        
        for (let i = 12; i >= 0; i--) {
            const date = new Date();
            date.setDate(date.getDate() - (i * 7));
            weeks.push(date.toLocaleDateString('en-GB', { month: 'short', day: 'numeric' }));
            
            sundayService.push(Math.floor(Math.random() * 50) + 100);
            prayerMeeting.push(Math.floor(Math.random() * 30) + 40);
            bibleStudy.push(Math.floor(Math.random() * 25) + 35);
        }
        
        return {
            weeks,
            sunday_service: sundayService,
            prayer_meeting: prayerMeeting,
            bible_study: bibleStudy
        };
    }
}

/**
 * Export dashboard data
 */
function exportDashboardData(format = 'pdf') {
    ChurchCMS.showLoading('Generating dashboard report...');
    
    const exportData = {
        action: 'export_dashboard',
        format: format,
        include_charts: true,
        include_stats: true
    };
    
    fetch(`${BASE_URL}modules/reports/export.php`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(exportData)
    })
    .then(response => response.blob())
    .then(blob => {
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.style.display = 'none';
        a.href = url;
        a.download = `dashboard-report-${new Date().toISOString().split('T')[0]}.${format}`;
        document.body.appendChild(a);
        a.click();
        window.URL.revokeObjectURL(url);
        document.body.removeChild(a);
        
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Dashboard report downloaded successfully!', 'success');
    })
    .catch(error => {
        console.error('Error exporting dashboard:', error);
        ChurchCMS.hideLoading();
        ChurchCMS.showToast('Error generating report', 'error');
    });
}

/**
 * Print dashboard
 */
function printDashboard() {
    // Hide non-printable elements
    document.body.classList.add('printing');
    
    // Set print styles
    const printStyles = `
        @media print {
            .no-print { display: none !important; }
            .card { break-inside: avoid; }
            .stats-card { border: 1px solid #000; }
            canvas { max-height: 300px; }
        }
    `;
    
    const styleSheet = document.createElement('style');
    styleSheet.textContent = printStyles;
    document.head.appendChild(styleSheet);
    
    setTimeout(() => {
        window.print();
        document.body.classList.remove('printing');
        document.head.removeChild(styleSheet);
    }, 500);
}

// Expose global functions
window.DashboardJS = {
    exportDashboardData,
    printDashboard,
    refreshDashboardData,
    sendBirthdayGreeting,
    submitFollowup,
    scheduleMaintenanceReminder
};