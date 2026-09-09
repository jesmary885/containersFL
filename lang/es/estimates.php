<?php

/*
|--------------------------------------------------------------------------
| MÓDULO DE PRESUPUESTOS
|--------------------------------------------------------------------------
|
| Todo el texto que aparece en las pantallas de presupuesto.
|
| ── CÓMO ESTÁN NOMBRADAS LAS CLAVES ──
|
| Por SIGNIFICADO, no por dónde aparecen. 'delivery_zip' y no
| 'campo_4_columna_1'. Si mañana se mueve el campo de sitio, la clave
| sigue teniendo sentido.
|
| Las claves que terminan en _help son los textos chiquitos de ayuda que
| van debajo del campo. Las que terminan en _hint son los tooltips.
|
*/

return [

    /* ---------------------------------------------------------------
     | ENCABEZADO
     * ------------------------------------------------------------ */
    'title_new'          => 'Nuevo presupuesto',
    'title_edit'         => 'Editar presupuesto :number',
    'subtitle_new'       => 'El número se asigna automáticamente al guardar.',
    'subtitle_edit'      => 'Los cambios se guardan al presionar el botón de la derecha.',
    'errors_title'       => 'No se pudo guardar. Falta esto:',
    'missing_one'        => 'Falta 1 dato',
    'missing_many'       => 'Faltan :count datos',

    /* ---------------------------------------------------------------
     | 1 · CLIENTE
     * ------------------------------------------------------------ */
    'section_customer'   => 'Cliente',
    'search_customer'    => 'Buscar cliente',
    'search_customer_ph' => 'Nombre de la empresa, del contacto, teléfono o número de cliente…',
    'customer_not_found' => 'No se encontró ningún cliente con ese dato.',
    'no_contact'         => 'Sin contacto registrado',
    'tax_exempt_badge'   => 'Exento de impuesto',

    /* ---------------------------------------------------------------
     | 2 · DATOS DEL DOCUMENTO
     * ------------------------------------------------------------ */
    'section_document'   => 'Datos del documento',
    'issue_date'         => 'Fecha de emisión',
    'valid_until'        => 'Válido hasta',
    'valid_until_hint'   => 'Pasada esta fecha el precio ya no se respeta. Se recalcula sola al cambiar la fecha de emisión.',
    'valid_until_help'   => 'Se ajusta sola con la fecha de emisión.',

    'terms'              => 'Términos de pago',
    'terms_other'        => 'Otro — escribirlo a mano',
    'terms_other_ph'     => 'Ej: Net 45, 30% anticipo y saldo a 60 días…',
    'terms_other_help'   => 'Esto es lo que se va a imprimir en el documento, tal cual.',

    'salesperson_help'   => 'De aquí sale la comisión si se concreta.',

    'payment_method'         => 'Forma de pago prevista',
    'payment_method_hint'    => 'Con esto el sistema sabe si tiene que sumar el recargo de tarjeta. Se copia a la factura al convertir.',
    'payment_method_unknown' => '— Todavía no se sabe —',

    'use_type'           => '¿Para qué va a usar el contenedor?',
    'export_notice'      => 'Venta de exportación',
    'export_no_tax'      => 'No lleva impuesto de venta.',
    'export_needs_cert'  => 'Antes de facturar hace falta el certificado CSC en PDF.',
    'export_no_delivery' => 'Normalmente no lleva entrega: el cliente contrata su naviera.',

    /* ---------------------------------------------------------------
     | 3 · DIRECCIONES
     * ------------------------------------------------------------ */
    'section_addresses'  => 'Direcciones',
    'ship_elsewhere'     => 'La entrega va a otra dirección',
    'addresses_intro'    => '<strong>Facturar a</strong> es la dirección fiscal, la que va en el documento. <strong>Entregar en</strong> es dónde se deja físicamente el contenedor. Si son la misma, deje el interruptor apagado.',
    'bill_to_label'      => 'Facturar a (BILL TO)',
    'ship_to_label'      => 'Entregar en (SHIP TO)',
    'street_ph'          => 'Calle y número',
    'line2_bill_ph'      => 'Suite, unidad, referencia',
    'line2_ship_ph'      => 'Portón, referencia de la obra',

    'autofilled'         => 'Se llenaron las direcciones con las que tiene guardadas <strong>:name</strong>.',
    'autofilled_ship'    => 'Como este cliente tiene una dirección de entrega distinta de la de facturación, se encendió el interruptor de arriba y se cargó en <strong>Entregar en</strong>. Si esta vez la entrega va a la misma dirección de la factura, apáguelo.',
    'autofilled_note'    => 'Puede cambiar cualquier campo: lo que quede escrito acá es lo que se imprime, y no toca la ficha del cliente.',

    'no_saved_address'   => 'Este cliente todavía no tiene ninguna dirección guardada, por eso los campos salieron en blanco.',
    'save_to_customer'   => 'Guardar esta dirección en la ficha del cliente',
    'save_to_customer_x' => '— así el próximo presupuesto se llena solo',
    'addresses_frozen'   => 'Estas direcciones se guardan como una copia del día de hoy. Si el cliente se muda el año que viene, este documento seguirá mostrando dónde estaba hoy.',

    /* ---------------------------------------------------------------
     | 4 · CONCEPTOS
     |
     | Antes esta sección era la 5. Subió un lugar porque desapareció
     | la sección "Entrega", que tenía un solo juego de campos para
     | todo el documento y no servía para un presupuesto con varios
     | destinos.
     * ------------------------------------------------------------ */
    'section_lines'      => 'Conceptos',
    'group_selected'     => 'Agrupar seleccionadas',
    'add_line'           => 'Agregar línea',
    'select_hint'        => 'Marque dos o más líneas y use «Agrupar seleccionadas».',

    'col_concept'        => 'Concepto',
    'col_description'    => 'Descripción',
    'col_qty'            => 'Cant.',
    'col_price'          => 'Precio',
    'col_amount'         => 'Importe',
    'col_tax'            => 'Tax',
    'col_group'          => 'Grupo',
    'tax_col_hint'       => 'Marcado = paga el 7%. El transporte nunca se marca.',

    'free_line'          => '— Libre —',
    'description_ph'     => 'Lo que va a leer el cliente',
    'find_unit'          => 'Buscar unidad',
    'remove_unit'        => 'Quitar la unidad',
    'remove_line'        => 'Quitar línea',
    'remove_from_group'  => 'Sacar esta línea del grupo :group',

    /* ---------------------------------------------------------------
     | LA FILA DE ENTREGA
     |
     | Aparece dentro de la línea cuando el concepto elegido es una
     | entrega. Es lo que reemplazó a la vieja sección 4: cada entrega
     | tiene su destino, sus millas y su tarifa, porque un presupuesto
     | de tres contenedores puede ir a tres sitios distintos.
     * ------------------------------------------------------------ */
    'delivery_row'       => 'Datos de esta entrega',
    'delivery_zip'       => 'ZIP destino',
    'miles'              => 'Millas',
    'rate_per_mile'      => 'Tarifa/milla',
    'rate_hint'          => 'Viene de Configuración. Se puede cambiar solo para esta línea.',
    'delivery_row_help'  => 'El importe se calcula solo: millas × tarifa. Queda editable.',
    'delivery_export'    => 'En exportación casi nunca hay entrega: el cliente contrata su naviera. Cargue esta línea solo si es el caso del contenedor vacío hacia una terminal.',

    /* ---------------------------------------------------------------
     | LOS GRUPOS
     * ------------------------------------------------------------ */
    'groups_title'       => 'Cómo se imprime cada grupo',
    'groups_intro'       => 'Estas líneas se suman en un solo renglón para el cliente, pero por dentro cada una conserva si paga impuesto o no. Es lo que permite mostrar un precio consolidado y aun así cobrar el 7% solo sobre el contenedor.',
    'group_lines'        => 'Líneas :lines',
    'customer_sees'      => 'El cliente ve:',
    'group_text_ph'      => 'Ej: Contenedor 40HC entregado en Homestead',
    'group_text_help'    => 'Este es el texto que va a leer el cliente en ese renglón.',

    /* ---------------------------------------------------------------
     | 5 · NOTAS
     * ------------------------------------------------------------ */
    'section_notes'      => 'Notas',
    'doc_notes'          => 'Notas del documento',
    'doc_notes_ph'       => 'Aparecen impresas en el presupuesto.',
    'footer_terms'       => 'Condiciones al pie',
    'footer_terms_ph'    => 'Si se deja vacío se usan las de la empresa.',

    /* ---------------------------------------------------------------
     | TOTALES
     * ------------------------------------------------------------ */
    'discount_field'     => 'Descuento ($)',
    'tax_rate_field'     => 'Sales tax (%)',
    'customer_exempt'    => 'Cliente exento de impuesto',
    'pays_with_card'     => 'Pagará con tarjeta (+:percent%)',
    'non_taxable_freight'=> 'No gravable (transporte)',
    'exempt_note'        => 'Cliente exento. Al facturar se guardará cuál certificado lo justifica, como respaldo ante el estado.',

    /* ---------------------------------------------------------------
     | BOTONES Y ENVÍO
     * ------------------------------------------------------------ */
    'send_hint'          => 'Guarda el presupuesto y lo manda al correo del cliente.',
    'sent_ok'            => 'Presupuesto :number enviado a :email.',
    'sent_queued'        => 'Presupuesto :number guardado. El correo salió a la cola de envío.',
    'send_no_email'      => 'Se guardó el presupuesto, pero el cliente no tiene correo cargado. Agrégueselo en su ficha y vuelva a enviarlo.',
    'send_failed'        => 'Se guardó el presupuesto, pero el correo no pudo salir. Puede reintentarlo desde el listado.',

    /* ---------------------------------------------------------------
     | BUSCADOR DE UNIDADES
     * ------------------------------------------------------------ */
    'unit_picker_title'  => 'Elegir unidad para la línea :line',
    /* ── El flujo ── */
    'process'            => 'Procesar',
    'process_hint'       => 'Arma el documento y te lo muestra. Todavía no se envía nada.',
    'review_title'       => 'Revisa antes de enviar',
    'review_text'        => 'Así es como lo va a ver el cliente. Si algo no cuadra, vuelve a editarlo. Cuando esté bien, envíalo.',
    'send_now'           => 'Guardar y enviar correo',
    'back_to_edit'       => 'Seguir editando',
    'locked_until_sent'  => 'Disponible cuando el presupuesto se envíe.',

    /* ── El texto que se escribe solo ── */
    'auto_rental'        => 'Renta mensual · :unit',
    'auto_sale'          => 'Venta de contenedor · :unit',
    'auto_repair'        => 'Modificación de contenedor · :unit',

    /* ── El editor de renglones ── */
    'line_new'           => 'Nuevo renglón',
    'line_edit'          => 'Renglón :n',
    'line_modal_sub'     => 'Escapar para cancelar',
    'line_empty'         => 'Sin descripción',
    'no_lines'           => 'Todavía no hay conceptos. Agrega el primero.',
    'line_amount'        => 'Importe del renglón',
    'monthly_amount'     => 'Mensualidad',
    'description_help'   => 'Es el texto que ve el cliente en el documento.',

    'block_unit'         => 'La unidad',
    'block_delivery'     => 'El transporte',
    'block_repair'       => 'El trabajo',

    'the_unit'           => 'Contenedor',
    'monthly_rate'       => 'Mensualidad',
    'months_abbr'        => 'Meses',
    'month_abbr'         => 'mes',
    'miles_abbr'         => 'mi',

    'repair_unit'        => '¿Sobre qué contenedor?',
    'repair_pick_unit'   => 'Elegir contenedor (opcional)',
    'repair_unit_help'   => 'Déjalo vacío si el contenedor es del cliente y no está en el inventario.',
    'work_details'       => '¿Qué se le va a hacer?',
    'work_details_ph'    => "Dos puertas laterales de 36\"\nVentana con reja en pared trasera\nPintura exterior blanca\nPiso de madera tratada",
    'work_details_help'  => 'Un trabajo por línea. Se imprime debajo del renglón, en gris.',

    'pays_tax'           => 'TAX',
    'no_tax'             => 'SIN TAX',
    'pays_tax_long'      => 'Este renglón paga sales tax',
    'no_tax_long'        => 'Este renglón no paga sales tax',

    'rental_row'         => 'Plazo',
    'rental_months'      => 'Meses de renta',
    'rental_row_help'    => 'El importe es la mensualidad. La renta se factura mes a mes, no de golpe.',
    'rental_commitment'  => 'Compromiso a :months meses: $:total',
    'per_month'          => 'por mes',
    'months_short'       => '{1}1 mes|[2,*]:count meses',
    'unit_picker_sub'    => 'Escapar para cerrar',
    'units_found'        => '{0}Ninguna unidad|{1}1 unidad disponible|[2,*]:count unidades disponibles',
    'unit_search_ph'     => 'Número ISO, código interno, medida o condición…',
    'unit_picker_help'   => 'Solo aparecen las unidades que están en yarda y sin venta ni renta encima. Las compradas que siguen en el depósito del proveedor no se pueden vender todavía.',
    'export_eligible'    => 'Apto exportación',
    'unclassified'       => 'Sin clasificar',
    'already_in_line'    => 'Ya está en la línea :line.',
    'no_price'           => 'Sin precio cargado',
    'rent_per_month'     => 'Renta $:amount/mes',
    'no_units'           => 'No hay unidades disponibles en este momento.',
    'no_units_match'     => 'Ninguna unidad coincide con «:term».',

];
