/**
 * EduManage - Attendance Module JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    initAttendanceControls();
});

function initAttendanceControls() {
    // 1. Mark All Present
    const markAllPresentBtn = document.getElementById('markAllPresentBtn');
    if (markAllPresentBtn) {
        markAllPresentBtn.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('input[type="radio"][value="Present"]').forEach(radio => {
                radio.checked = true;
            });
        });
    }

    // 2. Mark All Absent
    const markAllAbsentBtn = document.getElementById('markAllAbsentBtn');
    if (markAllAbsentBtn) {
        markAllAbsentBtn.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('input[type="radio"][value="Absent"]').forEach(radio => {
                radio.checked = true;
            });
        });
    }

    // 3. AJAX Attendance Form Submit
    const attendanceForm = document.getElementById('attendanceForm');
    if (attendanceForm) {
        attendanceForm.addEventListener('submit', (e) => {
            // Check if AJAX submit is requested
            const isAjax = attendanceForm.getAttribute('data-ajax') === 'true';
            if (!isAjax) return; // Allow normal POST submit

            e.preventDefault();
            const submitBtn = attendanceForm.querySelector('button[type="submit"]');
            const originalText = submitBtn ? submitBtn.innerHTML : 'Save';
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            }

            const formData = new FormData(attendanceForm);
            const basePath = window.APP_BASE_PATH || '';

            fetch(`${basePath}/api/attendance.php`, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showToast(data.message || 'Attendance saved successfully!', 'success');
                } else {
                    showToast(data.message || 'Failed to save attendance', 'error');
                }
            })
            .catch(err => {
                console.error('Attendance save error:', err);
                showToast('Network error while saving attendance', 'error');
            })
            .finally(() => {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            });
        });
    }
}
