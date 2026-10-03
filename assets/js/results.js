/**
 * EduManage - Examination & Marks Calculation
 */

document.addEventListener('DOMContentLoaded', () => {
    initMarksCalculations();
});

function initMarksCalculations() {
    const marksRows = document.querySelectorAll('.marks-entry-row');
    if (!marksRows.length) return;

    marksRows.forEach(row => {
        const obtainedInput = row.querySelector('.obtained-marks');
        const maxMarksInput = row.querySelector('.max-marks') || { value: 100 };
        const percentDisplay = row.querySelector('.percent-display');
        const gradeDisplay = row.querySelector('.grade-display');
        const statusDisplay = row.querySelector('.status-display');

        function updateRow() {
            const obtained = parseFloat(obtainedInput.value) || 0;
            const max = parseFloat(maxMarksInput.value) || 100;

            if (max <= 0) return;

            const percentage = Math.min(100, Math.max(0, (obtained / max) * 100));
            if (percentDisplay) percentDisplay.textContent = percentage.toFixed(1) + '%';

            let grade = 'F';
            let pass = false;

            if (percentage >= 90) { grade = 'A+'; pass = true; }
            else if (percentage >= 80) { grade = 'A'; pass = true; }
            else if (percentage >= 70) { grade = 'B'; pass = true; }
            else if (percentage >= 60) { grade = 'C'; pass = true; }
            else if (percentage >= 50) { grade = 'D'; pass = true; }
            else { grade = 'F'; pass = false; }

            if (gradeDisplay) gradeDisplay.textContent = grade;
            if (statusDisplay) {
                if (pass) {
                    statusDisplay.className = 'badge bg-success status-display';
                    statusDisplay.textContent = 'Pass';
                } else {
                    statusDisplay.className = 'badge bg-danger status-display';
                    statusDisplay.textContent = 'Fail';
                }
            }
        }

        if (obtainedInput) {
            obtainedInput.addEventListener('input', updateRow);
        }
    });
}
