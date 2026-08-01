<div class="border-t border-gray-100 px-5 py-4 bg-gray-50/70" x-data="{ open: false }">
    <button type="button" @click="open = !open"
            class="inline-flex items-center gap-2 text-xs font-semibold text-brand hover:text-brand-light">
        <span x-text="open ? '−' : '+'"></span>
        <span>{{ $title }}</span>
    </button>
    <form x-show="open" x-cloak method="POST" action="{{ route('admin.credit-team.store') }}" class="mt-4 space-y-3">
        @csrf
        <input type="hidden" name="team" value="{{ $team }}">
        <div class="grid sm:grid-cols-2 gap-3">
            <div>
                <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Full name</label>
                <input type="text" name="name" required maxlength="120"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Email</label>
                <input type="email" name="email" required maxlength="190"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Phone</label>
                <input type="text" name="phone" maxlength="40"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div>
                <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Temporary password</label>
                <input type="password" name="password" required minlength="6"
                       class="w-full rounded-lg border-gray-300 text-sm">
            </div>
            <div class="sm:col-span-2">
                <label class="block text-[10px] uppercase tracking-widest text-gray-500 font-semibold mb-1">Branch (optional)</label>
                <select name="branch_id" class="w-full rounded-lg border-gray-300 text-sm">
                    <option value="">—</option>
                    @foreach ($branches as $branch)
                        <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <button type="submit"
                class="inline-flex bg-brand-gold hover:brightness-95 text-brand font-semibold px-4 py-2 rounded-lg text-sm">
            Save member
        </button>
    </form>
</div>
