{{--
    ═══════════════════════════════════════════════════════════════════════
    LAS MARCAS DE UN CLIENTE
    ═══════════════════════════════════════════════════════════════════════

    Las etiquetitas que resumen su situación. Están en un archivo aparte
    porque las usan las dos vistas de la tabla.

        @include('livewire.customers.partials.marcas', ['c' => $cliente])

    ── EL ORDEN NO ES CASUAL ──

    Primero lo que estorba —papel vencido, retención—, después lo que
    informa. Si alguien solo alcanza a leer la primera etiqueta, que sea
    la que le hace cambiar lo que iba a hacer.
--}}

@if ($c->documentos_alerta_count > 0)
    <span class="badge bg-danger-subtle text-danger"
          title="Tiene documentos vencidos o a punto de vencer">
        <i class="bi bi-paperclip"></i> {{ $c->documentos_alerta_count }}
    </span>
@endif

@if ($c->credit_hold)
    <span class="badge bg-warning-subtle text-warning-emphasis"
          title="No se le vende a crédito">
        Retenido
    </span>
@endif

@if ($c->tax_exempt)
    <span class="badge bg-success-subtle text-success"
          title="Certificado de exención vigente">
        Exento
    </span>
@endif

@if ($c->allow_credit_card)
    <span class="badge bg-light text-dark border" title="Puede pagar con tarjeta">
        <i class="bi bi-credit-card"></i>
    </span>
@endif

@unless ($c->is_active)
    <span class="badge bg-secondary-subtle text-secondary">Desactivado</span>
@endunless
