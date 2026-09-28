<?php

/*
|--------------------------------------------------------------------------
| COMPANY DETAILS
|--------------------------------------------------------------------------
|
| New file, from the 16-Sep meeting: Denisse asked for a static section at
| the bottom of the invoice with the banking information and payment
| methods, so the customer can pick how to pay.
|
*/

return [

    'title'    => 'Company details',
    'subtitle' => 'What gets printed on this company\'s estimates and invoices.',
    'saved'    => 'Company details saved.',

    /* ── Document header ── */
    'header_data'   => 'Document header',
    'address'       => 'Address',
    'address_2'     => 'Suite, floor, landmark (optional)',
    'city'          => 'City',
    'state'         => 'State',
    'zip'           => 'ZIP code',
    'phone'         => 'Phone',
    'email'         => 'Email',
    'website'       => 'Website',

    'footer_terms'      => 'Footer terms',
    'footer_terms_hint' => 'Fixed text printed at the end of every document. '
                          .'For example: "Invoice valid for: 3 days".',

    /* ── Payment methods ── */
    'payment_methods' => 'Payment methods',
    'payment_methods_hint' =>
        'Printed at the bottom of every invoice for this company, always, regardless of how '
        .'the customer pays. They get the document and choose. '
        .'Each company has its own accounts: load the ones for the active company.',

    'method_name'    => 'Zelle, Wire/SWIFT, Check...',
    'method_details' => "The details, one per line.\nBank, routing, account number, payable to...",
    'add_method'     => 'Add payment method',
    'remove_method'  => 'Remove',
    'no_methods'     => 'No payment methods loaded yet. Without these the invoice footer prints '
                       .'blank and the customer has nowhere to wire the money.',

];
