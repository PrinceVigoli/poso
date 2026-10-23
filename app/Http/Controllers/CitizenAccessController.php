<?php
namespace App\Http\Controllers;

use App\Models\Violation;
use App\Support\CitizenAccess;
use Illuminate\Http\Request;

class CitizenAccessController extends Controller
{
    public function store(Request $request, Violation $violation)
    {
        abort_unless($request->user()->isAdmin() || ($request->user()->isEnforcer() && $violation->officer_id === $request->user()->id), 403);
        abort_unless($violation->citation && $violation->violator, 404);
        $data = $request->validate(['reason' => 'required|string|min:10|max:300', 'identity_checked' => 'accepted']);
        $code = CitizenAccess::issue($violation, $data['reason']);
        return redirect()->route($request->user()->isAdmin() ? 'violations.show' : 'enforcer.show', $violation)
            ->with('citizen_access', ['record_id' => $violation->id, 'staff_id' => $request->user()->id, 'code' => $code]);
    }
}
