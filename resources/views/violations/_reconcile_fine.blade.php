@if($violation->status !== 'dismissed' && $violation->citation && $violation->citation->fine_amount === null && $violation->citation->payment_status !== 'paid')
<section class="card p-3 mt-3"><h2 class="h5">Amount needs review</h2>
<p>This record has no configured fine. Check the ordinance that applied to the incident and record its amount before verifying settlement.</p>
<form method="POST" action="{{ route('violations.reconcile-fine', $violation) }}">@csrf
<input type="hidden" name="verification_version" value="{{ $violation->citation->verification_version }}">
<label class="form-label" for="reconcileAmount">Applicable fine (₱)</label><input class="form-control mb-3" id="reconcileAmount" name="fine_amount" type="number" min="0" max="999999.99" step="0.01" value="{{ old('fine_amount') }}" required>
<label class="form-label" for="ordinanceReference">Ordinance reference</label><input class="form-control mb-3" id="ordinanceReference" name="ordinance_reference" maxlength="255" value="{{ old('ordinance_reference') }}" required>
<label class="form-label" for="fineReason">Reason and supporting details</label><textarea class="form-control mb-3" id="fineReason" name="reason" minlength="10" maxlength="1000" required>{{ old('reason') }}</textarea>
<div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="fine_confirmed" id="fineConfirmed" value="1" required><label class="form-check-label" for="fineConfirmed">I checked the ordinance and confirm this amount applies to the incident.</label></div>
<button class="btn btn-primary" type="submit">Save fine with audit history</button></form></section>
@endif
