<?php

/*
|--------------------------------------------------------------------------
| INVOICING
|--------------------------------------------------------------------------
|
| Payment terms are NOT translated: "Net 30" and "Due on receipt" print
| as-is in both languages. They are legal document text — the customer
| knows them that way and their accountant expects to see them that way.
|
| What does change is the explanation shown inside the dropdown, which is
| for whoever issues the invoice, not for the customer.
|
*/

return [

    'terms_on_receipt' => 'payable on receipt',
    'terms_days'       => ':n days',
    'terms_deposit'    => '50% deposit, balance on delivery',
    'terms_other'      => 'Other — type it in',
    'terms_other_ph'   => 'e.g. Net 45, 30% upfront and balance in 60 days…',

];
