<x-site.face-verification-wizard
    :customer="$customer ?? auth()->user()?->customer"
    :angles="$faceAngles ?? []"
    :wizard="$wizard"
    :photos="$facePhotos ?? collect()"
    :steps="$steps"
    :upload-urls="$uploadUrls"
    :delete-urls="$deleteUrls ?? []"
    :submit-url="route('site.borrower.face-verification.submit')"
/>
