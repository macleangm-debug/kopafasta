@php
    $r = $record ?? null;
    $meta = $meta ?? ($r?->metadata ?? []);
    $filters = $meta['audience_filters'] ?? [];
    $wizardAlpine = 'campaignWizard('.json_encode([
        'estimateUrl' => $estimateUrl,
        'audiences' => ($savedAudiences ?? collect())->map(fn ($a) => [
            'id' => $a->id,
            'name' => $a->name,
            'count' => $a->estimated_count,
        ])->values(),
        'intents' => $intents ?? [],
        'enabledChannels' => $enabledChannels ?? ['in_app' => 'In-app'],
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
            'name' => old('name', $r?->name ?? ''),
            'messageEn' => old('message_en', $r?->message_en ?? ''),
            'messageSw' => old('message_sw', $r?->message_sw ?? ''),
            'cta' => old('cta_url', $meta['cta_url'] ?? ''),
            'offerId' => (string) old('offer_id', $meta['offer_id'] ?? ''),
        ],
    ]).')';
@endphp
