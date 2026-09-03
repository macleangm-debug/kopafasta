@props([
    'steps' => [],
    'required' => true,
    'dbName' => 'kf-partner-face',
])

<x-site.guided-form-camera
    :steps="$steps"
    :db-name="$dbName"
    facing-mode="user"
    orientation="portrait"
    guide-frame="oval"
    :required="$required"
    thumb-class="aspect-square"
    :start-label="__('site.partner_account.face_start')"
>
    {{ $slot }}
</x-site.guided-form-camera>
