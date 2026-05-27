<?php
require __DIR__.'/vendor/autoload.php';
\$app = require_once __DIR__.'/bootstrap/app.php';
\$kernel = \$app->make(\Illuminate\Contracts\Console\Kernel::class);
\$kernel->bootstrap();

use App\Models\LoanApplication;
use App\Services\LoanAgreementService;
use Illuminate\Support\Facades\Storage;

\$application = LoanApplication::first();
if (!\$application) { echo "NO APP\n"; exit(1); }
\$svc = app(LoanAgreementService::class);
\$a = \$svc->generateOfferLetter(\$application, true);
echo "agreement_id=".\$a->id." ref=".\$a->reference." status=".\$a->status." path=".\$a->file_path."\n";
echo "pdf_exists=".(int)Storage::disk("public")->exists(\$a->file_path)." bytes=".Storage::disk("public")->size(\$a->file_path)."\n";
\$code = \$svc->issueSigningOtp(\$a);
echo "otp=".\$code."\n";
\$result = \$svc->signWithOtp(\$a->fresh(), \$code, "127.0.0.1", "phpunit");
\$ok = \$result[0];
\$msg = \$result[1];
echo "sign_ok=".var_export(\$ok,true)." msg=".\$msg."\n";
