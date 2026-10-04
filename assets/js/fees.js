/**
 * EduManage - Fee Module Dynamic Calculation
 * Supports automatic Scholarship percentage concessions (Need-based, Orphan, Siblings, Merit)
 */

document.addEventListener('DOMContentLoaded', () => {
    initFeeCalculation();
});

function initFeeCalculation() {
    const feeForm = document.getElementById('feeCollectionForm');
    if (!feeForm) return;

    const baseAmountInput = document.getElementById('feeAmount');
    const discountInput = document.getElementById('feeDiscount');
    const fineInput = document.getElementById('feeFine');
    const totalInput = document.getElementById('feeTotal');
    const paidInput = document.getElementById('feePaid');
    const balanceInput = document.getElementById('feeBalance');
    const scholarshipSelect = document.getElementById('scholarshipSelect');
    const concessionBadge = document.getElementById('concessionBadge');

    function recalculate() {
        const base = parseFloat(baseAmountInput?.value || 0);
        const discount = parseFloat(discountInput?.value || 0);
        const fine = parseFloat(fineInput?.value || 0);

        const total = Math.max(0, base - discount + fine);
        if (totalInput) totalInput.value = total.toFixed(2);

        const paid = parseFloat(paidInput?.value || 0);
        const remaining = Math.max(0, total - paid);
        if (balanceInput) balanceInput.value = remaining.toFixed(2);
    }

    // Apply scholarship percentage on selection
    if (scholarshipSelect) {
        scholarshipSelect.addEventListener('change', () => {
            const opt = scholarshipSelect.options[scholarshipSelect.selectedIndex];
            const pct = parseFloat(opt.getAttribute('data-pct') || 0);
            const title = opt.getAttribute('data-title') || '';
            const base = parseFloat(baseAmountInput?.value || 0);

            if (pct > 0 && base > 0) {
                const discAmt = (base * (pct / 100));
                if (discountInput) discountInput.value = discAmt.toFixed(2);
                if (concessionBadge) {
                    concessionBadge.innerHTML = `<i class="fas fa-check-circle me-1"></i> ${pct}% ${title} applied: PKR ${discAmt.toFixed(2)} deducted`;
                    concessionBadge.style.display = 'block';
                }
            } else if (pct === 0) {
                if (discountInput && !discountInput.hasAttribute('data-manual')) {
                    discountInput.value = '0.00';
                }
                if (concessionBadge) concessionBadge.style.display = 'none';
            }
            recalculate();
            // Default paying amount to new total
            if (paidInput && totalInput) {
                paidInput.value = totalInput.value;
                recalculate();
            }
        });
    }

    [baseAmountInput, discountInput, fineInput, paidInput].forEach(input => {
        if (input) {
            input.addEventListener('input', () => {
                if (input === discountInput) {
                    input.setAttribute('data-manual', '1');
                }
                recalculate();
            });
        }
    });

    // Pay Full Amount button helper
    const payFullBtn = document.getElementById('payFullBtn');
    if (payFullBtn && totalInput && paidInput) {
        payFullBtn.addEventListener('click', (e) => {
            e.preventDefault();
            paidInput.value = totalInput.value;
            recalculate();
        });
    }
}
