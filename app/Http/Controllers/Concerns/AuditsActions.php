<?php

namespace App\Http\Controllers\Concerns;

use App\Services\AuditService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

trait AuditsActions
{
    protected function auditResourceKey(): string
    {
        return property_exists($this, 'viewFolder') ? (string) $this->viewFolder : 'resource';
    }

    protected function auditAdminCreated(Model $record): void
    {
        app(AuditService::class)->logCreated($this->auditUser(), $this->auditResourceKey(), $record);
    }

    protected function auditAdminUpdated(Model $record, array $before): void
    {
        app(AuditService::class)->logUpdated($this->auditUser(), $this->auditResourceKey(), $record, $before);
    }

    protected function auditAdminDeleted(Model $record): void
    {
        app(AuditService::class)->logDeleted($this->auditUser(), $this->auditResourceKey(), $record);
    }

    protected function auditAdmin(string $event, ?Model $auditable = null, array $context = []): void
    {
        app(AuditService::class)->logAdminAction($this->auditUser(), $event, $auditable, $context);
    }

    protected function auditBorrower(string $event, ?Model $auditable = null, array $context = []): void
    {
        app(AuditService::class)->logBorrower(Auth::user(), $event, $auditable, $context);
    }

    protected function auditUser()
    {
        return Auth::user() ?? Auth::guard('admin')->user();
    }
}
