<?php

/*
|--------------------------------------------------------------------------
| ESTIMATES MODULE
|--------------------------------------------------------------------------
|
| Every string shown on the estimate screens.
|
| ── HOW THE KEYS ARE NAMED ──
|
| By MEANING, not by position. 'delivery_zip', never 'field_4_col_1'.
| If the field moves tomorrow the key still makes sense.
|
| Keys ending in _help are the small hints under a field. Keys ending
| in _hint are tooltips.
|
*/

return [

    /* ---------------------------------------------------------------
     | HEADER
     * ------------------------------------------------------------ */
    'title_new'          => 'New estimate',
    'title_edit'         => 'Edit estimate :number',
    'subtitle_new'       => 'The number is assigned automatically on save.',
    'subtitle_edit'      => 'Changes are saved with the button on the right.',
    'errors_title'       => 'Could not save. This is missing:',
    'missing_one'        => '1 field missing',
    'missing_many'       => ':count fields missing',

    /* ---------------------------------------------------------------
     | 1 · CUSTOMER
     * ------------------------------------------------------------ */
    'section_customer'   => 'Customer',
    'search_customer'    => 'Find customer',
    'search_customer_ph' => 'Company name, contact, phone or customer number…',
    'customer_not_found' => 'No customer matches that.',
    'no_contact'         => 'No contact on file',
    'tax_exempt_badge'   => 'Tax exempt',

    /* ---------------------------------------------------------------
     | 2 · DOCUMENT DETAILS
     * ------------------------------------------------------------ */
    'section_document'   => 'Document details',
    'issue_date'         => 'Issue date',
    'valid_until'        => 'Valid until',
    'valid_until_hint'   => 'After this date the price no longer holds. Recalculated when the issue date changes.',
    'valid_until_help'   => 'Adjusts itself with the issue date.',

    'terms'              => 'Payment terms',
    'terms_other'        => 'Other — type it in',
    'terms_other_ph'     => 'e.g. Net 45, 30% down and balance in 60 days…',
    'terms_other_help'   => 'This prints on the document exactly as written.',

    'salesperson_help'   => 'Commission is calculated from this if the deal closes.',

    'payment_method'         => 'Expected payment method',
    'payment_method_hint'    => 'Tells the system whether to add the card surcharge. Copied to the invoice on conversion.',
    'payment_method_unknown' => '— Not known yet —',

    'use_type'           => 'What will the container be used for?',
    'export_notice'      => 'Export sale',
    'export_no_tax'      => 'No sales tax applies.',
    'export_needs_cert'  => 'The CSC certificate PDF is required before invoicing.',
    'export_no_delivery' => 'Usually no delivery: the customer books their own shipping line.',

    /* ---------------------------------------------------------------
     | 3 · ADDRESSES
     * ------------------------------------------------------------ */
    'section_addresses'  => 'Addresses',
    'ship_elsewhere'     => 'Deliver to a different address',
    'addresses_intro'    => '<strong>Bill to</strong> is the billing address that prints on the document. <strong>Ship to</strong> is where the container is physically dropped. If they are the same, leave the switch off.',
    'bill_to_label'      => 'Bill to',
    'ship_to_label'      => 'Ship to',
    'street_ph'          => 'Street and number',
    'line2_bill_ph'      => 'Suite, unit, reference',
    'line2_ship_ph'      => 'Gate, jobsite reference',

    'autofilled'         => 'Addresses were filled in from the ones on file for <strong>:name</strong>.',
    'autofilled_ship'    => 'This customer has a delivery address different from the billing one, so the switch above was turned on and it was loaded into <strong>Ship to</strong>. If this time delivery goes to the billing address, turn it off.',
    'autofilled_note'    => 'You can change any field: whatever is written here is what prints, and it does not touch the customer record.',

    'no_saved_address'   => 'This customer has no address on file yet, which is why the fields came up blank.',
    'save_to_customer'   => 'Save this address to the customer record',
    'save_to_customer_x' => '— so the next estimate fills itself in',
    'addresses_frozen'   => 'These addresses are stored as a snapshot of today. If the customer moves next year, this document will still show where they were today.',

    /* ---------------------------------------------------------------
     | 4 · LINE ITEMS
     * ------------------------------------------------------------ */
    'section_lines'      => 'Line items',
    'group_selected'     => 'Group selected',
    'add_line'           => 'Add line',
    'select_hint'        => 'Check two or more lines and use "Group selected".',

    'col_concept'        => 'Item',
    'col_description'    => 'Description',
    'col_qty'            => 'Qty',
    'col_price'          => 'Price',
    'col_amount'         => 'Amount',
    'col_tax'            => 'Tax',
    'col_group'          => 'Group',
    'tax_col_hint'       => 'Checked = pays the 7%. Freight is never checked.',

    'free_line'          => '— Free text —',
    'description_ph'     => 'What the customer will read',
    'find_unit'          => 'Find unit',
    'remove_unit'        => 'Remove unit',
    'remove_line'        => 'Remove line',
    'remove_from_group'  => 'Take this line out of group :group',

    /* ---------------------------------------------------------------
     | THE DELIVERY ROW
     * ------------------------------------------------------------ */
    'delivery_row'       => 'Details for this delivery',
    'delivery_zip'       => 'Destination ZIP',
    'miles'              => 'Miles',
    'rate_per_mile'      => 'Rate/mile',
    'rate_hint'          => 'Comes from Settings. Can be changed for this line only.',
    'delivery_row_help'  => 'The amount is calculated automatically: miles × rate. Still editable.',
    'delivery_export'    => 'Export sales rarely include delivery: the customer books their own shipping line. Add this line only for an empty container moving to a terminal.',

    /* ---------------------------------------------------------------
     | GROUPS
     * ------------------------------------------------------------ */
    'groups_title'       => 'How each group prints',
    'groups_intro'       => 'These lines add up into a single printed row for the customer, but internally each one keeps whether it is taxable. That is what allows a consolidated price while still charging the 7% on the container only.',
    'group_lines'        => 'Lines :lines',
    'customer_sees'      => 'Customer sees:',
    'group_text_ph'      => 'e.g. 40HC container delivered to Homestead',
    'group_text_help'    => 'This is the text the customer will read on that row.',

    /* ---------------------------------------------------------------
     | 5 · NOTES
     * ------------------------------------------------------------ */
    'section_notes'      => 'Notes',
    'doc_notes'          => 'Document notes',
    'doc_notes_ph'       => 'These print on the estimate.',
    'footer_terms'       => 'Footer terms',
    'footer_terms_ph'    => 'Left empty, the company defaults are used.',

    /* ---------------------------------------------------------------
     | TOTALS
     * ------------------------------------------------------------ */
    'discount_field'     => 'Discount ($)',
    'tax_rate_field'     => 'Sales tax (%)',
    'customer_exempt'    => 'Customer is tax exempt',
    'pays_with_card'     => 'Paying by card (+:percent%)',
    'non_taxable_freight'=> 'Non-taxable (freight)',
    'exempt_note'        => 'Exempt customer. On invoicing, the certificate that justifies it will be recorded as backup for the state.',

    /* ---------------------------------------------------------------
     | BUTTONS AND SENDING
     * ------------------------------------------------------------ */
    'send_hint'          => 'Saves the estimate and emails it to the customer.',
    'sent_ok'            => 'Estimate :number sent to :email.',
    'sent_queued'        => 'Estimate :number saved. The email was queued for sending.',
    'send_no_email'      => 'The estimate was saved, but this customer has no email on file. Add one to their record and send it again.',
    'send_failed'        => 'The estimate was saved, but the email could not go out. You can retry from the list.',

    /* ---------------------------------------------------------------
     | UNIT PICKER
     * ------------------------------------------------------------ */
    'unit_picker_title'  => 'Choose a unit for line :line',
    /* ── The line editor ── */
    'line_new'           => 'New line',
    'line_edit'          => 'Line :n',
    'line_modal_sub'     => 'Press Esc to cancel',
    'line_empty'         => 'No description',
    'no_lines'           => 'No line items yet. Add the first one.',
    'line_amount'        => 'Line amount',
    'monthly_amount'     => 'Monthly rate',
    'description_help'   => 'This is the text the customer sees on the document.',

    'block_unit'         => 'The unit',
    'block_delivery'     => 'Transport',
    'block_repair'       => 'The work',

    'the_unit'           => 'Container',
    'monthly_rate'       => 'Monthly rate',
    'months_abbr'        => 'Months',
    'month_abbr'         => 'mo',
    'miles_abbr'         => 'mi',

    'repair_unit'        => 'On which container?',
    'repair_pick_unit'   => 'Pick a container (optional)',
    'repair_unit_help'   => 'Leave empty if the container belongs to the customer and is not in inventory.',
    'work_details'       => 'What will be done?',
    'work_details_ph'    => "Two 36\" side doors\nBarred window on rear wall\nWhite exterior paint\nTreated wood floor",
    'work_details_help'  => 'One item per line. Printed under the line, in grey.',

    'pays_tax'           => 'TAX',
    'no_tax'             => 'NO TAX',
    'pays_tax_long'      => 'This line pays sales tax',
    'no_tax_long'        => 'This line does not pay sales tax',

    'rental_row'         => 'Term',
    'rental_months'      => 'Rental months',
    'rental_row_help'    => 'The amount is the monthly rate. Rentals are billed month by month, not upfront.',
    'rental_commitment'  => ':months-month commitment: $:total',
    'per_month'          => 'per month',
    'months_short'       => '{1}1 month|[2,*]:count months',
    'unit_picker_sub'    => 'Press Esc to close',
    'units_found'        => '{0}No units|{1}1 unit available|[2,*]:count units available',
    'unit_search_ph'     => 'ISO number, internal code, size or condition…',
    'unit_picker_help'   => 'Only units in the yard with no sale or rental on them appear here. Units purchased but still at the supplier depot cannot be sold yet.',
    'export_eligible'    => 'Export eligible',
    'unclassified'       => 'Unclassified',
    'already_in_line'    => 'Already on line :line.',
    'no_price'           => 'No price on file',
    'rent_per_month'     => 'Rent $:amount/mo',
    'no_units'           => 'No units available right now.',
    'no_units_match'     => 'No unit matches ":term".',

];
