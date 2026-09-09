{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DEL PAGO
    ═══════════════════════════════════════════════════════════════════════
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0">
                Pago {{ $payment->payment_number }}
                <x-ui.badge :color="$payment->status->color()" :label="$payment->status->label()" class="ms-2" />
            </h4>
            <small class="text-secondary">
                {{ $payment->customer?->name }} · {{ $payment->method->label() }} ·
                recibido el {{ $payment->received_at?->format('d/m/Y') }}
            </small>
        </div>
        <a href="{{ route('finanzas.pagos.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show">
            <i class="bi bi-exclamation-triangle me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    @if (in_array($payment->status, [\App\Enums\PaymentStatus::Failed, \App\Enums\PaymentStatus::Refunded, \App\Enums\PaymentStatus::Disputed]))
        <div class="alert alert-warning">
            <i class="bi bi-info-circle me-1"></i>
            Este pago está marcado como <strong>{{ $payment->status->label() }}</strong>.
            Todo lo que tenía aplicado a facturas ya fue revertido automáticamente.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-8">

            {{-- DATOS DEL PAGO --}}
            <div class="card mb-3">
                <div class="card-header"><h6 class="card-title mb-0">Datos del pago</h6></div>
                <div class="card-body">
                    <dl class="row mb-0 small">
                        <dt class="col-4">Monto recibido</dt>
                        <dd class="col-8">${{ number_format((float) $payment->amount, 2) }}</dd>

                        @if ((float) $payment->fee_amount > 0)
                            <dt class="col-4">Retenido por Square</dt>
                            <dd class="col-8">${{ number_format((float) $payment->fee_amount, 2) }}</dd>

                            <dt class="col-4">Neto al banco</dt>
                            <dd class="col-8">${{ number_format((float) $payment->net_amount, 2) }}</dd>
                        @endif

                        <dt class="col-4">Referencia</dt>
                        <dd class="col-8">{{ $payment->reference ?: '—' }}</dd>

                        @if ($payment->cardAuth)
                            <dt class="col-4">Tarjeta</dt>
                            <dd class="col-8">{{ $payment->card_brand }} ····{{ $payment->card_last4 }}</dd>
                        @endif

                        @if ($payment->is_deposit)
                            <dt class="col-4">Tipo</dt>
                            <dd class="col-8"><span class="badge text-bg-light border">Anticipo</span></dd>
                        @endif

                        <dt class="col-4">Registrado por</dt>
                        <dd class="col-8">{{ $payment->createdBy?->name ?? '—' }}</dd>

                        @if ($payment->notes)
                            <dt class="col-4">Notas</dt>
                            <dd class="col-8" style="white-space: pre-line;">{{ $payment->notes }}</dd>
                        @endif
                    </dl>
                </div>
            </div>

            {{-- FACTURAS DONDE SE APLICÓ --}}
            <div class="card mb-3">
                <div class="card-header"><h6 class="card-title mb-0">Aplicado a</h6></div>
                <div class="card-body p-0">
                    @if ($payment->allocations->isEmpty())
                        <div class="text-secondary small p-3">
                            Este pago todavía no se aplicó a ninguna factura.
                        </div>
                    @else
                        <table class="table align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Factura</th>
                                    <th>Fecha</th>
                                    <th class="text-end">Monto aplicado</th>
                                    <th class="text-end" style="width: 90px;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($payment->allocations as $a)
                                    <tr wire:key="asignacion-{{ $a->id }}">
                                        <td>
                                            @if ($a->invoice)
                                                <a href="{{ route('finanzas.facturacion.show', $a->invoice) }}" class="text-decoration-none">
                                                    {{ $a->invoice->invoice_number }}
                                                </a>
                                            @else
                                                <span class="text-secondary">Factura eliminada</span>
                                            @endif
                                        </td>
                                        <td>{{ $a->allocated_at?->format('d/m/Y') }}</td>
                                        <td class="text-end">${{ number_format((float) $a->amount, 2) }}</td>
                                        <td class="text-end">
                                            @if ($payment->status->isSettled())
                                                <button type="button" class="btn btn-sm btn-outline-danger"
                                                        wire:click="pedirReversion({{ $a->id }})"
                                                        title="Revertir esta aplicación">
                                                    <i class="bi bi-arrow-counterclockwise"></i>
                                                </button>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif
                </div>

                @if ($payment->status->isSettled() && (float) $payment->unapplied_amount > 0.001)
                    <div class="card-footer">
                        <div class="fw-semibold mb-2">
                            Sin aplicar: ${{ number_format((float) $payment->unapplied_amount, 2) }}
                        </div>

                        @if ($this->facturasPendientes->isEmpty())
                            <div class="text-secondary small">
                                Este cliente no tiene facturas pendientes en esta compañía.
                            </div>
                        @else
                            <div class="row g-2 align-items-end">
                                <div class="col-md-6">
                                    <label class="form-label small mb-1">Aplicar a</label>
                                    <select class="form-select form-select-sm" wire:model.live="facturaParaAplicar">
                                        <option value="">Elige una factura…</option>
                                        @foreach ($this->facturasPendientes as $f)
                                            <option value="{{ $f->id }}">
                                                {{ $f->invoice_number }} — saldo ${{ number_format((float) $f->balance_due, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small mb-1">Monto</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" class="form-control" wire:model="montoParaAplicar">
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <button type="button" class="btn btn-primary btn-sm w-100" wire:click="aplicarSaldo">
                                        Aplicar
                                    </button>
                                </div>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

        </div>

        {{-- BARRA LATERAL — ACCIONES --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header"><h6 class="card-title mb-0">Estado del pago</h6></div>
                <div class="card-body d-grid gap-2">

                    @if ($payment->status->value === 'pending')
                        <button type="button" class="btn btn-success btn-sm" wire:click="pedirCambioEstado('completed')">
                            <i class="bi bi-check-lg me-1"></i> Confirmar
                        </button>
                    @endif

                    @if ($payment->status->isSettled())
                        <button type="button" class="btn btn-outline-danger btn-sm" wire:click="pedirCambioEstado('failed')">
                            Marcar como fallido
                        </button>
                        @if ($payment->method->value === 'credit_card')
                            <button type="button" class="btn btn-outline-warning btn-sm" wire:click="pedirCambioEstado('disputed')">
                                Marcar en disputa
                            </button>
                        @endif
                        <button type="button" class="btn btn-outline-secondary btn-sm" wire:click="pedirCambioEstado('refunded')">
                            Marcar como reembolsado
                        </button>
                    @endif

                    <div class="form-text">
                        Marcar el pago como fallido, en disputa o reembolsado revierte
                        automáticamente todo lo que tuviera aplicado a facturas.
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ─────────────────────────────────────────────────────────────
         CONFIRMACIÓN: REVERTIR UNA ASIGNACIÓN
    ───────────────────────────────────────────────────────────── --}}
    @if ($confirmando === 'revertir')
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Revertir esta aplicación</h5>
                        <button type="button" class="btn-close" wire:click="cancelar"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            El monto vuelve al saldo del pago y la factura vuelve a mostrar la
                            deuda que tenía antes. No se borra nada: queda el rastro de que se
                            aplicó y se revirtió.
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="cancelar">Cancelar</button>
                        <button class="btn btn-danger" wire:click="confirmarReversion">
                            <i class="bi bi-arrow-counterclockwise me-1"></i> Revertir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

    {{-- ─────────────────────────────────────────────────────────────
         CONFIRMACIÓN: CAMBIAR ESTADO
    ───────────────────────────────────────────────────────────── --}}
    @if ($confirmando === 'estado')
        <div class="modal fade show d-block" tabindex="-1" style="background: rgba(0,0,0,.5);">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            Cambiar a "{{ \App\Enums\PaymentStatus::tryFrom($nuevoEstado)?->label() }}"
                        </h5>
                        <button type="button" class="btn-close" wire:click="cancelar"></button>
                    </div>
                    <div class="modal-body">
                        @if (in_array($nuevoEstado, ['failed', 'refunded', 'disputed']) && $payment->allocations->isNotEmpty())
                            <div class="alert alert-warning small">
                                Este pago tiene ${{ number_format($payment->allocated_amount, 2) }} aplicados
                                a {{ $payment->allocations->count() }} factura(s). Al confirmar, esas
                                aplicaciones se revierten y las facturas vuelven a mostrar el saldo
                                pendiente.
                            </div>
                        @endif

                        <label class="form-label">Nota (opcional)</label>
                        <textarea class="form-control" rows="2"
                                  placeholder="Ej: cheque devuelto por fondos insuficientes."
                                  wire:model="notaCambioEstado"></textarea>
                    </div>
                    <div class="modal-footer">
                        <button class="btn btn-secondary" wire:click="cancelar">Cancelar</button>
                        <button class="btn btn-primary" wire:click="confirmarCambioEstado">
                            Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    @endif

</div>
