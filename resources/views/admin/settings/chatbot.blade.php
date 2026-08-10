<x-admin.layout title="Chatbot content" heading="Support chatbot" subheading="Automated answers shown in the AI assistant — add FAQs as you learn what borrowers ask most">
    @include('admin.settings._tabs', ['active' => 'chatbot'])

<form method="POST" action="{{ route('admin.settings.chatbot.save') }}" class="space-y-6">
        @csrf @method('PUT')

        <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 p-6 space-y-2">
            <h3 class="text-sm font-semibold text-gray-800">How matching works</h3>
            <p class="text-sm text-gray-600 leading-relaxed">Keywords are comma-separated. The assistant matches user messages against keywords and responds with a short delay. Inactive entries are hidden. Suggested questions in chat come from active English/Swahili question fields (sorted by order).</p>
        </div>

        <div class="space-y-5" x-data="{ rows: @js($entries) }">
            <template x-for="(entry, index) in rows" :key="index">
                <div class="bg-white rounded-xl shadow-sm ring-1 ring-gray-200 overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100 bg-gray-50 flex items-center justify-between gap-3">
                        <p class="text-sm font-semibold text-gray-900" x-text="'Entry ' + (index + 1)"></p>
                        <label class="inline-flex items-center gap-2 text-xs font-medium text-gray-700">
                            <input type="checkbox" :name="'entries[' + index + '][active]'" value="1" :checked="entry.active !== false" class="size-4 rounded border-gray-300 text-brand focus:ring-brand">
                            Active
                        </label>
                    </div>
                    <div class="p-5 space-y-5">
                        <input type="hidden" :name="'entries[' + index + '][key]'" :value="entry.key || ('entry_' + (index + 1))">
                        <div class="grid md:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Sort order</label>
                                <input type="number" :name="'entries[' + index + '][sort]'" x-model="entry.sort" min="0"
                                       class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 placeholder:text-gray-400 hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Keywords (comma-separated)</label>
                                <input type="text" :name="'entries[' + index + '][keywords]'" x-model="entry.keywords"
                                       class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 placeholder:text-gray-400 hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition"
                                       placeholder="loan, repayment, interest">
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 gap-5">
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Question (English)</label>
                                <input type="text" :name="'entries[' + index + '][question_en]'" x-model="entry.question_en"
                                       class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 placeholder:text-gray-400 hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Question (Swahili)</label>
                                <input type="text" :name="'entries[' + index + '][question_sw]'" x-model="entry.question_sw"
                                       class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 placeholder:text-gray-400 hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Answer (English)</label>
                                <textarea :name="'entries[' + index + '][answer_en]'" x-model="entry.answer_en" rows="4"
                                          class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 placeholder:text-gray-400 hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition"></textarea>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1">Answer (Swahili)</label>
                                <textarea :name="'entries[' + index + '][answer_sw]'" x-model="entry.answer_sw" rows="4"
                                          class="w-full text-sm bg-white border border-brand/15 rounded-xl shadow-sm px-3.5 py-2.5 placeholder:text-gray-400 hover:border-brand/30 focus:outline-none focus:border-brand focus:ring-2 focus:ring-brand/15 transition"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <button type="button"
                    @click="rows.push({ key: 'entry_' + (rows.length + 1), sort: rows.length + 1, active: true, keywords: '', question_en: '', question_sw: '', answer_en: '', answer_sw: '' })"
                    class="inline-flex items-center rounded-xl bg-white ring-1 ring-brand/20 hover:bg-brand-muted/40 text-brand text-sm font-semibold px-4 py-2.5">
                + Add entry
            </button>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="bg-brand-gold hover:brightness-95 text-brand font-semibold text-sm px-5 py-2.5 rounded-xl shadow-sm">Save chatbot content</button>
        </div>
    </form>
</x-admin.layout>
