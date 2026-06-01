<?php

namespace App\Http\Controllers\Admin;

use App\Models\AmlRule;
use App\Models\Customer;
use App\Models\Loan;
use App\Models\SuspiciousActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class SuspiciousActivityController extends ResourceController
{
    protected string $model = SuspiciousActivity::class;
    protected string $routePrefix = 'admin.suspicious-activities';
    protected string $viewFolder = 'suspicious-activities';
    protected string $singular = 'suspicious activity';

    protected function rules(?Model $model = null): array
    {
        return [
            'customer_id'  => ['nullable', 'exists:customers,id'],
            'loan_id'      => ['nullable', 'exists:loans,id'],
            'aml_rule_id'  => ['nullable', 'exists:aml_rules,id'],
            'activity_type'=> ['required', 'string', 'max:100'],
            'amount'       => ['nullable', 'numeric'],
            'severity'     => ['required', 'in:low,medium,high,critical'],
            'status'       => ['required', 'in:open,investigating,cleared,reported,closed'],
            'description'  => ['required', 'string', 'max:2000'],
            'investigator_notes' => ['nullable', 'string', 'max:2000'],
            'assigned_to_user_id' => ['nullable', 'exists:users,id'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'customers' => Customer::orderBy('first_name')->limit(500)->get()->mapWithKeys(fn($c)=>[$c->id=>trim(($c->first_name ?? '').' '.($c->last_name ?? ''))]),
            'loans'     => Loan::orderByDesc('id')->limit(500)->pluck('loan_number', 'id'),
            'amlRules'  => AmlRule::orderBy('name')->pluck('name', 'id'),
            'users'     => User::orderBy('name')->pluck('name', 'id'),
            'severities'=> ['low'=>'Low','medium'=>'Medium','high'=>'High','critical'=>'Critical'],
            'statuses'  => ['open'=>'Open','investigating'=>'Investigating','cleared'=>'Cleared','reported'=>'Reported (STR)','closed'=>'Closed'],
        ];
    }
}
