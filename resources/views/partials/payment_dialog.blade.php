@if(auth()->user()->isAdmin())
<dialog id="paymentDialog" class="payment-dialog" aria-labelledby="paymentTitle">
<form id="treasuryVerificationForm" method="POST">
    @csrf
    <h2 id="paymentTitle" class="h5 fw-semibold mb-3">Verify Treasury settlement</h2>
    <p class="text-muted small">Check the official Treasury receipt against this incident. POSO records verification; payment is collected by Treasury.</p>
    <dl class="review-details"><dt>Violation record</dt><dd id="paymentTicket"></dd><dt>Citizen</dt><dd id="paymentPerson"></dd><dt>Address for this incident</dt><dd id="paymentAddress" class="text-break"></dd><dt>Offense</dt><dd id="paymentOffense"></dd><dt>Required fine</dt><dd id="paymentFine" class="fw-semibold"></dd></dl>
    <label for="treasuryReceipt" class="form-label">Treasury receipt number</label>
    <input type="text" id="treasuryReceipt" name="treasury_receipt_no" class="form-control mb-3" maxlength="100" required value="{{ old('treasury_receipt_no') }}">
    <label for="receiptDate" class="form-label">Date printed on the Treasury receipt</label>
    <input type="date" id="receiptDate" name="receipt_date" class="form-control mb-3" required max="{{ today()->toDateString() }}" value="{{ old('receipt_date') }}">
    <label for="receiptTotal" class="form-label">Total amount printed on the receipt (₱)</label>
    <input type="number" id="receiptTotal" name="receipt_total" class="form-control mb-3" min="0" max="9999999999.99" step="0.01" required value="{{ old('receipt_total') }}">
    <button class="btn btn-outline-primary w-100 mb-2" type="button" id="reviewReceipt">Review receipt allocations</button>
    <div id="receiptAllocations" class="small mb-3" role="status" aria-live="polite">Enter the receipt number to review any existing allocations.</div>
    @if(config('portals.allow_shared_receipts'))<div class="form-check mb-3"><input type="checkbox" class="form-check-input" name="reuse_receipt" value="1" id="reuseReceipt"><label for="reuseReceipt" class="form-check-label">I reviewed the allocations and confirmed this receipt also covers this violation for the same person.</label></div>@endif
    <div class="form-check mb-3"><input type="checkbox" class="form-check-input" id="receiptVerified" name="receipt_verified" value="1" required><label for="receiptVerified" class="form-check-label">I checked the Treasury receipt and verified this settlement.</label></div>
    <p class="text-muted small">POSO verification is recorded when this form is submitted.</p>
    <button type="submit" class="btn btn-primary w-100 mb-2" id="paymentConfirm">Verify Settlement</button>
    <button type="button" class="btn btn-outline-secondary w-100" id="paymentCancel">Cancel</button>
</form></dialog>
<script>
(() => {
    const dialog = document.getElementById('paymentDialog'), form = document.getElementById('treasuryVerificationForm'), receipt = document.getElementById('treasuryReceipt'), status = document.getElementById('receiptAllocations');
    let recordId, generation = 0;
    const resetReview = () => { generation++; status.textContent = 'Review the allocations for this receipt before confirming shared coverage.'; const shared = document.getElementById('reuseReceipt'); if (shared) shared.checked = false; };
    document.querySelectorAll('form[data-payment-ticket]').forEach(trigger => trigger.addEventListener('submit', event => {
        event.preventDefault(); if (recordId && recordId !== trigger.dataset.violationId) form.reset();
        recordId = trigger.dataset.violationId; form.action = trigger.action;
        for (const [id, key] of [['paymentTicket','paymentTicket'],['paymentPerson','paymentPerson'],['paymentAddress','paymentAddress'],['paymentOffense','paymentOffense'],['paymentFine','paymentAmount']]) document.getElementById(id).textContent = trigger.dataset[key] || 'Not recorded';
        document.getElementById('receiptVerified').checked = false; document.getElementById('paymentConfirm').disabled = false;
        resetReview(); dialog.showModal(); receipt.focus();
    }));
    receipt.addEventListener('input', resetReview);
    document.getElementById('reviewReceipt').addEventListener('click', async () => {
        const current = ++generation; status.textContent = 'Checking receipt allocations…';
        try {
            const response = await fetch(@json(route('receipts.review')), {method:'POST', headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':form.querySelector('[name="_token"]').value}, body:JSON.stringify({receipt_no:receipt.value,violation_id:recordId})});
            if (!response.ok) throw new Error('Review failed'); const result = await response.json(); if (current !== generation) return;
            const money = value => value === null ? 'Unknown' : new Intl.NumberFormat('en-PH',{style:'currency',currency:'PHP'}).format(value);
            status.replaceChildren(); const line = text => {const p=document.createElement('p'); p.className='mb-1'; p.textContent=text; status.append(p);};
            if (!result.found) {line('No existing allocations. Check the receipt before recording its date and total.');return;}
            line('Previously recorded total: '+money(result.total)+'. Allocated: '+money(result.allocated)+'. Remaining before this violation: '+money(result.remaining)+'.');
            if (result.receipt_date) line('Recorded Treasury date: '+result.receipt_date);
            for (const allocation of result.allocations) line('Violation #'+allocation.reference+': '+money(allocation.amount));
            if (!result.same_person) line('This receipt is assigned to another person. Resolve that verification before proceeding.');
            if (result.unknown_amount) line('An existing allocation has an unknown fine and needs reconciliation.');
            line('The server checks the latest allocations again when you submit.');
        } catch {if (current===generation) status.textContent='Could not review this receipt. Check the number and try again.';}
    });
    document.getElementById('paymentCancel').addEventListener('click',()=>dialog.close());
    form.addEventListener('submit',()=>{document.getElementById('paymentConfirm').disabled=true;});
})();
</script>
@endif
