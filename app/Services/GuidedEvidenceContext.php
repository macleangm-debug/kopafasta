<?php

namespace App\Services;

use App\Models\LoanApplication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Remembers that the analyst opened evidence from Guided Review / Committee /
 * Post-Approval so the evidence page can return to the wizard, not a dead end.
 */
class GuidedEvidenceContext
{
    public function rememberFromRequest(Request $request, LoanApplication $application): void
    {
        $from = (string) $request->input('from', '');
        if (! in_array($from, ['guided', 'committee', 'post_approval'], true)) {
            if ($request->isMethod('get')) {
                $this->forget($application);
            }

            return;
        }

        $request->session()->put($this->key($application), [
            'from' => $from,
            'item' => $request->input('open_item'),
            'person' => $request->input('review_person'),
            'm' => $request->filled('review_m') ? $request->integer('review_m') : null,
            'g' => $request->filled('review_g') ? $request->integer('review_g') : null,
        ]);
    }

    /**
     * @return array{from: string, item: ?string, person: ?string, m: ?int, g: ?int}|null
     */
    public function peek(LoanApplication $application): ?array
    {
        $row = session($this->key($application));

        return is_array($row) && isset($row['from']) ? $row : null;
    }

    public function from(LoanApplication $application): ?string
    {
        return $this->peek($application)['from'] ?? (in_array(request('from'), ['guided', 'committee', 'post_approval'], true) ? request('from') : null);
    }

    public function isGuided(LoanApplication $application): bool
    {
        return $this->from($application) === 'guided';
    }

    public function backLabel(LoanApplication $application): string
    {
        return match ($this->from($application)) {
            'guided' => 'Back to Review',
            'committee' => 'Back to Committee Review',
            'post_approval' => 'Back to Post-Approval',
            default => 'Back to Screening',
        };
    }

    public function backUrl(LoanApplication $application): string
    {
        return match ($this->from($application)) {
            'guided' => route('admin.loan-applications.guided-screening', $application),
            'committee' => route('admin.loan-applications.guided-committee', $application),
            'post_approval' => route('admin.loan-applications.guided-post-approval', $application),
            default => route('admin.loan-applications.show', [
                'loan_application' => $application,
                'workspace' => 'overview',
            ]).'#credit-workspace',
        };
    }

    public function redirectAfterResolution(LoanApplication $application): RedirectResponse
    {
        $url = $this->backUrl($application);
        if (in_array($this->from($application), ['guided', 'committee', 'post_approval'], true)) {
            $this->forget($application);
        }

        return redirect()->to($url);
    }

    public function forget(LoanApplication $application): void
    {
        session()->forget($this->key($application));
    }

    private function key(LoanApplication $application): string
    {
        return 'guided.evidence_return.'.$application->id;
    }
}
