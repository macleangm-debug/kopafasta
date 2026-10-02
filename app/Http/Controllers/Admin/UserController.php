<?php

namespace App\Http\Controllers\Admin;

use App\Models\Department;
use App\Models\User;
use App\Services\CreditAuthorityService;
use App\Services\CreditDeskAssignmentService;
use App\Services\RoleService;
use App\Services\UserAccountService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends ResourceController
{
    protected string $model = User::class;
    protected string $routePrefix = 'admin.users';
    protected string $viewFolder = 'users';
    protected string $singular = 'user';

    public function __construct(
        private RoleService $roles,
        private UserAccountService $accounts,
    ) {
    }

    protected function rules(?Model $model = null): array
    {
        $id = $model?->id;
        $allowedRoles = $this->roles->userFormRoles();

        return [
            'name'            => ['required', 'string', 'max:150'],
            'email'           => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'phone'           => ['nullable', 'string', 'max:30'],
            'roles'           => ['required', 'array', 'min:1'],
            'roles.*'         => ['string', Rule::in($allowedRoles)],
            'department_ids'  => ['nullable', 'array'],
            'department_ids.*'=> ['integer', 'exists:departments,id'],
            'is_active'       => ['nullable', 'boolean'],
            'password'        => [$id ? 'nullable' : 'nullable', 'string', 'min:6', 'confirmed'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        $roleOptions = $this->roles->userFormRoleLabels();

        return [
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'departmentRows' => Department::query()->orderBy('name')->get(['id', 'name', 'code']),
            'roles'       => $roleOptions,
            'roleDuties'  => collect($this->roles->userFormRoles())
                ->mapWithKeys(fn (string $code) => [$code => $this->roles->duty($code)])
                ->all(),
            'roleDesks'   => collect($this->roles->userFormRoles())
                ->mapWithKeys(function (string $code) {
                    $desk = app(CreditDeskAssignmentService::class);
                    $id = $desk->defaultDepartmentId($code);
                    $name = $id ? (string) (Department::query()->where('id', $id)->value('name') ?? '') : 'Full console';

                    return [$code => $name];
                })
                ->all(),
            'approvalAuthority' => $record instanceof User
                ? app(CreditAuthorityService::class)->effectiveLoanApproveDisplay($record)
                : null,
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (! empty($data['password'])) {
            // Plain value — User casts password as hashed (Hash::isHashed prevents double-hash).
            $data['password'] = (string) $data['password'];
            $data['password_changed_at'] = null;
        } else {
            unset($data['password']);
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        unset($data['department_ids']);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: string, 1: list<string>}
     */
    private function resolveRoleSelection(array $validated): array
    {
        $roleCodes = array_values(array_unique(array_map(
            'strval',
            (array) ($validated['roles'] ?? []),
        )));

        $screening = array_intersect($roleCodes, CreditDeskAssignmentService::SCREENING_ROLES);
        $committee = array_intersect($roleCodes, CreditDeskAssignmentService::COMMITTEE_ROLES);
        if ($screening !== [] && $committee !== []) {
            throw ValidationException::withMessages([
                'roles' => 'A user cannot hold both a screening capability and a committee capability.',
            ]);
        }

        $primary = $this->roles->resolvePrimaryRole($roleCodes);

        return [$primary, $roleCodes];
    }

    /** @return list<int> */
    private function resolvedDepartmentIds(Request $request): array
    {
        $ids = collect($request->input('department_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $primary = (int) $request->input('department_id', 0);
        if ($primary > 0 && ! in_array($primary, $ids, true)) {
            $ids[] = $primary;
        }

        return $ids;
    }

    public function create()
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        return parent::create();
    }

    public function store(Request $request)
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        $validated = $request->validate($this->rules());
        if (blank($validated['email'] ?? null) && blank($validated['password'] ?? null)) {
            throw ValidationException::withMessages([
                'password' => 'Set a temporary password, or add an email and issue a password setup link after create.',
            ]);
        }
        if (blank($validated['email'] ?? null)) {
            $validated['email'] = null;
        }
        [$primary, $roleCodes] = $this->resolveRoleSelection($validated);
        $desks = app(CreditDeskAssignmentService::class);
        $departmentIds = $desks->ensureDesks($roleCodes, $this->resolvedDepartmentIds($request));
        $validated['department_id'] = $desks->primaryDepartmentId($primary, $departmentIds);
        $validated['branch_id'] = $desks->headOfficeBranchId();
        $desks->assertCompatible($primary, $departmentIds);
        $validated['role'] = $primary;
        $validated['roles'] = $roleCodes;
        if (blank($validated['password'] ?? null)) {
            $validated['password'] = \Illuminate\Support\Str::password(32);
        }
        $data = $this->transform($validated);
        $record = User::create($data);
        $record->departments()->sync($departmentIds);
        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    public function show($id): View
    {
        abort_unless(auth()->user()?->hasPermission('users.view'), 403);

        $record = User::with(['department', 'departments'])->findOrFail($id);
        $approvalAuthority = app(CreditAuthorityService::class)->effectiveLoanApproveDisplay($record);

        return view("admin.{$this->viewFolder}.show", [
            'record'   => $record,
            'isLocked' => $this->accounts->isLocked($record),
            'approvalAuthority' => $approvalAuthority,
            'supportPerformance' => ($record->hasRole('agent') || $record->hasRole('partner_support'))
                ? app(\App\Services\Support\CustomerSupportWorkspaceService::class)->performanceSnapshot($record->id, '30d')
                : null,
        ]);
    }

    public function edit($id)
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        $record = User::with('departments')->findOrFail($id);

        return view("admin.{$this->viewFolder}.edit", ['record' => $record] + $this->formData($record));
    }

    public function update(Request $request, $id)
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        $record = User::findOrFail($id);
        $before = app(\App\Services\AuditService::class)->snapshot($record);
        $validated = $request->validate($this->rules($record));
        [$primary, $roleCodes] = $this->resolveRoleSelection($validated);
        $desks = app(CreditDeskAssignmentService::class);
        $departmentIds = $desks->ensureDesks($roleCodes, $this->resolvedDepartmentIds($request));
        $validated['department_id'] = $desks->primaryDepartmentId($primary, $departmentIds);
        $validated['branch_id'] = $desks->headOfficeBranchId();
        $desks->assertCompatible($primary, $departmentIds, $record);
        $validated['role'] = $primary;
        $validated['roles'] = $roleCodes;
        if (blank($validated['email'] ?? null)) {
            $validated['email'] = null;
        }
        $data = $this->transform($validated, $record);
        $record->update($data);
        $record->departments()->sync($departmentIds);
        $record->refresh();
        $this->auditAdminUpdated($record, $before);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' updated.');
    }

    public function destroy($id)
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        return parent::destroy($id);
    }

    public function lock(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'minutes' => ['nullable', 'integer', 'min:1', 'max:43200'],
            'reason'  => ['nullable', 'string', 'max:500'],
        ]);

        $this->accounts->lock(
            auth()->user(),
            $user,
            (int) ($data['minutes'] ?? 60),
            $data['reason'] ?? null,
            $request,
        );

        $user = $user->fresh();

        return redirect()
            ->route("{$this->routePrefix}.show", $user)
            ->with('status', 'Account locked until '.$user->locked_until?->format('d M Y, H:i').'.');
    }

    public function unlock(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        $this->accounts->unlock(auth()->user(), $user, $request);

        return redirect()
            ->route("{$this->routePrefix}.show", $user)
            ->with('status', 'Account unlocked.');
    }

    public function toggleActive(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        if ((int) $user->id === (int) auth()->id()) {
            return back()->with('error', 'You cannot deactivate your own account.');
        }

        $active = ! (bool) $user->is_active;
        $this->accounts->setActive(auth()->user(), $user, $active, $request);

        return redirect()
            ->route("{$this->routePrefix}.show", $user)
            ->with('status', $active ? 'Account activated.' : 'Account deactivated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        $result = $this->accounts->resetPassword(
            auth()->user(),
            $user,
            $data['password'] ?? null,
            $request,
        );

        return redirect()
            ->route("{$this->routePrefix}.show", $user)
            ->withFragment('password-access')
            ->with('status', 'Password set. Copy the temporary password below — it is shown once.')
            ->with('temporary_password', $result['temporary_password']);
    }

    public function issuePasswordSetupLink(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        $result = $this->accounts->issuePasswordSetupLink(auth()->user(), $user, $request);

        return redirect()
            ->route("{$this->routePrefix}.show", $user)
            ->withFragment('password-access')
            ->with('status', $result['emailed']
                ? 'Password setup link emailed (also copied below for sharing).'
                : 'Password setup link ready — copy and share securely (no email on file).')
            ->with('password_setup_url', $result['url'])
            ->with('password_setup_expires', $result['expires_at']);
    }

    public function resetSecurityVerification(Request $request, User $user): RedirectResponse
    {
        abort_unless(auth()->user()?->hasPermission('users.manage'), 403);

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        app(\App\Services\ConsoleSecondFactorService::class)->clearSecurityQuestions(
            auth()->user(),
            $user,
            $request,
            $data['reason'] ?? null,
        );

        // Also clear TOTP so the Staff member re-enrolls at next secure login (answers never shown).
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        \App\Models\AuditLog::create([
            'user_id' => auth()->id(),
            'event' => 'admin.user_security_verification_reset',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
            'old_values' => null,
            'new_values' => json_encode([
                'reset_by' => auth()->id(),
                'reason' => $data['reason'] ?? null,
                'cleared' => ['security_questions', 'authenticator'],
            ]),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1000),
        ]);

        return redirect()
            ->route("{$this->routePrefix}.show", $user)
            ->withFragment('password-access')
            ->with('status', 'Security verification reset. The user must re-enroll at next sign-in. Answers were never shown.');
    }
}
