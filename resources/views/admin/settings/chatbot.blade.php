<x-admin.layout title="Chatbot" heading="KopaFasta Assistant" subheading="Suggested questions and keyword replies for the public site chatbot">
    @include('admin.settings._tabs', ['active' => 'chatbot'])
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.chatbot.save') }}" class="space-y-6" x-data="{ rows: @js($entries) }">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-sm font-semibold text-gray-700">Question entries</h3>
                    <p class="text-xs text-gray-500 mt-1">Keywords are comma-separated. Suggested questions appear as quick chips in the chatbot.</p>
                </div>
                <button type="button"
                        @click="rows.push({ key: '', sort: rows.length + 1, active: true, keywords: '', question_en: '', question_sw: '', answer_en: '', answer_sw: '' })"
                        class="text-sm font-semibold text-amber-700 hover:underline">+ Add entry</button>
            </div>

            <div class="space-y-4">
                <template x-for="(row, index) in rows" :key="index">
                    <div class="rounded-xl border border-gray-200 p-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500" x-text="'Entry ' + (index + 1)"></p>
                            <div class="flex items-center gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="hidden" :name="'entries[' + index + '][active]'" value="0">
                                    <input type="checkbox" :name="'entries[' + index + '][active]'" value="1" x-model="row.active" class="rounded border-gray-300 text-amber-600">
                                    Active
                                </label>
                                <button type="button" @click="rows.splice(index, 1)" class="text-xs text-red-600 hover:underline">Remove</button>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Key</label>
                                <input type="text" :name="'entries[' + index + '][key]'" x-model="row.key" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Sort</label>
                                <input type="number" :name="'entries[' + index + '][sort]'" x-model="row.sort" min="0" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Keywords</label>
                                <input type="text" :name="'entries[' + index + '][keywords]'" x-model="row.keywords" placeholder="apply, register, omba" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Suggested question (English)</label>
                                <input type="text" :name="'entries[' + index + '][question_en]'" x-model="row.question_en" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Suggested question (Swahili)</label>
                                <input type="text" :name="'entries[' + index + '][question_sw]'" x-model="row.question_sw" class="w-full rounded-lg border-gray-300 text-sm">
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Answer (English)</label>
                                <textarea :name="'entries[' + index + '][answer_en]'" x-model="row.answer_en" rows="3" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Answer (Swahili)</label>
                                <textarea :name="'entries[' + index + '][answer_sw]'" x-model="row.answer_sw" rows="3" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save chatbot content</button>
        </div>
    </form>
</x-admin.layout>
