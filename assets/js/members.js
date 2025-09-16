/**
 * Members Management JavaScript
 * Deliverance Church Management System
 */

// Members namespace
window.ChurchCMS = window.ChurchCMS || {};
ChurchCMS.Members = {};

// =====================================================
// MEMBER FORM VALIDATION
// =====================================================

/**
 * Initialize member form validation
 */
ChurchCMS.Members.initFormValidation = function() {
    const form = document.getElementById('memberForm');
    if (!form) return;
    
    // Validation rules
    const validationRules = {
        first_name: ['required', 'min:2', 'max:50'],
        last_name: ['required', 'min:2', 'max:50'],
        gender: ['required'],
        join_date: ['required', 'date'],
        phone: ['phone'],
        email: ['email'],
        date_of_birth: ['date']
    };
    
    // Real-time validation
    Object.keys(validationRules).forEach(fieldName => {
        const field = form.querySelector(`[name="${fieldName}"]`);
        if (field) {
            field.addEventListener('blur', function() {
                ChurchCMS.Members.validateField(this, validationRules[fieldName]);
            });
            
            field.addEventListener('input', function() {
                // Clear previous validation state on input
                this.classList.remove('is-invalid', 'is-valid');
                const feedback = this.parentNode.querySelector('.invalid-feedback');
                if (feedback) feedback.remove();
            });
        }
    });
    
    // Form submission validation
    form.addEventListener('submit', function(e) {
        const validation = ChurchCMS.validateForm(this, validationRules);
        
        if (!validation.isValid) {
            e.preventDefault();
            e.stopPropagation();
            
            // Focus on first invalid field
            const firstInvalid = this.querySelector('.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            
            ChurchCMS.showToast('Please correct the errors in the form', 'error');
        } else {
            // Show loading state
            ChurchCMS.Members.setFormLoading(this, true);
        }
        
        this.classList.add('was-validated');
    });
};

/**
 * Validate individual field
 * @param {HTMLElement} field - Form field element
 * @param {Array} rules - Validation rules
 */
ChurchCMS.Members.validateField = function(field, rules) {
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';
    
    for (const rule of rules) {
        if (rule === 'required' && !value) {
            isValid = false;
            errorMessage = 'This field is required';
            break;
        }
        
        if (rule === 'email' && value && !ChurchCMS.isValidEmail(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid email address';
            break;
        }
        
        if (rule === 'phone' && value && !ChurchCMS.isValidPhone(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid phone number';
            break;
        }
        
        if (rule === 'date' && value && !ChurchCMS.Members.isValidDate(value)) {
            isValid = false;
            errorMessage = 'Please enter a valid date';
            break;
        }
        
        if (rule.startsWith('min:')) {
            const minLength = parseInt(rule.split(':')[1]);
            if (value.length < minLength) {
                isValid = false;
                errorMessage = `Minimum length is ${minLength} characters`;
                break;
            }
        }
        
        if (rule.startsWith('max:')) {
            const maxLength = parseInt(rule.split(':')[1]);
            if (value.length > maxLength) {
                isValid = false;
                errorMessage = `Maximum length is ${maxLength} characters`;
                break;
            }
        }
    }
    
    // Update field UI
    field.classList.remove('is-invalid', 'is-valid');
    const existingFeedback = field.parentNode.querySelector('.invalid-feedback');
    if (existingFeedback) existingFeedback.remove();
    
    if (value && !isValid) {
        field.classList.add('is-invalid');
        const feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        feedback.textContent = errorMessage;
        field.parentNode.appendChild(feedback);
    } else if (value && isValid) {
        field.classList.add('is-valid');
    }
    
    return isValid;
};

/**
 * Check if date is valid
 * @param {string} dateString - Date string to validate
 * @returns {boolean} Is valid date
 */
ChurchCMS.Members.isValidDate = function(dateString) {
    const date = new Date(dateString);
    return date instanceof Date && !isNaN(date) && dateString.match(/^\d{4}-\d{2}-\d{2}$/);
};

/**
 * Set form loading state
 * @param {HTMLFormElement} form - Form element
 * @param {boolean} loading - Loading state
 */
ChurchCMS.Members.setFormLoading = function(form, loading) {
    const submitBtn = form.querySelector('button[type="submit"]');
    const inputs = form.querySelectorAll('input, select, textarea');
    
    if (loading) {
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
        }
        inputs.forEach(input => input.disabled = true);
    } else {
        if (submitBtn) {
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-save me-2"></i>Save Member';
        }
        inputs.forEach(input => input.disabled = false);
    }
};

// =====================================================
// MEMBER PHOTO MANAGEMENT
// =====================================================

/**
 * Initialize photo upload functionality
 */
ChurchCMS.Members.initPhotoUpload = function() {
    const photoInput = document.getElementById('photo');
    const photoPreview = document.getElementById('photoPreview');
    const removePhotoBtn = document.getElementById('removePhoto');
    
    if (!photoInput || !photoPreview) return;
    
    // File input change handler
    photoInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        
        if (file) {
            // Validate file
            if (!ChurchCMS.Members.validatePhotoFile(file)) {
                this.value = '';
                return;
            }
            
            // Preview image
            const reader = new FileReader();
            reader.onload = function(e) {
                photoPreview.src = e.target.result;
                photoPreview.style.display = 'block';
                if (removePhotoBtn) removePhotoBtn.style.display = 'inline-block';
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Remove photo handler
    if (removePhotoBtn) {
        removePhotoBtn.addEventListener('click', function() {
            photoInput.value = '';
            photoPreview.src = '';
            photoPreview.style.display = 'none';
            this.style.display = 'none';
            
            // Add hidden input to mark for removal if editing existing member
            const memberId = document.querySelector('[name="member_id"]');
            if (memberId && memberId.value) {
                let removeInput = document.getElementById('remove_photo');
                if (!removeInput) {
                    removeInput = document.createElement('input');
                    removeInput.type = 'hidden';
                    removeInput.name = 'remove_photo';
                    removeInput.id = 'remove_photo';
                    photoInput.form.appendChild(removeInput);
                }
                removeInput.value = '1';
            }
        });
    }
};

/**
 * Validate photo file
 * @param {File} file - File to validate
 * @returns {boolean} Is valid photo file
 */
ChurchCMS.Members.validatePhotoFile = function(file) {
    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
    const maxSize = 2 * 1024 * 1024; // 2MB
    
    if (!allowedTypes.includes(file.type)) {
        ChurchCMS.showToast('Please select a valid image file (JPG, PNG, or GIF)', 'error');
        return false;
    }
    
    if (file.size > maxSize) {
        ChurchCMS.showToast('Image file size must be less than 2MB', 'error');
        return false;
    }
    
    return true;
};

// =====================================================
// DEPARTMENT MANAGEMENT
// =====================================================

/**
 * Initialize department assignment functionality
 */
ChurchCMS.Members.initDepartmentManagement = function() {
    const addDeptBtn = document.getElementById('addDepartment');
    const deptContainer = document.getElementById('departmentContainer');
    
    if (!addDeptBtn || !deptContainer) return;
    
    addDeptBtn.addEventListener('click', function() {
        ChurchCMS.Members.addDepartmentRow();
    });
    
    // Initialize existing department rows
    deptContainer.addEventListener('click', function(e) {
        if (e.target.classList.contains('remove-department')) {
            e.target.closest('.department-row').remove();
        }
    });
};

/**
 * Add new department assignment row
 */
ChurchCMS.Members.addDepartmentRow = function() {
    const container = document.getElementById('departmentContainer');
    const rowCount = container.children.length;
    
    const row = document.createElement('div');
    row.className = 'row department-row mb-3';
    row.innerHTML = `
        <div class="col-md-5">
            <select name="departments[${rowCount}][department_id]" class="form-select" required>
                <option value="">Select Department</option>
                ${ChurchCMS.Members.getDepartmentOptions()}
            </select>
        </div>
        <div class="col-md-4">
            <select name="departments[${rowCount}][role]" class="form-select">
                <option value="member">Member</option>
                <option value="assistant">Assistant</option>
                <option value="secretary">Secretary</option>
                <option value="treasurer">Treasurer</option>
                <option value="head">Head/Leader</option>
            </select>
        </div>
        <div class="col-md-3">
            <button type="button" class="btn btn-outline-danger remove-department">
                <i class="fas fa-times"></i> Remove
            </button>
        </div>
    `;
    
    container.appendChild(row);
};

/**
 * Get department options HTML (this would be populated from server data)
 */
ChurchCMS.Members.getDepartmentOptions = function() {
    // This would typically be populated from server-side data
    // For now, return empty string - will be populated by PHP
    return window.departmentOptions || '';
};

// =====================================================
// FAMILY RELATIONSHIP MANAGEMENT
// =====================================================

/**
 * Initialize family relationship functionality
 */
ChurchCMS.Members.initFamilyRelationships = function() {
    const spouseSelect = document.getElementById('spouse_member_id');
    const fatherSelect = document.getElementById('father_member_id');
    const motherSelect = document.getElementById('mother_member_id');
    
    // Initialize Select2 for better member selection
    if (typeof $ !== 'undefined' && $.fn.select2) {
        [spouseSelect, fatherSelect, motherSelect].forEach(select => {
            if (select) {
                $(select).select2({
                    placeholder: 'Search and select member...',
                    allowClear: true,
                    ajax: {
                        url: BASE_URL + 'api/members.php',
                        dataType: 'json',
                        delay: 250,
                        data: function(params) {
                            return {
                                action: 'search',
                                q: params.term,
                                exclude: document.querySelector('[name="member_id"]')?.value
                            };
                        },
                        processResults: function(data) {
                            return {
                                results: data.data.map(member => ({
                                    id: member.id,
                                    text: `${member.first_name} ${member.last_name} (#${member.member_number})`
                                }))
                            };
                        }
                    }
                });
            }
        });
    }
};

// =====================================================
// MEMBER SEARCH AND FILTERS
// =====================================================

/**
 * Initialize search and filter functionality
 */
ChurchCMS.Members.initSearchAndFilters = function() {
    const searchInput = document.getElementById('memberSearch');
    const filterForm = document.getElementById('filterForm');
    
    if (searchInput) {
        // Debounced search
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                ChurchCMS.Members.performSearch(this.value);
            }, 300);
        });
    }
    
    if (filterForm) {
        // Auto-submit on filter change
        const filterSelects = filterForm.querySelectorAll('select');
        filterSelects.forEach(select => {
            select.addEventListener('change', function() {
                filterForm.submit();
            });
        });
    }
};

/**
 * Perform member search
 * @param {string} query - Search query
 */
ChurchCMS.Members.performSearch = function(query) {
    const membersList = document.getElementById('membersList');
    if (!membersList) return;
    
    // Show loading state
    membersList.innerHTML = '<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x"></i><p class="mt-2">Searching...</p></div>';
    
    // Perform search
    ChurchCMS.get(BASE_URL + 'api/members.php', {
        action: 'search',
        q: query
    })
    .then(response => {
        if (response.success) {
            ChurchCMS.Members.renderMembersList(response.data);
        } else {
            membersList.innerHTML = '<div class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle fa-2x"></i><p class="mt-2">Search failed</p></div>';
        }
    })
    .catch(error => {
        console.error('Search error:', error);
        membersList.innerHTML = '<div class="text-center py-4 text-danger"><i class="fas fa-exclamation-triangle fa-2x"></i><p class="mt-2">An error occurred</p></div>';
        // Optionally, you can add more error handling logic here
        // For example, you could log the error to an external service
        ChurchCMS.logError('Search error', error);
    });