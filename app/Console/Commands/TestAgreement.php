<?php
namespace App\Console\Commands;
use Illuminate\Console\Command;
use App\Models\LoanApplication;
use App\Services\LoanAgreementService;
use Illuminate\Support\Facades\Storage;

class TestAgreement extends Command {
    protected \$signature = 'test:agreement';
    public function handle() {
        \$app = LoanApplication::first();
        if (!\$app) { \$this->error('NO APP'); return 1; }
        \$svc = app(LoanAgreementService::class);
        \$a = \$svc->generateOfferLetter(\$app, true);
        \$this->info('agreement_id='.\$a->id.' ref='.\$a->reference.' status='.\$a->status.' path='.\$a->file_path);
        \$this->info('pdf_exists='.(int)Storage::disk('public')->exists(\$a->file_path).' bytes='.Storage::disk('public')->size(\$a->file_path));
        \$code = \$svc->issueSigningOtp(\$a);
        \$this->info('otp='.\$code);
        [\$ok,\$msg] = \$svc->signWithOtp(\$a->fresh(), \$code, '127.0.0.1', 'phpunit');
        \$this->info('sign_ok='.var_export(\$ok,true).' msg='.\$msg);
    }
}
