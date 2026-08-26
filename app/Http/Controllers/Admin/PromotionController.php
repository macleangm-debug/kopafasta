<?php

namespace App\Http\Controllers\Admin;

use App\Models\MarketingAudience;
use App\Models\PlusOffer;
use App\Models\PlusSubject;
use App\Models\Promotion;
use App\Services\Marketing\CampaignOrchestrationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class PromotionController extends ResourceController
{
    protected string $model = Promotion::class;
    protected string $routePrefix = 'admin.promotions';
    protected string $viewFolder = 'promotions';
    protected string $singular = 'campaign';

    public function create()
    {
        abort_unless(request()->user()?->hasPermission('marketing.campaigns.create'), 403);

        return view("admin.{$this->viewFolder}.create", $this->formData());
    }

    public function store(Request $request)
    {
        abort_unless($request->user()?->hasPermission('marketing.campaigns.create'), 403);
        $this->normalizeMoneyRequest($request);
        $data = $this->transform($request->validate($this->rules()));
        $record = $this->model::create($data);
        $this->auditAdminCreated($record);
        app(CampaignOrchestrationService::class)->launch($record);

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', 'Campaign launched. Fee discounts still use the existing promotion engine.');
    }

    public function show($id)
    {
        $record = $this->model::findOrFail($id);
        $record = app(CampaignOrchestrationService::class)->refreshResults($record);

        return view("admin.{$this->viewFolder}.show", [
            'record' => $record,
            'results' => $record->metadata['results'] ?? [],
            'orchestration' => $record->metadata ?? [],
        ]);
    }

    public function update(Request $request, $id)
    {
        abort_unless($request->user()?->hasPermission('marketing.campaigns.edit'), 403);
        $this->normalizeMoneyRequest($request);
        $record = $this->model::findOrFail($id);
        $data = $this->transform($request->validate($this->rules($record)), $record);
        $record->update($data);
        if (($data['status'] ?? '') === 'active') {
            app(CampaignOrchestrationService::class)->launch($record->fresh());
        }

        return redirect()
            ->route("{$this->routePrefix}.show", $record)
            ->with('status', 'Campaign saved.');
    }

    protected function rules(?Model $model = null): array
    {
        $intents = array_keys(config('marketing.campaign_intents', []));

        return [
            'code'              => ['nullable', 'string', 'max:40'],
            'name'              => ['required', 'string', 'max:150'],
            'type'              => ['nullable', 'in:birthday,registration_fee_discount,application_fee_discount,referral,promo_code,seasonal,fee_discount,referral_bonus,membership_campaign'],
            'status'            => ['nullable', 'in:draft,active,ended'],
            'discount_percent'  => ['nullable', 'numeric', 'min:0', 'max:100'],
            'discount_amount'   => ['nullable', 'numeric', 'min:0'],
            'original_fee'      => ['nullable', 'numeric', 'min:0'],
            'discount_type'     => ['nullable', 'in:percentage,fixed'],
            'eligible_members'  => ['nullable', 'in:all,new,renewing,inactive'],
            'banner_path'       => ['nullable', 'string', 'max:255'],
            'applies_to'        => ['nullable', 'string', 'max:40', 'in:'.implode(',', \App\Services\PromotionService::FEE_APPLIES_TO)],
            'starts_at'         => ['nullable', 'date'],
            'ends_at'           => ['nullable', 'date', 'after_or_equal:starts_at'],
            'message_template'  => ['nullable', 'string', 'max:2000'],
            'message_en'        => ['nullable', 'string', 'max:2000'],
            'message_sw'        => ['nullable', 'string', 'max:2000'],
            'intent'            => ['required', 'in:'.implode(',', $intents)],
            'intent_other'      => ['nullable', 'required_if:intent,other', 'string', 'max:160'],
            'audience_mode'     => ['required', 'in:everyone,saved,custom'],
            'audience_id'       => ['nullable', 'integer'],
            'country_code'      => ['nullable', 'string', 'max:2'],
            'audience_status'   => ['nullable', 'string', 'max:20'],
            'grades'            => ['nullable', 'array'],
            'plus'              => ['nullable', 'string', 'max:20'],
            'borrowing'         => ['nullable', 'string', 'max:30'],
            'affiliate'         => ['nullable', 'string', 'max:30'],
            'payload_type'      => ['nullable', 'in:message,offer,plus,referral,article,fee'],
            'offer_id'          => ['nullable', 'integer'],
            'cta_url'           => ['nullable', 'string', 'max:255'],
            'article_hint'      => ['nullable', 'string', 'max:160'],
            'channels'          => ['nullable', 'array'],
            'send_mode'         => ['required', 'in:now,schedule'],
        ];
    }

    protected function formData(?Model $record = null): array
    {
        $meta = $record?->metadata ?? [];
        $orchestration = app(CampaignOrchestrationService::class);
        $savedAudiences = MarketingAudience::query()->orderBy('name')->get(['id', 'name', 'estimated_count', 'filters']);
        $intents = config('marketing.campaign_intents', []);
        $enabledChannels = $orchestration->enabledChannels();
        $estimateUrl = route('admin.growth.audiences.estimate');
        $filters = $meta['audience_filters'] ?? [];

        return [
            'types' => [
                'birthday'                  => 'Birthday',
                'registration_fee_discount' => 'Membership fee discount',
                'application_fee_discount'  => 'Application fee discount',
                'referral'                  => 'Referral',
                'promo_code'                => 'Promo code',
                'seasonal'                  => 'Seasonal',
                'fee_discount'              => 'General fee discount',
                'referral_bonus'            => 'Referral bonus',
                'membership_campaign'       => 'Membership campaign',
            ],
            'discountTypes' => [
                'percentage' => 'Percentage',
                'fixed'      => 'Fixed amount (TZS)',
            ],
            'eligibleMembers' => [
                'all'       => 'All members',
                'new'       => 'New members only',
                'renewing'  => 'Renewing members',
                'inactive'  => 'Inactive members',
            ],
            'statuses' => [
                'draft'  => 'Draft',
                'active' => 'Active',
                'ended'  => 'Ended',
            ],
            'appliesTo' => [
                'registration_fee'  => 'Membership fee',
                'application_fee'   => 'Application fee',
                'post_approval_fee' => 'Post-approval fees',
                'valuation_fee'     => 'Valuation fee',
                'membership_fee'    => 'Membership fee',
                'all'               => 'All eligible fees',
            ],
            'intents' => $intents,
            'dimensions' => config('marketing.audience_dimensions', []),
            'savedAudiences' => $savedAudiences,
            'offers' => PlusOffer::query()->where('active', true)->orderBy('title')->limit(50)->get(['id', 'title']),
            'articles' => class_exists(PlusSubject::class)
                ? PlusSubject::query()->where('status', 'published')->orderBy('title_en')->limit(40)->get(['id', 'title_en'])
                : collect(),
            'enabledChannels' => $enabledChannels,
            'estimateUrl' => $estimateUrl,
            'meta' => $meta,
            'wizardAlpine' => 'campaignWizard('.json_encode([
                'estimateUrl' => $estimateUrl,
                'audiences' => $savedAudiences->map(fn ($a) => [
                    'id' => $a->id,
                    'name' => $a->name,
                    'count' => $a->estimated_count,
                ])->values(),
                'intents' => $intents,
                'enabledChannels' => $enabledChannels ?: ['in_app' => 'In-app'],
                'initial' => [
                    'intent' => old('intent', $meta['intent'] ?? 'encourage_plus'),
                    'intentOther' => old('intent_other', $meta['intent_other'] ?? ''),
                    'audienceMode' => old('audience_mode', $meta['audience_mode'] ?? 'everyone'),
                    'audienceId' => (string) old('audience_id', $meta['audience_id'] ?? ''),
                    'country' => old('country_code', $filters['country_code'] ?? ''),
                    'status' => old('audience_status', $filters['status'] ?? 'active'),
                    'grades' => array_values((array) old('grades', $filters['grades'] ?? [])),
                    'plus' => old('plus', $filters['plus'] ?? 'any'),
                    'borrowing' => old('borrowing', $filters['borrowing'] ?? 'any'),
                    'affiliate' => old('affiliate', $filters['affiliate'] ?? 'any'),
                    'channels' => array_values((array) old('channels', $meta['channels'] ?? ['in_app'])),
                    'sendMode' => old('send_mode', $meta['send_mode'] ?? 'now'),
                    'name' => old('name', $record?->name ?? ''),
                    'messageEn' => old('message_en', $record?->message_en ?? ''),
                    'messageSw' => old('message_sw', $record?->message_sw ?? ''),
                    'cta' => old('cta_url', $meta['cta_url'] ?? ''),
                    'offerId' => (string) old('offer_id', $meta['offer_id'] ?? ''),
                ],
            ]).')',
        ];
    }

    protected function transform(array $data, ?Model $existing = null): array
    {
        $data = parent::transform($data, $existing);

        return app(CampaignOrchestrationService::class)->transformPayload($data, $existing instanceof Promotion ? $existing : null);
    }
}
