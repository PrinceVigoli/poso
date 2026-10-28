<section class="card p-4 my-3">
    <h2 class="h6">Citizen access</h2>
    @php($issuedAccess = session()->pull('citizen_access'))
    @if(($issuedAccess['record_id'] ?? null) === $violation->id && ($issuedAccess['staff_id'] ?? null) === auth()->id())
        <div class="alert alert-success">
            <p class="mb-1">Give this code privately to the ticket holder. It is shown once and cannot be retrieved.</p>
            <p class="mb-1"><strong>TOP:</strong> {{ $violation->citation?->ticket_no }}</p>
            <p class="mb-1"><strong>Private access code:</strong> <code class="user-select-all">{{ $issuedAccess['code'] }}</code></p>
            <p class="mb-0"><strong>Citizen portal:</strong> {{ \App\Support\CitizenPortal::searchUrl() }}</p>
        </div>
    @endif
    <p class="small text-muted">The citizen needs the TOP number and private code to see this ticket's status. Verify their identity and authority to receive access before issuing a replacement. Replacing the code immediately revokes the old code and open citizen sessions.</p>
    @if($violation->citation && $violation->violator)
    <form method="POST" action="{{ route('violations.citizen-access', $violation) }}">
        @csrf
        <label for="access_reason" class="form-label">Reason for issuing or replacing the code</label>
        <input type="text" name="reason" id="access_reason" class="form-control mb-3" required minlength="10" maxlength="300" placeholder="e.g. Ticket holder requested a replacement; identity checked">
        <div class="form-check mb-3"><input class="form-check-input" type="checkbox" name="identity_checked" id="identity_checked" value="1" required><label class="form-check-label" for="identity_checked">I verified the recipient's identity and authority to access this ticket.</label></div>
        <button class="btn btn-outline-primary" type="submit">{{ $violation->public_access_hash ? 'Replace private access code' : 'Issue private access code' }}</button>
    </form>
    @else
    <p class="small text-muted mb-0">Citizen access is unavailable for this record.</p>
    @endif
</section>
