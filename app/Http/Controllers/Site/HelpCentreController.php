<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\HelpArticleFeedback;
use App\Services\PartnerWorkspaceService;
use App\Services\Support\SupportHelpLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpCentreController extends Controller
{
    public function category(string $category, SupportHelpLibraryService $help): View|RedirectResponse
    {
        $audience = $this->audience();
        $workspace = $this->workspace();
        $group = $help->category($category, $audience, $workspace)
            ?? $help->category($category, 'both')
            ?? $help->category($category, 'member');

        if (! $group) {
            abort(404);
        }

        if (auth()->user()?->customer) {
            return redirect()->route('site.borrower.support', [
                'section' => 'help',
                'topic' => $category,
            ]);
        }

        if (auth()->check() && ! auth()->user()?->customer) {
            $route = request()->routeIs('site.vendor.*')
                ? 'site.vendor.support'
                : 'site.partner.support';

            return redirect()->route($route, [
                'section' => 'help',
                'topic' => $category,
            ]);
        }

        // Public: single Help Centre surface (same engine).
        return redirect()->route('site.support', [
            'topic' => $category,
        ]);
    }

    public function article(string $category, string $slug, SupportHelpLibraryService $help): View|RedirectResponse
    {
        $audience = $this->audience();
        $workspace = $this->workspace();
        $article = $help->article($category, $slug, $audience, $workspace)
            ?? $help->article($category, $slug, 'member')
            ?? $help->article($category, $slug, 'partner')
            ?? $help->article($category, $slug, 'both');

        if (! $article) {
            abort(404);
        }

        if (auth()->user()?->customer) {
            return redirect()->route('site.borrower.support', [
                'section' => 'help',
                'topic' => $category,
                'article' => $slug,
            ]);
        }

        if (auth()->check() && ! auth()->user()?->customer) {
            $route = request()->routeIs('site.vendor.*')
                ? 'site.vendor.support'
                : 'site.partner.support';

            return redirect()->route($route, [
                'section' => 'help',
                'topic' => $category,
                'article' => $slug,
            ]);
        }

        // Public deep link — exact article, same engine, no login required to read.
        return redirect()->route('site.support', [
            'topic' => $category,
            'article' => $slug,
        ]);
    }

    public function feedback(Request $request, string $category, string $slug): RedirectResponse
    {
        $data = $request->validate([
            'vote' => ['required', 'in:yes,no'],
        ]);

        HelpArticleFeedback::query()->create([
            'user_id' => $request->user()?->id,
            'customer_id' => $request->user()?->customer?->id,
            'category_key' => $category,
            'article_slug' => $slug,
            'vote' => $data['vote'],
            'locale' => app()->getLocale(),
        ]);

        return back()
            ->with('help_feedback_vote', $data['vote'])
            ->with('status', $data['vote'] === 'yes'
                ? (str_starts_with(app()->getLocale(), 'sw') ? 'Asante kwa maoni yako.' : 'Thanks for your feedback.')
                : (str_starts_with(app()->getLocale(), 'sw') ? 'Asante. Unaweza Ongea na timu au Tuma maoni.' : 'Thanks. You can Talk to Support or Send feedback.'));
    }

    private function audience(): string
    {
        $user = auth()->user();
        if (! $user) {
            return 'member'; // public uses member/public shared content
        }

        $role = (string) ($user->role ?? '');
        if (in_array($role, ['borrower', 'member'], true) || $user->customer) {
            return 'member';
        }

        return 'partner';
    }

    private function workspace(): ?string
    {
        $user = auth()->user();
        $partner = $user?->partner;
        if (! $partner) {
            return null;
        }

        return app(PartnerWorkspaceService::class)->currentKey($partner);
    }
}
