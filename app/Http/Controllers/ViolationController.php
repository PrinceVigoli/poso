<?php

namespace App\Http\Controllers;

use App\Models\Violation;
use App\Models\Violator;
use App\Models\ViolationType;
use App\Models\Citation;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ViolationController extends Controller
{
    public function index(Request $request)
    {
        $request->validate([
            'search' => 'nullable|string|max:255', 'status' => 'nullable|in:pending,settled,dismissed',
            'payment_status' => 'nullable|in:unpaid,ready,paid,overdue,needs_review',
            'type' => 'nullable|integer', 'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('date_from') ? 'after_or_equal:date_from' : null]),
        ]);
        $query = Violation::with(['violator', 'violationType', 'officer', 'citation']);
        if ($request->filled('payment_status')) {
            $query->payable()->whereHas('citation', fn ($q) => match ($request->payment_status) {
                'unpaid' => $q->unverified(),
                'ready' => $q->readyToVerify(),
                'overdue' => $q->overdue(),
                'needs_review' => $q->unverified()->whereNull('fine_amount'),
                default => $q->where('payment_status', 'paid'),
            });
        }

        if ($request->filled('search')) {
            $q = trim($request->search);
            $normalized = strtolower(str_replace([' ', '-'], '', $q));
            $query->where(function ($w) use ($q, $normalized) {
                $w->where('person_snapshot->full_name', 'like', "%$q%")
                  ->orWhereRaw("REPLACE(REPLACE(LOWER(json_extract(person_snapshot, '$.vehicle_plate')),' ',''),'-','') LIKE ?", ["%{$normalized}%"]);
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('type')) {
            $key = ViolationType::withTrashed()->find($request->type)?->offense_key;
            $query->whereHas('violationType', fn ($q) => $q->where('offense_key', $key));
        }
        if ($request->filled('date_from')) {
            $query->where('violation_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('violation_date', '<', \Carbon\Carbon::parse($request->date_to)->addDay()->toDateString());
        }

        $violations     = $query->latest()->paginate(15)->withQueryString();
        $violationTypes = ViolationType::withTrashed()->orderBy('offense_name')->orderByDesc('id')->get()->unique('offense_key');
        return view('violations.index', compact('violations', 'violationTypes'));
    }

    public function create()
    {
        abort_unless(auth()->user()->isEnforcer(), 403);
        $violationTypes = ViolationType::orderBy('offense_name')->get();
        return view('violations.create_quick', compact('violationTypes'));
    }

    public function clearDraft(Request $request)
    {
        $request->session()->forget(['enforcer_preview', 'enforcer_input', 'enforcer_drafts', 'enforcer_uploads']);
        return redirect()->route('enforcer.create');
    }

    public function mySubmissions(Request $request)
    {
        $data = $request->validate(['search' => 'nullable|string|max:255']);
        $records = Violation::where('officer_id', $request->user()->id)->with(['violationType', 'citation'])
            ->when($data['search'] ?? null, fn ($q, $term) => $q->where('person_snapshot->full_name', 'like', '%'.$term.'%'))
            ->latest('id')->paginate(15)->withQueryString();
        return response()->view('violations.my_submissions', compact('records'))->header('Cache-Control', 'no-store, private');
    }

    public function store(Request $request)
    {
        abort_unless($request->user()->isEnforcer(), 403);
        return $this->storeQuickCite($request);
    }
    public function previewEnforcer(Request $request)
    {
        return $this->storeQuickCite($request);
    }

    protected function storeQuickCite(Request $request)
    {
        $data = $request->validate([
            'top_number' => 'required|string|max:50|unique:citations,ticket_no',
            'minor_photos' => 'nullable|array|max:5',
            'minor_photos.*' => 'required|image|mimes:jpg,jpeg,png,webp|max:5120',
            'photo_token' => 'nullable|string|size:40',
            'remove_photos' => 'nullable|boolean',
            'full_name' => 'required|string|max:255',
            'license_no' => 'nullable|string|max:50',
            'vehicle_plate' => 'nullable|string|max:20',
            'vehicle_type' => 'nullable|string|max:50',
            'address' => 'required|string|max:255',
            'confiscated_id' => ['required', \Illuminate\Validation\Rule::in(config('portals.confiscated_ids'))],
            'additional_info' => 'nullable|string|max:500',
            'contact_no' => 'nullable|string|max:20',
            'birthdate' => 'nullable|date_format:Y-m-d|before_or_equal:today',
            'violation_type_id' => 'required|exists:violation_types,id,deleted_at,NULL',
            'matched_violator_id' => 'nullable|exists:violators,id',
            'confirm_new' => 'nullable|boolean',
            'confirm_duplicate' => 'nullable|boolean',
        ]);
        $uploads = collect($request->session()->get('enforcer_uploads', []))
            ->filter(fn ($upload) => $upload['expires_at'] >= now()->timestamp);
        $request->session()->put('enforcer_uploads', $uploads->all());
        $photoToken = null;
        $photos = [];
        if (!empty($data['photo_token']) && !$request->boolean('remove_photos') && !$request->hasFile('minor_photos')) {
            $upload = $request->session()->get('enforcer_uploads.'.$data['photo_token']);
            if (!$upload || $upload['user_id'] !== $request->user()->id || $upload['expires_at'] < now()->timestamp) {
                throw ValidationException::withMessages(['minor_photos' => 'Please select the pictures again; the previous upload has expired.']);
            }
            $photos = $upload['paths'];
            $photoToken = $data['photo_token'];
        }
        if ($request->hasFile('minor_photos')) {
            if ($uploads->count() >= 10) {
                throw ValidationException::withMessages(['minor_photos' => 'You have ten uploaded picture sets. Clear drafts before uploading more.']);
            }
            $photos = [];
            foreach ($request->file('minor_photos') as $photo) {
                $path = $photo->store('minor-photos', 'local');
                if (!$path) {
                    throw ValidationException::withMessages(['minor_photos' => 'The pictures could not be uploaded. Please try again.']);
                }
                $photos[] = $path;
            }
        }
        unset($data['minor_photos'], $data['photo_token'], $data['remove_photos']);
        if ($photos) {
            $data['photo_token'] = $photoToken ?? Str::random(40);
            if (!$photoToken) {
                $request->session()->put('enforcer_uploads.'.$data['photo_token'], [
                    'paths' => $photos, 'user_id' => $request->user()->id, 'expires_at' => now()->addHour()->timestamp,
                ]);
            }
        }
        $data['full_name'] = trim($data['full_name']);
        $request->session()->forget('enforcer_preview');
        $request->session()->put('enforcer_input', $data);
        $candidates = $this->findSimilarViolators($data['full_name']);
        $violator = null;
        if (!empty($data['matched_violator_id'])) {
            if ($request->boolean('confirm_new') || !$candidates->contains('id', (int) $data['matched_violator_id'])) {
                throw ValidationException::withMessages(['full_name' => 'Please choose a matching profile or explicitly add a new person.']);
            }
            $violator = Violator::findOrFail($data['matched_violator_id']);
        } elseif (!$request->boolean('confirm_new') && $candidates->isNotEmpty()) {
            return view('violations.confirm_match', [
                'full_name' => $data['full_name'], 'violationTypeId' => $data['violation_type_id'],
                'candidates' => $candidates, 'inputData' => $data,
            ]);
        }
        $todaysViolations = $violator ? $violator->violations()->where('violation_date', today())->with(['violationType', 'citation'])->get() : collect();
        if ($todaysViolations->isNotEmpty() && !$request->boolean('confirm_duplicate')) {
            return view('violations.confirm_duplicate', [
                'violator' => $violator, 'violationTypeId' => $data['violation_type_id'],
                'newOffense' => ViolationType::findOrFail($data['violation_type_id']),
                'todaysViolations' => $todaysViolations, 'inputData' => $data,
            ]);
        }

        $profile = ['full_name' => $violator?->full_name ?? $data['full_name']];
        foreach (['license_no', 'vehicle_plate', 'vehicle_type', 'address', 'contact_no', 'birthdate'] as $field) {
            $profile[$field] = $data[$field] ?? null;
        }
        $type = ViolationType::findOrFail($data['violation_type_id']);
        $draft = [
            'schema_version' => 2, 'input' => $data, 'candidate_ids' => $candidates->pluck('id')->all(),
            'token' => Str::random(40), 'user_id' => $request->user()->id,
            'profile' => $profile, 'violator_id' => $violator?->id,
            'violation_type_id' => $type->id, 'fine_amount' => (string) $type->fine_amount,
            'violation_date' => today()->toDateString(), 'expires_at' => now()->addMinutes(30)->timestamp,
            'today_ids' => $todaysViolations->pluck('id')->all(),
            'confiscated_id' => $data['confiscated_id'],
            'additional_info' => $data['additional_info'] ?? null,
            'top_number' => $data['top_number'], 'minor_photos' => $photos,
        ];
        $drafts = collect($request->session()->get('enforcer_drafts', []))
            ->filter(fn ($saved) => $saved['expires_at'] > now()->timestamp);
        if ($drafts->count() >= 10) {
            throw ValidationException::withMessages(['preview' => 'You have ten open previews. Submit or clear them before starting another.']);
        }
        $drafts->put($draft['token'], $draft);
        $request->session()->put('enforcer_drafts', $drafts->all());
        $request->session()->put('enforcer_preview', $draft);
        return redirect()->route('enforcer.review');
    }

    public function reviewEnforcer(Request $request)
    {
        $token = $request->input('preview_token', $request->input('draft'));
        $draft = $token ? $request->session()->get('enforcer_drafts.'.$token) : $request->session()->get('enforcer_preview');
        if (!$draft || ($draft['schema_version'] ?? null) !== 2 || $draft['user_id'] !== $request->user()->id || $draft['expires_at'] < now()->timestamp) {
            return redirect()->route('enforcer.create')->withErrors(['preview' => 'Please enter your details and preview them again.']);
        }
        $type = ViolationType::findOrFail($draft['violation_type_id']);
        $violator = $draft['violator_id'] ? Violator::findOrFail($draft['violator_id']) : null;
        return view('violations.preview_enforcer', compact('draft', 'type', 'violator'));
    }

    public function editEnforcer(Request $request)
    {
        $token = $request->input('preview_token', $request->session()->get('enforcer_preview.token'));
        $data = $token ? $request->session()->get('enforcer_drafts.'.$token.'.input', []) : $request->session()->get('enforcer_input', []);
        if ($token) { $request->session()->forget('enforcer_drafts.'.$token); }
        $request->session()->put('enforcer_input', $data);
        $request->session()->forget('enforcer_preview');
        return redirect()->route('enforcer.create')->withInput($data);
    }

    public function confirmEnforcer(Request $request)
    {
        $request->validate(['preview_token' => 'required|string', 'confirmed' => 'accepted']);
        $token = $request->input('preview_token', $request->input('draft'));
        $draft = $token ? $request->session()->get('enforcer_drafts.'.$token) : $request->session()->get('enforcer_preview');
        if (!$draft || ($draft['schema_version'] ?? null) !== 2 || $draft['user_id'] !== $request->user()->id
            || !hash_equals($draft['token'], $request->input('preview_token'))
            || $draft['expires_at'] < now()->timestamp || $draft['violation_date'] !== today()->toDateString()) {
            return redirect()->route('enforcer.create')->withErrors(['preview' => 'Please preview your details again before submitting.']);
        }

        try {
            $violation = DB::transaction(function () use ($draft) {
                if (empty($draft['top_number']) || Citation::where('ticket_no', $draft['top_number'])->exists()) {
                    throw ValidationException::withMessages(['preview' => 'Please edit and enter an unused TOP ticket number.']);
                }
                $type = ViolationType::lockForUpdate()->findOrFail($draft['violation_type_id']);
                if ((string) $type->fine_amount !== $draft['fine_amount']) {
                    throw ValidationException::withMessages(['preview' => 'The offense fine has changed. Please preview the details again.']);
                }
                $violator = $draft['violator_id'] ? Violator::lockForUpdate()->findOrFail($draft['violator_id']) : null;
                if (!$violator && $this->findSimilarViolators($draft['profile']['full_name'])->pluck('id')->diff($draft['candidate_ids'])->isNotEmpty()) {
                    throw ValidationException::withMessages(['preview' => 'Another matching profile was added. Please preview again and check the matches.']);
                }
                if ($violator && $violator->violations()->where('violation_date', today())->whereNotIn('id', $draft['today_ids'])->exists()) {
                    throw ValidationException::withMessages(['preview' => 'Another violation was recorded today. Please preview again to review it.']);
                }
                if (!$violator) {
                    $violator = Violator::create($draft['profile']);
                    AuditLog::record('created', 'violators', "Added violator: {$violator->full_name}");
                }
                $violation = Violation::create([
                    'violator_id' => $violator->id, 'officer_id' => auth()->id(),
                    'violation_type_id' => $type->id, 'violation_date' => $draft['violation_date'],
                    'location' => null, 'status' => 'pending',
                    'person_snapshot' => $draft['profile'], 'snapshot_source' => 'captured', 'submission_token' => $draft['token'],
                    'confiscated_id' => $draft['confiscated_id'] ?? null,
                    'remarks' => $draft['additional_info'] ?? null,
                    'minor_photos' => $draft['minor_photos'] ?? [],
                ]);
                $this->issueCitation($violation, $type->id, $draft['top_number']);
                AuditLog::record('created', 'violations', "Confirmed enforcer submission #{$violation->id} for violator ID {$violator->id}");
                return $violation;
            });
        } catch (\Illuminate\Database\UniqueConstraintViolationException $exception) {
            if (Citation::where('ticket_no', $draft['top_number'])->exists()) {
                throw ValidationException::withMessages(['preview' => 'This TOP ticket number was just used. Please edit and enter an unused number.']);
            }
            throw $exception;
        }
        $request->session()->forget('enforcer_drafts.'.$draft['token']);
        if ($request->session()->get('enforcer_preview.token') === $draft['token']) {
            $request->session()->forget(['enforcer_preview', 'enforcer_input']);
        }
        return redirect()->route('enforcer.show', $violation)->with('success', 'Details confirmed. Violation recorded.');
    }

    // Looks for existing violators with a similarly-spelled name (typo
    // tolerance) so the enforcer isn't forced to create a duplicate record.
    protected function findSimilarViolators(string $name, int $limit = 5)
    {
        $target = Str::lower(Str::squish($name));
        $prefix = mb_substr($target, 0, 3);
        // Exact matches rank first. Score at most 200 candidates in PHP.
        // Prefix LIKE can use the normalized-name index. It is only a suggestion,
        // never identity proof; the enforcer always chooses an existing/new person.
        return Violator::query()->select('id', 'full_name', 'address', 'vehicle_type', 'vehicle_plate')
            ->where('normalized_name', 'like', addcslashes($prefix, '%_\\').'%')
            ->orderByRaw('CASE WHEN normalized_name = ? THEN 0 ELSE 1 END', [$target])
            ->orderBy('id')->limit(200)->get()
            ->map(function ($v) use ($target) {
                similar_text($target, Str::lower(Str::squish($v->full_name)), $pct);
                $v->match_pct = (int) round($pct);
                return $v;
            })->filter(fn ($v) => $v->match_pct >= 55)->sortByDesc('match_pct')->take(20)->values();
    }

    protected function issueCitation(Violation $violation, int $violationTypeId, string $ticketNumber): void
    {
        $type = ViolationType::find($violationTypeId);
        Citation::create([
            'violation_id'   => $violation->id,
            'ticket_no'      => $ticketNumber,
            'fine_amount'    => $type->fine_amount,
            'due_date'       => now()->addDays(15)->toDateString(),
            'payment_status' => 'pending',
        ]);
    }

    public function minorPhoto(Request $request, Violation $violation, int $photo)
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->isEnforcer() && $violation->officer_id === $request->user()->id), 403);
        $path = $violation->minor_photos[$photo] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function draftPhoto(Request $request, string $token, int $photo)
    {
        $upload = $request->session()->get('enforcer_uploads.'.$token);
        abort_unless($upload && $upload['user_id'] === $request->user()->id && $upload['expires_at'] >= now()->timestamp, 404);
        $path = $upload['paths'][$photo] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);
        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function show(Violation $violation)
    {
        // Enforcers are limited to the citation form; they may only view
        // the receipt for a violation they personally issued.
        if (auth()->user()->isEnforcer() && $violation->officer_id !== auth()->id()) {
            abort(403, 'You can only view records you submitted.');
        }

        $violation->load(['violator', 'violationType', 'officer', 'citation']);
        if (auth()->user()->isEnforcer()) {
            return response()->view('violations.enforcer_submitted', compact('violation'))->header('Cache-Control', 'no-store, private');
        }
        return response()->view('violations.show', compact('violation'))->header('Cache-Control', 'no-store, private');
    }

}
