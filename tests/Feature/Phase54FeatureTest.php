<?php

namespace Tests\Feature;

use App\Models\Partner;
use App\Models\PartnerDocument;
use App\Models\PartnerPayment;
use App\Models\PartnerTask;
use App\Models\RecoveryAssignment;
use App\Models\ValuationAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class Phase54FeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_partner_task_id_columns_replace_vendor_task_id(): void
    {
        foreach (['partner_documents', 'partner_payments', 'recovery_assignments', 'valuation_assignments'] as $table) {
            $this->assertTrue(Schema::hasColumn($table, 'partner_task_id'), $table);
            $this->assertFalse(Schema::hasColumn($table, 'vendor_task_id'), $table);
        }
    }

    public function test_vendor_task_id_accessor_maps_to_partner_task_id(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PTR-P54-001',
            'name'          => 'Task Partner',
            'category'      => 'gps_installer',
            'status'        => 'active',
            'phone'         => '255712345894',
        ]);

        $task = PartnerTask::create([
            'vendor_id'  => $partner->id,
            'task_type'  => 'gps_installation',
            'status'     => 'assigned',
            'fee_amount' => 25000,
        ]);

        $payment = PartnerPayment::create([
            'vendor_id'      => $partner->id,
            'vendor_task_id' => $task->id,
            'invoice_number' => 'INV-P54-001',
            'amount'         => 25000,
            'status'         => 'pending',
        ]);

        $this->assertDatabaseHas('partner_payments', [
            'id'              => $payment->id,
            'partner_task_id' => $task->id,
        ]);
        $this->assertSame($task->id, $payment->fresh()->vendor_task_id);
        $this->assertSame($task->id, $payment->fresh()->task?->id);
    }

    public function test_recovery_and_valuation_assignments_keep_vendor_task_id_accessor(): void
    {
        $partner = Partner::create([
            'vendor_number' => 'PTR-P54-002',
            'name'          => 'Recovery Partner',
            'category'      => 'debt_collector',
            'status'        => 'active',
            'phone'         => '255712345895',
        ]);

        $task = PartnerTask::create([
            'vendor_id' => $partner->id,
            'task_type' => 'recovery_visit',
            'status'    => 'assigned',
        ]);

        $document = PartnerDocument::create([
            'vendor_id'      => $partner->id,
            'vendor_task_id' => $task->id,
            'label'          => 'Proof of visit',
            'file_path'      => 'proofs/test.pdf',
        ]);

        $this->assertDatabaseHas('partner_documents', [
            'id'              => $document->id,
            'partner_task_id' => $task->id,
        ]);
        $this->assertSame($task->id, $document->fresh()->vendor_task_id);
    }
}
