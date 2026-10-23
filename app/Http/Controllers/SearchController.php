<?php
namespace App\Http\Controllers;

use App\Models\Violation;
use App\Support\CitizenAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class SearchController extends Controller
{
    private const FAILURE = 'Details could not be verified. Check your TOP number and private access code, or contact POSO.';

    public function index(Request $request)
    {
        return response()->view('search.index')->header('Cache-Control', 'no-store, private');
    }

    public function privacy()
    {
        return response()->view('search.privacy')->header('Cache-Control', 'no-store, private');
    }

    public function lookup(Request $request)
    {
        $request->session()->forget('public_record');
        $validator = Validator::make($request->only('ticket_no', 'access_code'), [
            'ticket_no' => 'required|string|max:100',
            'access_code' => 'required|string|max:64',
        ]);
        if ($validator->fails()) return $this->failed($request);
        $data = $validator->validated();
        $code = CitizenAccess::normalize($data['access_code']);
        $record = Violation::whereHas('violator')->whereHas('citation', fn ($q) => $q->where('ticket_no', trim($data['ticket_no'])))->first();
        // Compare fixed-length hashes without disclosing whether a TOP exists.
        $matches = hash_equals($record?->public_access_hash ?? str_repeat('0', 64), hash('sha256', $code));
        if (!$record || !$matches || !preg_match('/^[A-F0-9]{32}$/D', $code)) return $this->failed($request);
        $request->session()->regenerate();
        $request->session()->put('public_record', [
            'record_id' => $record->id,
            'fingerprint' => hash('sha256', $record->public_access_hash),
            'expires' => now()->addMinutes(15)->timestamp,
        ]);
        return redirect()->route($request->routeIs('citizen.*') ? 'citizen.results' : 'search.results');
    }

    public function results(Request $request)
    {
        $grant = $request->session()->get('public_record');
        $record = null;
        if (is_array($grant) && isset($grant['record_id'], $grant['fingerprint'], $grant['expires']) && $grant['expires'] > now()->timestamp) {
            $record = Violation::select('id', 'status', 'public_access_hash')
                ->whereHas('violator')->with('citation:id,violation_id,ticket_no,payment_status')->find($grant['record_id']);
        }
        if (!$record || !$record->public_access_hash || !hash_equals(hash('sha256', $record->public_access_hash), (string) ($grant['fingerprint'] ?? ''))) {
            $request->session()->forget('public_record');
            return redirect()->route($request->routeIs('citizen.*') ? 'citizen.search' : 'search')
                ->withErrors(['lookup' => 'Access has expired or is no longer available. Enter your TOP number and private access code again.']);
        }
        return response()->view('search.results', ['record' => $record])->header('Cache-Control', 'no-store, private');
    }

    public function forget(Request $request)
    {
        $request->session()->forget('public_record');
        $request->session()->regenerate();
        return redirect()->route($request->routeIs('citizen.*') ? 'citizen.search' : 'search');
    }

    private function failed(Request $request)
    {
        return redirect()->route($request->routeIs('citizen.*') ? 'citizen.search' : 'search')
            ->withErrors(['lookup' => self::FAILURE]);
    }
}
