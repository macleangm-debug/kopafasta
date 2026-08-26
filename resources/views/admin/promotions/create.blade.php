<x-admin.create-page
    title="New campaign"
    heading="New campaign"
    subheading="Goal → Audience → Channels → Timing → Preview → Launch. Fee discounts stay on the existing Promotion engine."
    :action="route('admin.promotions.store')"
    :cancelUrl="route('admin.promotions.index')"
    submitLabel="Launch campaign"
    :confirmBeforeSubmit="true"
    :alpine="$wizardAlpine">
    @include('admin.promotions._form', ['record' => null])
</x-admin.create-page>
