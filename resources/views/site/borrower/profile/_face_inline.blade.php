@php
    $faceSubmitQuery = array_filter([
        'return' => $returnUrl ?? null,
        'application' => request('application'),
        'solo' => request()->boolean('solo') ? 1 : null,
    ]);
    $faceSubmitUrl = route('site.borrower.face-verification.submit');
    if ($faceSubmitQuery !== []) {
        $faceSubmitUrl .= '?'.http_build_query($faceSubmitQuery);
    }
@endphp
<x-site.face-verification-wizard
    :customer="$customer ?? auth()->user()?->customer"
    :angles="$faceAngles ?? []"
    :wizard="$wizard"
    :photos="$facePhotos ?? collect()"
    :steps="$steps"
    :upload-urls="$uploadUrls"
    :delete-urls="$deleteUrls ?? []"
    :submit-url="$faceSubmitUrl"
    :return-url="$returnUrl ?? null"
/>
