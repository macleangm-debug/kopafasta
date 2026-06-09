<?php

namespace App\Providers;

use App\Models\ArrearCase;
use App\Models\Customer;
use App\Models\CustomerKyc;
use App\Models\Disbursement;
use App\Models\Loan;
use App\Models\LoanApplication;
use App\Models\LoanProduct;
use App\Models\LoanTopUpRequest;
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
use App\Policies\LoanTopUpRequestPolicy;
use App\Policies\RepaymentPolicy;
use App\Policies\RestructureRequestPolicy;
use App\Policies\VendorPolicy;
use App\Policies\VendorTaskPolicy;
use App\Services\PermissionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            \App\Contracts\CrbClientInterface::class,
            \App\Services\Crb\DnbLiveCrbClient::class,
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::unguard();

        Paginator::useTailwind();

        View::share('fmt', fn (float|int|string|null $amount, int $decimals = 0): string => format_number($amount, $decimals));
        View::share('fmtMoney', fn (float|int|string|null $amount, int $decimals = 0): string => format_money($amount, true, $decimals));

        Blade::directive('num', function (string $expression) {
            return "<?php echo e(format_number({$expression})); ?>";
        });

        Blade::directive('money', function (string $expression) {
            return "<?php echo e(format_money({$expression})); ?>";
        });

        Gate::policy(Loan::class, LoanPolicy::class);
        Gate::policy(Disbursement::class, DisbursementPolicy::class);
        Gate::policy(ArrearCase::class, ArrearCasePolicy::class);
        Gate::policy(LoanApplication::class, LoanApplicationPolicy::class);
        Gate::policy(RestructureRequest::class, RestructureRequestPolicy::class);
        Gate::policy(LoanTopUpRequest::class, LoanTopUpRequestPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(LoanProduct::class, LoanProductPolicy::class);
        Gate::policy(Repayment::class, RepaymentPolicy::class);
        Gate::policy(Vendor::class, VendorPolicy::class);

        CustomerKyc::observe(CustomerKycObserver::class);
        Gate::policy(VendorTask::class, VendorTaskPolicy::class);

        foreach (array_keys(config('permissions.permissions', [])) as $permission) {
            Gate::define($permission, fn (User $user) => app(PermissionService::class)->has($user, $permission));
        }

        Blade::if('perm', fn (string $permission) => auth()->check() && auth()->user()->hasPermission($permission));
        Blade::if('permany', function (...$permissions) {
            if (! auth()->check()) {
                return false;
            }
            $user = auth()->user();

            return app(PermissionService::class)->hasAny($user, $permissions);
        });
    }
}
