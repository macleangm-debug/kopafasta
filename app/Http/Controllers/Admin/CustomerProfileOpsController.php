<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\ProfileCompletionService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class CustomerProfileOpsController extends Controller
{
    public function index(Request $request, ProfileCompletionService $completion): View
    {
        abort_unless($request->user()?->hasPermission('customers.view'), 403);

        $q = trim((string) $request->query('q', ''));
        $bucket = (string) $request->query('bucket', 'attention');
        $focusId = (int) $request->query('focus', 0);
        $perPage = 20;

        $source = Customer::query()
            ->when($q !== '', function ($query) use ($q) {
                $like = '%'.$q.'%';
                $digits = preg_replace('/\D+/', '', $q) ?? '';
                $query->where(function ($inner) use ($like, $digits) {
                    $inner->where('first_name', 'like', $like)
                        ->orWhere('last_name', 'like', $like)
                        ->orWhereRaw("concat(first_name, ' ', last_name) like ?", [$like])
                        ->orWhere('customer_number', 'like', $like)
                        ->orWhere('phone', 'like', $like);
                    if (strlen($digits) >= 4) {
                        $inner->orWhere('phone', 'like', '%'.$digits.'%');
                    }
                });
            })
            ->orderByDesc('updated_at')
            ->limit($q !== '' ? 80 : 250)
            ->get();

        $rows = $source->map(function (Customer $customer) use ($completion) {
            $summary = $completion->completionSummary($customer);
            $missing = array_values(array_filter($summary['remaining'] ?? []));

            return [
                'customer' => $customer,
                'percent' => (int) ($summary['percent'] ?? 0),
                'missing' => $missing,
                'complete' => ((int) ($summary['percent'] ?? 0)) >= 100,
                'needs_documents' => collect($missing)->contains(fn ($label) => str_contains(strtolower((string) $label), 'document')
                    || str_contains(strtolower((string) $label), 'bank')
                    || str_contains(strtolower((string) $label), 'proof')
                    || str_contains(strtolower((string) $label), 'kyc')),
            ];
        });

        $counts = [
            'attention' => $rows->filter(fn (array $row) => ! $row['complete'])->count(),
            'complete' => $rows->where('complete', true)->count(),
            'incomplete' => $rows->filter(fn (array $row) => ! $row['complete'])->count(),
            'documents' => $rows->where('needs_documents', true)->count(),
        ];

        $filtered = $rows->filter(function (array $row) use ($bucket) {
            return match ($bucket) {
                'complete' => $row['complete'],
                'incomplete' => ! $row['complete'],
                'documents' => $row['needs_documents'],
                default => ! $row['complete'],
            };
        })->values();

        $page = max(1, (int) $request->query('page', 1));
        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $focused = null;
        if ($focusId > 0) {
            $focusCustomer = $source->firstWhere('id', $focusId)
                ?? Customer::query()->find($focusId);
            if ($focusCustomer) {
                $summary = $completion->completionSummary($focusCustomer);
                $tabs = $completion->tabStatuses($focusCustomer);
                $focused = [
                    'customer' => $focusCustomer,
                    'percent' => (int) ($summary['percent'] ?? 0),
                    'missing' => array_values(array_filter($summary['remaining'] ?? [])),
                    'tabs' => $tabs,
                ];
            }
        }

        return view('admin.customers.profiles', [
            'page' => $paginator,
            'rows' => $paginator->items(),
            'bucket' => $bucket,
            'q' => $q,
            'counts' => $counts,
            'focused' => $focused,
        ]);
    }
}
