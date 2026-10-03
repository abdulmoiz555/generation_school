/**
 * EduManage - Core JavaScript Application
 * Vanilla JS - No external frontend frameworks required
 */

document.addEventListener('DOMContentLoaded', () => {
    initTheme();
    initSidebar();
    initModals();
    initDeleteConfirmations();
    initClassSectionDropdowns();
});

/**
 * Dark / Light Theme Toggle with localStorage persistence
 */
function initTheme() {
    const savedTheme = localStorage.getItem('edumanage_theme') || 'light';
    document.documentElement.setAttribute('data-theme', savedTheme);
    updateThemeToggleIcons(savedTheme);

    const themeToggleBtn = document.getElementById('themeToggleBtn');
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const currentTheme = document.documentElement.getAttribute('data-theme') || 'light';
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('edumanage_theme', newTheme);
            updateThemeToggleIcons(newTheme);
        });
    }
}

function updateThemeToggleIcons(theme) {
    const icon = document.querySelector('#themeToggleBtn i');
    const label = document.querySelector('#themeToggleBtn .theme-label');
    if (icon) {
        if (theme === 'dark') {
            icon.className = 'fas fa-sun text-warning';
            if (label) label.textContent = 'Light Mode';
        } else {
            icon.className = 'fas fa-moon text-secondary';
            if (label) label.textContent = 'Dark Mode';
        }
    }
}

/**
 * Sidebar and Mobile Menu Toggle
 */
function initSidebar() {
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.app-sidebar');
    let backdrop = document.querySelector('.sidebar-backdrop');

    if (!backdrop) {
        backdrop = document.createElement('div');
        backdrop.className = 'sidebar-backdrop';
        document.body.appendChild(backdrop);
    }

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
        });

        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('show');
            backdrop.classList.remove('show');
        });
    }
}

/**
 * Universal Delete Confirmation
 */
function initDeleteConfirmations() {
    document.addEventListener('click', (e) => {
        const deleteTrigger = e.target.closest('.btn-delete-confirm');
        if (deleteTrigger) {
            e.preventDefault();
            const message = deleteTrigger.getAttribute('data-confirm-message') || 'Are you sure you want to delete this record? This action cannot be undone.';
            const href = deleteTrigger.getAttribute('href');
            
            if (confirm(message)) {
                if (deleteTrigger.tagName === 'A' && href) {
                    window.location.href = href;
                } else if (deleteTrigger.form) {
                    deleteTrigger.form.submit();
                }
            }
        }
    });
}

/**
 * Dynamic Class to Section Cascading Dropdowns
 */
function initClassSectionDropdowns() {
    const classSelects = document.querySelectorAll('select.class-select');
    classSelects.forEach(classSelect => {
        const targetSectionId = classSelect.getAttribute('data-target-section') || 'section_id';
        const sectionSelect = document.getElementById(targetSectionId);
        
        if (sectionSelect) {
            classSelect.addEventListener('change', () => {
                const classId = classSelect.value;
                if (!classId) {
                    sectionSelect.innerHTML = '<option value="">Select Section</option>';
                    return;
                }
                
                // Get project base url
                const basePath = window.APP_BASE_PATH || '';
                fetch(`${basePath}/api/student-search.php?action=get_sections&class_id=${classId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.sections) {
                            let options = '<option value="">Select Section</option>';
                            data.sections.forEach(sec => {
                                options += `<option value="${sec.id}">${sec.section_name}</option>`;
                            });
                            sectionSelect.innerHTML = options;
                        }
                    })
                    .catch(err => console.error('Error fetching sections:', err));
            });
        }
    });
}

/**
 * Print Element Helper
 */
function printDocument(elementId) {
    window.print();
}

/**
 * Universal Toast Notification Helper
 */
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    
    const toast = document.createElement('div');
    toast.className = `toast align-items-center text-white bg-${type === 'error' ? 'danger' : type} border-0 show shadow-lg mb-2`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;
    container.appendChild(toast);
    setTimeout(() => {
        toast.remove();
    }, 4000);
}
