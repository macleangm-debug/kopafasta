<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\IpRule;
use App\Services\IpRuleService;
use Illuminate\Console\Command;

class IpRulesCommand extends Command
{
    protected $signature = 'security:ip-rules
        {action : list|add|remove}
        {cidr? : CIDR or single IP (required for add/remove)}
        {--mode=deny : allow|deny (for add/remove)}
        {--reason= : Optional reason text (for add)}
        {--force : Allow adding an opposite-mode rule when one already exists for this CIDR}';

    protected $description = 'Manage IP allow/deny rules from the CLI.';

    public function handle(IpRuleService $service): int
    {
        $action = (string) $this->argument('action');

        return match ($action) {
            'list' => $this->listRules(),
            'add' => $this->addRule($service),
            'remove' => $this->removeRule($service),
            default => $this->failWith("Unknown action: {$action}. Use list|add|remove."),
        };
    }

    private function listRules(): int
    {
        $rules = IpRule::query()->orderBy('mode')->orderBy('cidr')->get();
        if ($rules->isEmpty()) {
            $this->info('No IP rules configured.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'CIDR', 'Mode', 'Reason', 'Created'],
            $rules->map(fn (IpRule $r) => [
                $r->id, $r->cidr, $r->mode, $r->reason, $r->created_at?->toDateTimeString(),
            ])->all()
        );

        return self::SUCCESS;
    }

    private function addRule(IpRuleService $service): int
    {
        $cidr = (string) $this->argument('cidr');
        $mode = (string) $this->option('mode');

        if ($cidr === '') {
            return $this->failWith('cidr argument is required for add.');
        }
        if (! in_array($mode, [IpRule::MODE_ALLOW, IpRule::MODE_DENY], true)) {
            return $this->failWith("Invalid mode: {$mode}. Use allow|deny.");
        }
        if (! $this->isValidCidr($cidr)) {
            return $this->failWith("Invalid CIDR: {$cidr}");
        }

        $opposite = $mode === IpRule::MODE_DENY ? IpRule::MODE_ALLOW : IpRule::MODE_DENY;
        if (IpRule::where('cidr', $cidr)->where('mode', $opposite)->exists() && ! $this->option('force')) {
            return $this->failWith("An opposite-mode rule already exists for {$cidr}. Re-run with --force to override.");
        }

        $rule = IpRule::updateOrCreate(
            ['cidr' => $cidr, 'mode' => $mode],
            ['reason' => $this->option('reason')]
        );
        $service->flush();

        AuditLog::create([
            'user_id' => null,
            'event' => 'cli.ip_rule_created',
            'auditable_type' => IpRule::class,
            'auditable_id' => $rule->id,
            'old_values' => null,
            'new_values' => json_encode([
                'cidr' => $rule->cidr,
                'mode' => $rule->mode,
                'reason' => $rule->reason,
            ]),
            'ip_address' => null,
            'user_agent' => 'artisan',
        ]);

        $this->info("IP rule saved: [{$rule->mode}] {$rule->cidr}");

        return self::SUCCESS;
    }

    private function removeRule(IpRuleService $service): int
    {
        $cidr = (string) $this->argument('cidr');
        $mode = (string) $this->option('mode');

        if ($cidr === '') {
            return $this->failWith('cidr argument is required for remove.');
        }

        $rule = IpRule::where('cidr', $cidr)->where('mode', $mode)->first();
        if (! $rule) {
            $this->warn("No {$mode} rule found for {$cidr}.");

            return self::SUCCESS;
        }

        $snapshot = ['cidr' => $rule->cidr, 'mode' => $rule->mode, 'reason' => $rule->reason];
        $id = $rule->id;
        $rule->delete();
        $service->flush();

        AuditLog::create([
            'user_id' => null,
            'event' => 'cli.ip_rule_deleted',
            'auditable_type' => IpRule::class,
            'auditable_id' => $id,
            'old_values' => json_encode($snapshot),
            'new_values' => null,
            'ip_address' => null,
            'user_agent' => 'artisan',
        ]);

        $this->info("Removed {$mode} rule for {$cidr}.");

        return self::SUCCESS;
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }

    private function isValidCidr(string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return @inet_pton($cidr) !== false;
        }
        [$subnet, $bits] = explode('/', $cidr, 2);
        if (! ctype_digit($bits)) {
            return false;
        }
        $bin = @inet_pton($subnet);
        if ($bin === false) {
            return false;
        }
        $max = strlen($bin) * 8;
        $bitsInt = (int) $bits;

        return $bitsInt >= 0 && $bitsInt <= $max;
    }
}
