<?php
namespace App\Http\Controllers;

use App\Models\Violation;
use App\Models\Violator;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SearchController extends Controller
{
    /** A single search may not return more than this many records. */
    private const MAX_RESULTS = 50;

    /** How long a set of results stays open before the citizen searches again. */
    private const GRANT_MINUTES = 15;

    public function index(Request $request)
    {
        return response()->view('search.index')->header('Cache-Control', 'no-store, private');
    }

    public function lookup(Request $request)
    {
        $request->session()->forget('public_record');
        $data = $request->validate(['query' => 'required|string|max:255']);

        $term = trim($data['query']);
        $name = $this->normalize($term);
        if ($name === '') {
            return back()->withErrors(['lookup' => 'Enter the name, licence number or plate number written on the violation record.']);
        }

        $request->session()->put('public_record', [
            'name' => $name,
            // A licence or plate narrows to one person where a name does not,
            // so the same box accepts any of them and matches on all three.
            'reference' => Violator::squashReference($term),
            'entered' => $term,
            'expires' => now()->addMinutes(self::GRANT_MINUTES)->timestamp,
        ]);

        return redirect()->route($request->routeIs('citizen.*') ? 'citizen.results' : 'search.results');
    }

    public function results(Request $request)
    {
        $grant = $request->session()->get('public_record');
        if (! $grant || $grant['expires'] <= now()->timestamp) {
            $request->session()->forget('public_record');
            return redirect()->route($request->routeIs('citizen.*') ? 'citizen.search' : 'search')
                ->withErrors(['lookup' => 'Your search has expired. Enter the full name again.']);
        }

        $records = $this->recordsFor($grant['name'], $grant['reference'] ?? null);

        return response()->view('search.results', [
            'records' => $records,
            'entered' => $grant['entered'],
            'limited' => $records->count() >= self::MAX_RESULTS,
        ])->header('Cache-Control', 'no-store, private');
    }

    public function forget(Request $request)
    {
        $request->session()->forget('public_record');
        return redirect()->route($request->routeIs('citizen.*') ? 'citizen.search' : 'search');
    }

    /**
     * Matches the violator's normalized name, licence or plate. All three are
     * maintained on save and indexed. One box accepts any of them because a
     * citizen holding a ticket should not have to know which field we store it
     * in. A name is not proof of identity — several people share one — so the
     * view warns whenever more than one record comes back.
     */
    private function recordsFor(string $name, ?string $reference)
    {
        return Violation::with(['violationType', 'citation'])
            ->whereHas('violator', function ($violator) use ($name, $reference) {
                $violator->whereIn('normalized_name', [$name, Str::lower(\App\Support\PersonName::display($name))]);
                if ($reference !== null) {
                    $violator->orWhere('normalized_license', $reference)
                             ->orWhere('normalized_plate', $reference);
                }
            })
            ->orderByDesc('violation_date')->orderByDesc('id')
            ->limit(self::MAX_RESULTS)
            ->get();
    }

    private function normalize(string $name): string
    {
        return \App\Support\PersonName::normalized($name);
    }
}
