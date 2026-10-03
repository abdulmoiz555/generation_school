/**
 * EduManage - Dashboard Analytics & Visualizations
 */

document.addEventListener('DOMContentLoaded', () => {
    initDashboardCharts();
});

function initDashboardCharts() {
    // 1. Students By Class Bar Chart
    const classChartCtx = document.getElementById('studentsByClassChart');
    if (classChartCtx && window.Chart) {
        const labels = window.DASHBOARD_DATA?.classLabels || ['Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5'];
        const counts = window.DASHBOARD_DATA?.classCounts || [8, 4, 3, 3, 2];

        new Chart(classChartCtx, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Students Enrolled',
                    data: counts,
                    backgroundColor: '#3b82f6',
                    borderRadius: 6,
                    maxBarThickness: 40
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    }
                }
            }
        });
    }

    // 2. Male vs Female Gender Ratio Chart
    const genderChartCtx = document.getElementById('genderRatioChart');
    if (genderChartCtx && window.Chart) {
        const maleCount = window.DASHBOARD_DATA?.maleStudents ?? 11;
        const femaleCount = window.DASHBOARD_DATA?.femaleStudents ?? 9;

        new Chart(genderChartCtx, {
            type: 'doughnut',
            data: {
                labels: ['Male Students', 'Female Students'],
                datasets: [{
                    data: [maleCount, femaleCount],
                    backgroundColor: ['#0284c7', '#ec4899'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                cutout: '70%'
            }
        });
    }

    // 3. Monthly Financials (Fees Collected vs Expenses)
    const financialChartCtx = document.getElementById('monthlyFinancialsChart');
    if (financialChartCtx && window.Chart) {
        new Chart(financialChartCtx, {
            type: 'line',
            data: {
                labels: ['May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
                datasets: [
                    {
                        label: 'Fee Collections ($)',
                        data: [1250, 1800, 2400, 3100, 902.50, 1500],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        fill: true,
                        tension: 0.35
                    },
                    {
                        label: 'Operating Expenses ($)',
                        data: [980, 1100, 1400, 1850, 2930, 1200],
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.05)',
                        fill: true,
                        tension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
}
