@include('pdf.loan-agreement._locale')
@php
    $roleLabel = function (?string $role) use ($t): string {
        return match ($role) {
            'leader' => $t('Group leader', 'Kiongozi wa kikundi'),
            'guarantor' => $t('Guarantor', 'Mdhamini'),
            default => $t('Group member', 'Mwanachama'),
        };
    };
@endphp

<div class="signbox">
    <h2>{{ $t('Signatures', 'Sahihi') }}</h2>
    <table class="kv">
        <tr>
            <td style="width:50%;vertical-align:top;padding-right:10px">
                <strong>{{ $t('Borrower', 'Mkopaji') }}</strong>
                @php $bSig = $snapshot['borrower_signature'] ?? $snapshot['offer_borrower_signature'] ?? null; @endphp
                @if (! empty($bSig->signature_data ?? $bSig['signature_data'] ?? null))
                    <div><img src="{{ $bSig->signature_data ?? $bSig['signature_data'] }}" class="sig-img" alt=""></div>
                    <div class="muted">{{ $bSig->signer_name ?? $bSig['signer_name'] ?? $snapshot['customer_name'] }}</div>
                @elseif ($agreement->isSigned())
                    <div class="muted" style="margin-top:8px">{{ $t('PIN accepted', 'PIN imekubaliwa') }} {{ $agreement->signed_at?->format('d M Y H:i') }}</div>
                @else
                    <div class="muted" style="margin-top:18px">______________________________</div>
                @endif
                <div class="muted">{{ $snapshot['customer_name'] ?? '—' }}</div>
                <div class="muted">NIDA: {{ $snapshot['customer_id'] ?? '—' }}</div>
            </td>
            <td style="width:50%;vertical-align:top">
                <strong>{{ $t('Guarantor', 'Mdhamini') }}</strong>
                @if (! empty($snapshot['guarantor_name']))
                    @if (! empty($snapshot['guarantor_signature']->signature_data ?? null))
                        <div><img src="{{ $snapshot['guarantor_signature']->signature_data }}" class="sig-img" alt=""></div>
                        <div class="muted">{{ $snapshot['guarantor_signature']->signer_name ?? $snapshot['guarantor_name'] }}</div>
                    @else
                        <div class="muted" style="margin-top:18px">______________________________</div>
                    @endif
                    <div class="muted">{{ $snapshot['guarantor_name'] }}</div>
                    <div class="muted">NIDA: {{ $snapshot['guarantor_nida'] ?? '—' }}</div>
                @else
                    <div class="na" style="margin-top:10px">{{ $t('NOT APPLICABLE', 'HAITUMIKI') }}</div>
                @endif
            </td>
        </tr>
    </table>

    @if (! empty($snapshot['is_group_loan']) && ! empty($snapshot['group_members']))
        <h3>{{ $t('Group signatories', 'Wasaini wa kikundi') }}</h3>
        <table class="grid">
            <thead>
                <tr>
                    <th>{{ $t('Role', 'Wadhifa') }}</th>
                    <th>{{ $t('Name / NIDA', 'Jina / NIDA') }}</th>
                    <th>{{ $t('Signature', 'Saini') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($snapshot['group_members'] as $member)
                    <tr>
                        <td>{{ $roleLabel($member['role'] ?? null) }}</td>
                        <td>
                            {{ $member['name'] ?? '—' }}<br>
                            <span class="muted">{{ $member['national_id'] ?? '—' }} · {{ $member['phone'] ?? '—' }}</span>
                        </td>
                        <td>
                            @if (! empty($member['signature_data']))
                                <img src="{{ $member['signature_data'] }}" class="sig-img" alt="">
                                <div class="muted">{{ $member['signer_name'] ?? $member['name'] }}</div>
                            @else
                                <span class="muted">{{ ucfirst($member['signature_status'] ?? 'pending') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <table class="kv" style="margin-top:12px">
        <tr>
            <td style="width:33%;vertical-align:top">
                <strong>{{ $t('Chief Executive Officer', 'Mkurugenzi Mtendaji') }}</strong>
                @if (! empty($snapshot['ceo_signature_path'] ?? $snapshot['company_signature_path'] ?? null))
                    <div><img src="{{ $snapshot['ceo_signature_path'] ?? $snapshot['company_signature_path'] }}" class="sig-img" alt=""></div>
                @else
                    <div class="muted" style="margin-top:18px">______________________________</div>
                @endif
                <div class="muted">{{ $snapshot['ceo_signatory_name'] ?? $snapshot['company_signatory_name'] ?? $snapshot['company_legal_name'] ?? brand('legal_name') }}</div>
                <div class="muted">{{ $snapshot['ceo_signatory_title'] ?? $snapshot['company_signatory_title'] ?? $t('Chief Executive Officer', 'Mkurugenzi Mtendaji') }}</div>
            </td>
            <td style="width:33%;vertical-align:top">
                <strong>{{ $t('Finance manager', 'Meneja fedha') }}</strong>
                @if (! empty($snapshot['finance_signature_path']))
                    <div><img src="{{ $snapshot['finance_signature_path'] }}" class="sig-img" alt=""></div>
                @else
                    <div class="muted" style="margin-top:18px">______________________________</div>
                @endif
                <div class="muted">{{ $snapshot['finance_signatory_name'] ?? '—' }}</div>
                <div class="muted">{{ $snapshot['finance_signatory_title'] ?? $t('Finance manager', 'Meneja fedha') }}</div>
            </td>
            <td style="width:34%;vertical-align:top;text-align:center">
                <strong>{{ $t('Company stamp', 'Muhuri wa kampuni') }}</strong>
                @if (! empty($snapshot['company_stamp_path']))
                    <div><img src="{{ $snapshot['company_stamp_path'] }}" class="stamp-img" alt=""></div>
                @else
                    <div class="muted" style="margin-top:12px">—</div>
                @endif
            </td>
        </tr>
    </table>
</div>
