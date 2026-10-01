<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\HelpArticleFeedback;
use App\Services\Support\SupportHelpLibraryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HelpCentreController extends Controller
{
    public function category(string $category, SupportHelpLibraryService $help): View|RedirectResponse
    {
        $audience = $this->audience();
        $group = $help->category($category, $audience)
            ?? $help->category($category, 'both');

        if (! $group) {
            abort(404);
        }

        return view('site.help.category', [
            'group' => $group,
            'categoryKey' => $category,
            'howtoLabel' => $help->howtoLabel(),
            'audience' => $audience,
            'isSw' => str_starts_with(app()->getLocale(), 'sw'),
        ]);
    }

    public function article(string $category, string $slug, SupportHelpLibraryService $help): View|RedirectResponse
    {
        $audience = $this->audience();
        $article = $help->article($category, $slug, $audience)
            ?? $help->article($category, $slug, 'member')
            ?? $help->article($category, $slug, 'partner');

        if (! $article) {
            abort(404);
        }

        return view('site.help.article', [
            'article' => $article,
            'categoryKey' => $category,
            'slug' => $slug,
            'howtoLabel' => $help->howtoLabel(),
            'shareUrl' => route('site.help.article', ['category' => $category, 'slug' => $slug]),
            'audience' => $audience,
            'isSw' => str_starts_with(app()->getLocale(), 'sw'),
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
            return 'member';
        }

        $role = (string) ($user->role ?? '');
        if (in_array($role, ['borrower', 'member'], true) || $user->customer) {
            return 'member';
        }

        return 'partner';
    }
}
