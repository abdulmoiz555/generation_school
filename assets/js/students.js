/**
 * EduManage - Student Module JavaScript
 */

document.addEventListener('DOMContentLoaded', () => {
    initStudentSearch();
});

function initStudentSearch() {
    const searchInput = document.getElementById('studentSearchInput');
    if (!searchInput) return;

    let debounceTimer;
    searchInput.addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            const query = searchInput.value.trim().toLowerCase();
            const rows = document.querySelectorAll('#studentTableBody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(query)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }, 200);
    });
}
