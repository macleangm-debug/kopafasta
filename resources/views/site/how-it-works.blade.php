<x-site.layout title="How it works — Kopafasta">
    <section class="max-w-4xl mx-auto px-4 py-16">
        <h1 class="text-3xl sm:text-4xl font-bold tracking-tight">How it works</h1>
        <p class="mt-3 text-gray-600">Four simple steps from registration to disbursement.</p>

        <ol class="mt-10 space-y-6">
            @foreach ([
                ['Register', 'Create an account in under a minute. Phone, email, password.'],
                ['Apply', 'Use our 4-step wizard: pick a product, share your details, set income, and confirm.'],
                ['Review', 'Our team verifies your NIDA, runs credit checks, and prepares your offer.'],
                ['Disburse', 'Once approved, funds land in your account within hours.'],
            ] as $i => $row)
                <li class="flex gap-5 items-start">
                    <span class="size-10 rounded-full bg-amber-500 text-gray-900 font-bold grid place-items-center shrink-0">{{ $i+1 }}</span>
                    <div>
                        <h3 class="font-semibold text-lg">{{ $row[0] }}</h3>
                        <p class="text-sm text-gray-600">{{ $row[1] }}</p>
                    </div>
                </li>
            @endforeach
        </ol>
    </section>
</x-site.layout>
