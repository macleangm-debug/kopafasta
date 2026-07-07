<x-admin.layout title="Chatbot content" heading="Support chatbot" subheading="Automated answers shown in the AI assistant — add FAQs as you learn what borrowers ask most">
    @include('admin.settings._tabs', ['active' => 'chatbot'])

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 ring-1 ring-emerald-200 px-4 py-3 text-sm text-emerald-700">{{ session('status') }}</div>
    @endif

    <form method="POST" action="{{ route('admin.settings.chatbot.save') }}" class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-6">
        @csrf @method('PUT')

        <p class="text-sm text-gray-600">Keywords are comma-separated. The assistant matches user messages against keywords and responds with a short delay to feel natural. Inactive entries are hidden from matching.</p>

        <div class="space-y-4" x-data="{ rows: @js($entries) }">
            <template x-for="(entry, index) in rows" :key="index">
                <div class="rounded-xl ring-1 ring-gray-200 p-4 space-y-3">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-900" x-text="'Entry ' + (index + 1)"></p>
                        <label class="inline-flex items-center gap-2 text-xs text-gray-600">
                            <input type="checkbox" :name="'entries[' + index + '][active]'" value="1" :checked="entry.active !== false" class="rounded border-gray-300 text-amber-600">
                            Active
                        </label>
                    </div>
                    <input type="hidden" :name="'entries[' + index + '][key]'" :value="entry.key || ('entry_' + (index + 1))">
                    <div class="grid md:grid-cols-3 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Sort order</label>
                            <input type="number" :name="'entries[' + index + '][sort]'" x-model="entry.sort" min="0" class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-xs font-medium text-gray-600 mb-1">Keywords (comma-separated)</label>
                            <input type="text" :name="'entries[' + index + '][keywords]'" x-model="entry.keywords" class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                    </div>
                    <div class="grid md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Question (English)</label>
                            <input type="text" :name="'entries[' + index + '][question_en]'" x-model="entry.question_en" class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Question (Swahili)</label>
                            <input type="text" :name="'entries[' + index + '][question_sw]'" x-model="entry.question_sw" class="w-full rounded-lg border-gray-300 text-sm">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Answer (English)</label>
                            <textarea :name="'entries[' + index + '][answer_en]'" x-model="entry.answer_en" rows="3" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Answer (Swahili)</label>
                            <textarea :name="'entries[' + index + '][answer_sw]'" x-model="entry.answer_sw" rows="3" class="w-full rounded-lg border-gray-300 text-sm"></textarea>
                        </div>
                    </div>
                </div>
            </template>

            <button type="button" @click="rows.push({ key: 'entry_' + (rows.length + 1), sort: rows.length + 1, active: true, keywords: '', question_en: '', question_sw: '', answer_en: '', answer_sw: '' })"
                    class="text-sm font-semibold text-amber-700 hover:underline">+ Add entry</button>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-amber-600 hover:bg-amber-700 text-white font-semibold text-sm px-5 py-2 rounded-lg shadow-sm">Save chatbot content</button>
        </div>
    </form>
</x-admin.layout>
