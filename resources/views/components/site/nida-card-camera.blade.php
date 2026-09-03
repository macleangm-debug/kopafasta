@props([
    'frontName' => 'front',
    'backName' => 'back',
    'frontHostId' => 'nida-front',
    'backHostId' => 'nida-back',
    'required' => true,
    'dbName' => 'kf-nida-card',
    'subjectName' => null,
    'compact' => false,
    'frontPath' => null,
    'backPath' => null,
])

@php
    $steps = [
        [
            'asset_id' => 0,
            'angle' => 'front',
            'label' => __('borrower.document_upload.front'),
            'guidance' => __('borrower.document_upload.nida_front_guide'),
            'required' => true,
            'inputName' => $frontName,
            'inputId' => $frontHostId,
            'path' => $frontPath,
            'path_url' => filled($frontPath) ? asset('storage/'.$frontPath) : null,
        ],
        [
            'asset_id' => 0,
            'angle' => 'back',
            'label' => __('borrower.document_upload.back'),
            'guidance' => __('borrower.document_upload.nida_back_guide'),
            'required' => true,
            'inputName' => $backName,
            'inputId' => $backHostId,
            'path' => $backPath,
            'path_url' => filled($backPath) ? asset('storage/'.$backPath) : null,
        ],
    ];
@endphp

<x-site.guided-form-camera
    :steps="$steps"
    :db-name="$dbName"
    facing-mode="environment"
    orientation="landscape"
    guide-frame="id-card"
    :required="$required"
    :compact="$compact"
    :subject-name="$subjectName"
    :start-label="__('borrower.document_upload.nida_start')"
>
    {{ $slot }}
</x-site.guided-form-camera>
