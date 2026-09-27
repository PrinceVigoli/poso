<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\AuditLog;
use App\Models\ViolationType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminController extends Controller
{
    // ── Users ──────────────────────────────────────────────────

    public function users()
    {
        $users = User::latest()->paginate(25);
        return view('admin.users', compact('users'));
    }

    public function createUser()
    {
        return view('admin.user_form', ['user' => null]);
    }

    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'username' => 'required|string|unique:users|max:50',
            'email'    => 'required|email|unique:users',
            'password' => 'required|string|min:15|max:128|confirmed',
            'role'     => 'required|in:admin,enforcer',
        ]);
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        AuditLog::record('created', 'users', "Created user: {$user->username} ({$user->role})");
        return redirect()->route('admin.users')->with('success', 'User created successfully.');
    }

    public function editUser(User $user)
    {
        return view('admin.user_form', compact('user'));
    }

    public function updateUser(Request $request, User $user)
    {
        $data = $request->validate([
            'name'      => 'required|string|max:255',
            'username'  => 'required|string|max:50|unique:users,username,' . $user->id,
            'email'     => 'required|email|unique:users,email,' . $user->id,
            'role'      => 'required|in:admin,enforcer',
            'is_active' => 'boolean',
        ]);
        if ($request->filled('password')) {
            $request->validate(['password' => 'string|min:15|max:128|confirmed']);
            $data['password'] = Hash::make($request->password);
        }
        $data['is_active'] = $request->boolean('is_active');
        DB::transaction(function () use ($user, $data) {
        // Lock a stable set in ID order so simultaneous demotions cannot remove every admin.
        User::orderBy('id')->lockForUpdate()->get(['id']);
        $user = User::findOrFail($user->id);
        if ($user->isAdmin() && $user->is_active
            && ($data['role'] !== 'admin' || ! $data['is_active'])
            && ! User::where('role', 'admin')->where('is_active', true)->where('id', '!=', $user->id)->exists()) {
            throw ValidationException::withMessages([
                'role' => 'Keep at least one active administrator before changing this account.',
            ]);
        }
        $user->update($data);
        AuditLog::record('updated', 'users', "Updated user: {$user->username}");
        }, 3);
        return redirect()->route('admin.users')->with('success', 'User updated successfully.');
    }

    // ── Violation Types ────────────────────────────────────────

    public function violationTypes()
    {
        $types = ViolationType::withCount('violations')->orderBy('category')->get();
        return view('admin.violation_types', compact('types'));
    }

    public function storeViolationType(Request $request)
    {
        $data = $request->validate([
            'offense_name' => 'required|string|max:255',
            'category'     => ['required', \Illuminate\Validation\Rule::in(config('portals.offense_categories'))],
            'fine_amount'  => 'nullable|numeric|min:0|max:999999.99',
            'description'  => 'nullable|string|max:500',
        ]);
        ViolationType::create($data);
        AuditLog::record('created', 'violation_types', "Added offense: {$data['offense_name']}");
        return redirect()->route('admin.violation-types')->with('success', 'Violation type added.');
    }

    public function updateViolationType(Request $request, ViolationType $violationType)
    {
        $data = $request->validate([
            'offense_name' => 'required|string|max:255',
            'category'     => ['required', \Illuminate\Validation\Rule::in(config('portals.offense_categories'))],
            'fine_amount'  => 'nullable|numeric|min:0|max:999999.99',
            'description'  => 'nullable|string|max:500',
        ]);
        DB::transaction(function () use ($violationType, $data) {
            $violationType = ViolationType::lockForUpdate()->findOrFail($violationType->id);
            if ($violationType->violations()->withTrashed()->exists()) {
                // Issued records keep the ordinance wording and fine from their term.
                $violationType->delete();
                ViolationType::create($data + ['offense_key' => $violationType->offense_key, 'is_active' => $violationType->is_active]);
            } else {
                $violationType->update($data);
            }
        });
        AuditLog::record('updated', 'violation_types', "Updated offense: {$data['offense_name']}");
        return redirect()->route('admin.violation-types')->with('success', 'Violation type updated.');
    }

    // ── Audit Log ──────────────────────────────────────────────

    public function auditLogs(Request $request)
    {
        $query = AuditLog::with('user');
        if ($request->filled('module')) {
            $query->where('module', $request->module);
        }
        $logs = $query->latest()->paginate(20)->withQueryString();
        return view('admin.audit_logs', compact('logs'));
    }

    public function updateViolationTypeStatus(Request $request, ViolationType $violationType)
    {
        $data = $request->validate(['is_active' => 'required|boolean']);
        DB::transaction(function () use ($violationType, $data) {
            $type = ViolationType::lockForUpdate()->findOrFail($violationType->id);
            if ($type->is_active !== (bool) $data['is_active']) {
                $type->update(['is_active' => (bool) $data['is_active']]);
                $status = $type->is_active ? 'Active' : 'Inactive';
                AuditLog::record('updated', 'violation_types', "Set offense {$type->offense_name} to {$status}.");
            }
        }, 3);
        return redirect()->route('admin.violation-types')->with('success', 'Offense status updated.');
    }
}
