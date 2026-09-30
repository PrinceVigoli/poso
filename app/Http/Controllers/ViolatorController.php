<?php

namespace App\Http\Controllers;

use App\Models\Violator;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class ViolatorController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->input('status', 'active');
        if (! in_array($status, ['active', 'archived', 'all'], true)) {
            $status = 'active';
        }
        $query = Violator::withCount(['violations', 'unpaidViolations', 'countedViolations']);
        if ($status === 'archived') {
            $query->archived();
        } elseif ($status === 'active') {
            $query->active();
        }

        if ($request->filled('search')) {
            $q = $request->search;
            $query->where(function ($w) use ($q) {
                $w->where(fn ($names) => \App\Support\PersonName::search($names, 'full_name', $q))
                  ->orWhere('license_no', 'like', "%$q%")
                  ->orWhere('vehicle_plate', 'like', "%$q%")
                  ->orWhereHas('violations', fn ($v) => $v->where('person_snapshot->vehicle_plate', 'like', "%$q%"));
            });
        }

        $violators = $query->latest()->paginate(15)->withQueryString();
        $counts = ['active' => Violator::active()->count(), 'archived' => Violator::archived()->count(), 'all' => Violator::count()];
        return view('violators.index', compact('violators', 'status', 'counts'));
    }

    public function show(Violator $violator)
    {
        $violations = $violator->violations()
            ->with(['violationType', 'officer', 'citation'])
            ->latest()
            ->orderByDesc('id')
            ->get();

        // Derived from the rows already in memory. Calling isRepeatOffender()
        // and isArchived() from the view ran six more queries per page load.
        $countedViolations = $violations->where('status', '!=', 'dismissed')->count();
        $isRepeatOffender = $countedViolations >= Violator::repeatOffenderThreshold();
        $isArchived = $violations->isNotEmpty() && ! $violations->contains(
            fn ($v) => $v->status !== 'dismissed' && $v->citation?->payment_status !== 'paid'
        );

        return view('violators.show', compact('violator', 'violations', 'countedViolations', 'isRepeatOffender', 'isArchived'));
    }

}
