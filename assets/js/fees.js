/**
 * EduManage - Fee Module Dynamic Calculation
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

    [baseAmountInput, discountInput, fineInput, paidInput].forEach(input => {
        if (input) {
            input.addEventListener('input', recalculate);
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
