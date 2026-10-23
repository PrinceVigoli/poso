@extends('layouts.public')
@section('title', 'Privacy Notice')
@section('content')
<article class="card p-4">
    <h1 class="h3">Citizen portal privacy notice</h1>
    <p>The Public Order and Safety Office (POSO), Municipality of Luna, Apayao, operates this portal to let a ticket holder check whether POSO has verified settlement of their ticket.</p>
    <h2 class="h5">Information used and displayed</h2>
    <p>You submit a TOP number and private access code to open one ticket. This portal displays only its TOP number and current settlement status. It does not provide public searches by name, license or plate, or display personal profiles, offenses, photographs or other tickets.</p>
    <h2 class="h5">Access and security</h2>
    <p>A necessary session cookie maintains your access for up to 15 minutes. Close the result when finished. Codes are stored as hashes in the ticket database. Authorized staff can replace a code after verifying the recipient; replacement revokes earlier access. Protect your code as you would a password.</p>
    <p>Security controls limit repeated attempts. Staff code issuance and replacement are audited with staff identity, time, reason and IP address. Do not include access codes or unnecessary personal details in requests for assistance.</p>
    <p>Operational web-server logs may contain your IP address, request time and page path for security and troubleshooting. Access codes are submitted in the form body, not page URLs. Access to records, backups and logs is restricted to authorized personnel.</p>
    <h2 class="h5">Your records and questions</h2>
    <p>Contact POSO at the Municipality of Luna, Apayao, for a lost code, a disputed settlement status, access or correction requests, or to reach the municipal Data Protection Officer. Identity and authority to receive information must be checked before disclosure. Treasury handles payments and official receipts.</p>
    <p>Closing or expiring portal access does not delete the underlying official record. Official records, backups and audit records remain subject to the municipality's applicable records retention rules.</p>
    @if(config('portals.privacy_contact'))<p><strong>Privacy contact:</strong> {{ config('portals.privacy_contact') }}</p>@endif
    <a href="{{ route(request()->routeIs('citizen.*') ? 'citizen.search' : 'search') }}">Back to status check</a>
</article>
@endsection
