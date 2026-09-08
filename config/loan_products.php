<?php

return [
    /**
     * Catalogue sort order (landing, public products, borrower list, apply selector).
     * Codes missing from this list sort after known products.
     */
    'display_order' => [
        'IL',      // 1 Individual Loan
        'FC',      // 2 Artisan Loan
        'WL',      // 3 Queen's Loan
        'AL',      // 4 Asset Lending
        'AB',      // 5 Asset-Backed Loan
        'EM',      // 6 Emergency Loan
        'KB',      // 7 Agro Loan
        'EL',      // 8 Education Loan
        'GL',
        'BP',
        'SAL-12',
        // SL (Sharia) intentionally omitted from active catalogue while deactivated.
    ],
];
