<?php

namespace App\Livewire\Admin;

use App\Models\LoanApplication;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class LoanApplicationsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q')] public string $search = '';
    #[Url] public string $status = '';
    #[Url] public string $stage = '';
    #[Url] public string $sort = 'created_at';
    #[Url] public string $direction = 'desc';
    #[Url] public bool $mine = false;
    public int $perPage = 15;
    public bool $lockStage = false;
    public ?string $pipeline = null;

    public function mount(?string $stage = null, bool $lockStage = false, ?string $pipeline = null): void
    {
        if ($stage !== null && $stage !== '') {
            $this->stage = $stage;
        }
        $this->lockStage = $lockStage;
        $this->pipeline = $pipeline;
    }

    public function updatingSearch(): void { $this->resetPage(); }
    public function updatingStatus(): void { $this->resetPage(); }
    public function updatingStage(): void { $this->resetPage(); }
    public function updatingMine(): void { $this->resetPage(); }

    public function sortBy(string $col): void
    {
        $this->direction = $this->sort === $col
            ? ($this->direction === 'asc' ? 'desc' : 'asc')
            : 'asc';
        $this->sort = $col;
    }

    public function render()
    {
        $readiness = app(\App\Services\ApplicationDisbursementReadinessService::class);

        $rows = LoanApplication::query()
            ->with(['customer', 'product', 'loan', 'assignedAnalyst'])
            ->when($this->search !== '', function ($q) {
                $term = '%'.$this->search.'%';
                $q->where(function ($q) use ($term) {
                    $q->where('application_number', 'like', $term)
                        ->orWhereHas('customer', fn ($q) => $q->where('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term)
                            ->orWhere('phone', 'like', $term));
                });
            })
            ->when($this->mine, fn ($q) => $q->where('assigned_analyst_id', auth()->id()))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->stage !== '', fn ($q) => $q->where('current_stage', $this->stage))
            ->when($this->pipeline === 'under_review', function ($q) {
                $q->where(function ($q) {
                    $q->whereIn('current_stage', ['screening', 'credit_appraisal'])
                        ->orWhere(function ($q) {
                            $q->where(function ($inner) {
                                $inner->where('current_stage', 'rejected')
                                    ->orWhere('status', 'rejected');
                            })->where(function ($inner) {
                                $inner->whereNull('current_stage')
                                    ->orWhereNotIn('current_stage', ['pre_approval', 'approval', 'disbursement']);
                            });
                        });
                })->whereNotIn('status', ['approved', 'disbursed']);
            })
            ->when($this->pipeline === 'committee', function ($q) {
                $q->where('current_stage', 'pre_approval')
                    ->whereNotIn('status', ['approved', 'disbursed', 'rejected']);
            })
            ->when($this->pipeline === 'approved', function ($q) {
                $q->where(function ($q) {
                    $q->where(function ($q) {
                        $q->where('status', 'approved')
                            ->whereIn('current_stage', [
                                'approval',
                                'post_approval_fees',
                                'awaiting_disbursement_details',
                                'contract_generation',
                            ]);
                    })->orWhere(function ($q) {
                        $q->where('offer_status', 'declined')
                            ->whereIn('current_stage', [
                                'approval',
                                'post_approval_fees',
                                'awaiting_disbursement_details',
                                'contract_generation',
                            ]);
                    });
                });
            })
            ->when($this->pipeline === 'disbursement', function ($q) {
                $q->where(function ($q) {
                    $q->where('current_stage', 'disbursement')
                        ->orWhere('status', 'disbursed');
                });
            })
            ->when($this->pipeline === 'under_review', function ($q) {
                $q->orderByDesc('engagement_priority');
            })
            ->orderBy($this->sort, $this->direction)
            ->paginate($this->perPage);

        $pipelineStages = [];
        if ($this->pipeline === 'approved') {
            foreach ($rows as $row) {
                $pipelineStages[$row->id] = $readiness->approvedPipelineStage($row);
            }
        } elseif ($this->pipeline === 'disbursement') {
            foreach ($rows as $row) {
                $pipelineStages[$row->id] = $readiness->disbursementPipelineStage($row);
            }
        }

        $statuses = ['pending', 'under_review', 'approved', 'rejected', 'cancelled'];
        $stages = LoanApplication::STAGES;

        return view('livewire.admin.loan-applications-table', compact('rows', 'statuses', 'stages', 'pipelineStages'));
    }
}
