<?php

namespace App\Providers;

use App\Models\ArrearCase;
use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\Repayment;
use App\Models\RestructureRequest;
use App\Models\Vendor;
use App\Models\VendorTask;
use App\Observers\CustomerKycObserver;
use App\Policies\ArrearCasePolicy;
use App\Policies\CustomerPolicy;
use App\Policies\DisbursementPolicy;
use App\Policies\LoanPolicy;
use App\Policies\LoanApplicationPolicy;
use App\Policies\LoanProductPolicy;
use App\Policies\RepaymentPolicy;
use App\Policies\RestructureRequestPolicy;
use App\Policies\VendorPolicy;
use App\Policies\VendorTaskPolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        Paginator::useTailwind();

        Gate::policy(Loan::class, LoanPolicy::class);
        Gate::policy(Disbursement::class, DisbursementPolicy::class);
        Gate::policy(ArrearCase::class, ArrearCasePolicy::class);
        Gate::policy(LoanApplication::class, LoanApplicationPolicy::class);
        Gate::policy(RestructureRequest::class, RestructureRequestPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(LoanProduct::class, LoanProductPolicy::class);
        Gate::policy(Repayment::class, RepaymentPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);

        CustomerKyc::observe(CustomerKycObserver::class);
        Gate::policy(VendorTask::class, VendorTaskPolicy::class);
    }
}
