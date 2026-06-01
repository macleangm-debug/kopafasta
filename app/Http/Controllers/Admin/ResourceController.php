<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\AuditsActions;
use App\Http\Controllers\Controller;
use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

abstract class ResourceController extends Controller
{
    use AuditsActions;

    /** Eloquent model class, e.g. \App\Models\Customer::class */
    protected string $model;

    /** Route name prefix, e.g. 'admin.customers' */
    protected string $routePrefix;

    /** Folder for views under resources/views/admin, e.g. 'customers' */
    protected string $viewFolder;

    /** Human-singular noun, e.g. 'customer' */
    protected string $singular = 'record';

    /** Validation rules */
    abstract protected function rules(?Model $model = null): array;

    /** Extra data passed to create/edit views (selects etc.) */
    protected function formData(?Model $record = null): array
    {
        return [];
    }

    /** Hook to modify validated data before save (e.g. defaults, auto-numbers) */
    protected function transform(array $data, ?Model $existing = null): array
    {
        return $data;
    }

    public function create()
    {
        return view("admin.{$this->viewFolder}.create", $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->transform($request->validate($this->rules()));
        $record = $this->model::create($data);
        $this->auditAdminCreated($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' created.');
    }

    public function show($id)
    {
        $record = $this->model::findOrFail($id);

        return view("admin.{$this->viewFolder}.show", ['record' => $record]);
    }

    public function edit($id)
    {
        $record = $this->model::findOrFail($id);

        return view("admin.{$this->viewFolder}.edit", ['record' => $record] + $this->formData($record));
    }

    public function update(Request $request, $id)
    {
        $record = $this->model::findOrFail($id);
        $before = app(AuditService::class)->snapshot($record);
        $data = $this->transform($request->validate($this->rules($record)), $record);
        $record->update($data);
        $record->refresh();
        $this->auditAdminUpdated($record, $before);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', ucfirst($this->singular).' updated.');
    }

    public function destroy($id)
    {
        $record = $this->model::findOrFail($id);
        $this->auditAdminDeleted($record);
        $record->delete();

        return redirect()
            ->route("{$this->routePrefix}.index")
            ->with('status', ucfirst($this->singular).' deleted.');
    }
}
