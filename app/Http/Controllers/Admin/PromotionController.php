<?php

namespace App\Http\Controllers\Admin;

use App\Models\Promotion;
use Illuminate\Database\Eloquent\Model;

class PromotionController extends ResourceController
{
    protected string $model = Promotion::class;
    protected string $routePrefix = 'admin.promotions';
    protected string $viewFolder = 'promotions';
    protected string $singular = 'campaign';

    protected function rules(?Model $model = null): array
    {
        return [
            'code'              => ['required', 'string', 'max:40'],
            'name'              => ['required', 'string', 'max:150'],
            'type'              => ['required', 'in:birthday,fee_discount,interest_discount,referral_bonus'],
            'status'            => ['required', 'in:draft,active,ended'],
            'discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount'   => ['nullable', 'numeric', 'min:0'],
            'applies_to'        => ['nullable', 'string', 'max:40'],
            'starts_at'         => ['nullable', 'date'],
            'ends_at'           => ['nullable', 'date', 'after_or_equal:starts_at'],
            'message_template'  => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        return [
            'types' => [
                'birthday'           => 'Birthday greeting',
                'fee_discount'       => 'Fee discount',
                'interest_discount'  => 'Interest discount',
                'referral_bonus'     => 'Referral bonus',
            ],
            'statuses' => [
                'draft'  => 'Draft',
                'active' => 'Active',
                'ended'  => 'Ended',
            ],
            'appliesTo' => [
                'registration_fee'  => 'Registration fee',
                'application_fee'   => 'Application fee',
                'post_approval_fee' => 'Post-approval fees',
                'interest'          => 'Interest',
            ],
        ];
    }
}
