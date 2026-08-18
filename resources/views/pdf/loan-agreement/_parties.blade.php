@php require resource_path('views/pdf/loan-agreement/locale.php'); @endphp
@php
    $isGroup = ! empty($snapshot['is_group_loan']);
    $members = $snapshot['group_members'] ?? [];
    $hasGuarantor = filled($snapshot['guarantor_name'] ?? null);
@endphp

<h2>{{ $t('Parties', 'Wahusika') }}</h2>

<h3>{{ $isGroup ? $t('Group leader (borrower)', 'Kiongozi wa kikundi (mkopaji)') : $t('Borrower', 'Mkopaji') }}</h3>
<table class="kv">
    <tr><td class="label">{{ $t('Full name', 'Jina kamili') }}</td><td class="value">{{ pdf_text($snapshot['customer_name'] ?? '—') }}</td></tr>
    <tr><td class="label">NIDA / ID</td><td class="value">{{ pdf_text($snapshot['customer_id'] ?? '—') }}</td></tr>
    <tr><td class="label">{{ $t('Residential address', 'Anwani ya makazi') }}</td><td class="value">{{ pdf_text($snapshot['customer_address'] ?? '—') }}</td></tr>
    <tr><td class="label">{{ $t('Phone', 'Simu') }}</td><td class="value">{{ pdf_text($snapshot['customer_phone'] ?? '—') }}</td></tr>
</table>

@if ($hasGuarantor)
    <h3>{{ $t('Guarantor', 'Mdhamini') }}</h3>
    <table class="kv">
        <tr><td class="label">{{ $t('Name', 'Jina') }}</td><td class="value">{{ pdf_text($snapshot['guarantor_name']) }}</td></tr>
        <tr><td class="label">NIDA / ID</td><td class="value">{{ pdf_text($snapshot['guarantor_nida'] ?? '—') }}</td></tr>
        @if (! empty($snapshot['guarantor_address']))
            <tr><td class="label">{{ $t('Address', 'Anwani') }}</td><td class="value">{{ pdf_text($snapshot['guarantor_address']) }}</td></tr>
        @endif
        <tr><td class="label">{{ $t('Phone', 'Simu') }}</td><td class="value">{{ pdf_text($snapshot['guarantor_phone'] ?? '—') }}</td></tr>
        @if (! empty($snapshot['guarantor_relationship']))
    <tr><td class="label">{{ $t('Relationship', 'Uhusiano') }}</td><td class="value">{{ pdf_text($relationshipLabel($snapshot['guarantor_relationship'] ?? null)) }}</td></tr>
        @endif
    </table>
@endif

@if ($isGroup)
    <h3>{{ $t('Group members', 'Wanachama wa kikundi') }}</h3>
    @if (! empty($snapshot['group_name']))
        <p class="muted">{{ pdf_text($snapshot['group_name']) }}</p>
    @endif
    @if ($members !== [])
        <table class="grid">
            <thead>
                <tr>
                    <th>{{ $t('Role', 'Wadhifa') }}</th>
                    <th>{{ $t('Name', 'Jina') }}</th>
                    <th>NIDA</th>
                    <th>{{ $t('Phone', 'Simu') }}</th>
                    <th>{{ $t('Allocation', 'Mgao') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($members as $member)
                    <tr>
                        <td>{{ $roleLabel($member['role'] ?? null) }}</td>
                        <td>{{ pdf_text($member['name'] ?? '—') }}</td>
                        <td>{{ pdf_text($member['national_id'] ?? '—') }}</td>
                        <td>{{ pdf_text($member['phone'] ?? '—') }}</td>
                        <td>{{ format_money($member['requested_amount'] ?? 0) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="muted">{{ $t('Group members will appear on the executed contract.', 'Wanachama wa kikundi wataonekana kwenye mkataba unaotekelezwa.') }}</p>
    @endif
@endif
