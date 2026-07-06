<x-site.face-verification-wizard
    :customer="$customer ?? auth()->user()?->customer"
    :angles="$faceAngles ?? []"
    :wizard="$wizard"
    :photos="$facePhotos ?? collect()"
    :steps="$steps"
    :upload-urls="$uploadUrls"
/>
