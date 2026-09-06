<x-site.layout :title="brand_title(__('site.feedback.title'))">
    @php
        $openOnLoad = request()->boolean('open') || old('category') || session('status') || $errors->any();
    @endphp

    <x-site.public-hero
        variant="minimal"
        :title="__('site.feedback.title')"
        :body="__('site.feedback.subtitle')"
    />

    <x-site.public-section narrow>
        <x-site.feedback-form-panel :open-on-load="$openOnLoad" />
    </x-site.public-section>
</x-site.layout>
