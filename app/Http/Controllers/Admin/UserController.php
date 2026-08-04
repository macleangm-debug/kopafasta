<?php

namespace App\Http\Controllers\Admin;

use App\Models\Branch;
use App\Models\Department;
use App\Models\User;
use App\Services\RoleService;
use App\Services\UserAccountService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
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
            'email'           => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($id)],
            'phone'           => ['nullable', 'string', 'max:30'],
            'role'            => ['required', Rule::in($allowedRoles)],
            'branch_id'       => ['nullable', 'exists:branches,id'],
            'department_id'   => ['nullable', 'exists:departments,id'],
            'department_ids'  => ['nullable', 'array'],
            'department_ids.*'=> ['integer', 'exists:departments,id'],
            'approval_limit'  => ['nullable', 'numeric', 'min:0'],
            'is_active'       => ['nullable', 'boolean'],
            'password'        => [$id ? 'nullable' : 'required', 'string', 'min:6'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        $roleOptions = [];

        foreach ($this->roles->userFormRoles() as $code) {
            $roleOptions[$code] = $this->roles->label($code);
        }

        return [
            'branches'    => Branch::orderBy('name')->pluck('name', 'id'),
            'departments' => Department::orderBy('name')->pluck('name', 'id'),
            'roles'       => $roleOptions,
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        unset($data['department_ids']);

        return $data;
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
        $departmentIds = $this->resolvedDepartmentIds($request);
        app(\App\Services\CreditDeskAssignmentService::class)
            ->assertCompatible((string) $validated['role'], $departmentIds);
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

        return view("admin.{$this->viewFolder}.show", [
            'record'   => $record,
            'isLocked' => $this->accounts->isLocked($record),
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
        $departmentIds = $this->resolvedDepartmentIds($request);
        app(\App\Services\CreditDeskAssignmentService::class)
            ->assertCompatible((string) $validated['role'], $departmentIds, $record);
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
}
