{{--
    ═══════════════════════════════════════════════════════════════════════
    REGISTRAR UN COBRO
    ═══════════════════════════════════════════════════════════════════════
--}}
<div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h4 class="mb-0">Registrar cobro</h4>
            <small class="text-secondary">El dinero ya entró. Aquí se deja constancia y se aplica a las facturas.</small>
        </div>
        <a href="{{ route('finanzas.pagos.index') }}" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-arrow-left me-1"></i> Volver
        </a>
    </div>

    <form wire:submit="guardar">
        <div class="row">
            <div class="col-lg-8">

                {{-- 1 · CLIENTE --}}
                <div class="card mb-3">
                    <div class="card-header"><h6 class="card-title mb-0">1 · Cliente</h6></div>
                    <div class="card-body">

                        @if ($customer_id)
                            <div class="d-flex justify-content-between align-items-center border rounded p-3 bg-body-tertiary">
                                <div>
                                    <div class="fw-semibold">{{ $cliente->name }}</div>
                                    @if (! $cliente->allow_credit_card)
                                        <small class="text-secondary">No habilitado para pagar con tarjeta.</small>
                                    @endif
                                </div>
                                <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="quitarCliente">
                                    Cambiar
                                </button>
                            </div>
                        @else
                            <label class="form-label">Buscar cliente</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control @error('customer_id') is-invalid @enderror"
                                       placeholder="Empresa, contacto, teléfono o número de cliente…"
                                       wire:model.live.debounce.300ms="buscarCliente">
                            </div>
                            @error('customer_id')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror

                            @if ($this->resultadosCliente->isNotEmpty())
                                <div class="list-group mt-2">
                                    @foreach ($this->resultadosCliente as $c)
                                        <button type="button" class="list-group-item list-group-item-action"
                                                wire:key="cliente-{{ $c->id }}"
                                                wire:click="seleccionarCliente({{ $c->id }})">
                                            <div class="d-flex justify-content-between">
                                                <span class="fw-semibold">{{ $c->name }}</span>
                                                <small class="text-secondary">{{ $c->customer_number }}</small>
                                            </div>
                                        </button>
                                    @endforeach
                                </div>
                            @elseif (strlen(trim($buscarCliente)) >= 2)
                                <div class="text-secondary small mt-2">No se encontró ningún cliente con ese dato.</div>
                            @endif
                        @endif
                    </div>
                </div>

                {{-- 2 · EL PAGO --}}
                <div class="card mb-3">
                    <div class="card-header"><h6 class="card-title mb-0">2 · El pago</h6></div>
                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-6 col-md-4">
                                <label class="form-label">Método</label>
                                <select class="form-select @error('method') is-invalid @enderror" wire:model.live="method">
                                    @foreach ($metodos as $valor => $etiqueta)
                                        @continue($valor === 'credit_card' && $cliente && ! $cliente->allow_credit_card)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label">Monto recibido</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0.01"
                                           class="form-control @error('amount') is-invalid @enderror"
                                           wire:model.live="amount">
                                </div>
                                @error('amount') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label">Fecha recibido</label>
                                <input type="date" class="form-control @error('received_at') is-invalid @enderror"
                                       wire:model="received_at">
                            </div>

                            <div class="col-6 col-md-4">
                                <label class="form-label">
                                    Referencia
                                    @if (in_array($method, ['check','ach','wire','zelle']))
                                        <span class="text-danger">*</span>
                                    @endif
                                </label>
                                <input type="text" class="form-control @error('reference') is-invalid @enderror"
                                       placeholder="# de cheque, confirmación…"
                                       wire:model="reference">
                                @error('reference') <div class="text-danger small">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6 col-md-4 d-flex align-items-end">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="is-deposit" wire:model="is_deposit">
                                    <label class="form-check-label" for="is-deposit">
                                        Es un anticipo (todavía no hay factura)
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notas</label>
                                <textarea class="form-control" rows="2" wire:model="notes"></textarea>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- 3 · TARJETA — solo si el método es tarjeta --}}
                @if ($method === 'credit_card')
                    <div class="card mb-3 border-info">
                        <div class="card-header bg-info-subtle">
                            <h6 class="card-title mb-0">
                                <i class="bi bi-credit-card me-1"></i> Autorización de tarjeta
                            </h6>
                        </div>
                        <div class="card-body">

                            <div class="btn-group mb-3" role="group">
                                <input type="radio" class="btn-check" id="modo-existing" value="existing" wire:model.live="modoTarjeta">
                                <label class="btn btn-outline-secondary btn-sm" for="modo-existing">Usar una ya firmada</label>

                                <input type="radio" class="btn-check" id="modo-new" value="new" wire:model.live="modoTarjeta">
                                <label class="btn btn-outline-secondary btn-sm" for="modo-new">Autorización nueva</label>
                            </div>

                            @if ($modoTarjeta === 'existing')
                                @if ($this->autorizacionesDisponibles->isEmpty())
                                    <div class="alert alert-warning small mb-0">
                                        Este cliente no tiene ninguna autorización vigente. Elige
                                        "Autorización nueva" para capturarla ahora (RB-011).
                                    </div>
                                @else
                                    <label class="form-label">Autorización</label>
                                    <select class="form-select @error('credit_card_authorization_id') is-invalid @enderror"
                                            wire:model="credit_card_authorization_id">
                                        <option value="">Elige una…</option>
                                        @foreach ($this->autorizacionesDisponibles as $a)
                                            <option value="{{ $a->id }}">{{ $a->masked }} — {{ $a->cardholder_name }}</option>
                                        @endforeach
                                    </select>
                                    @error('credit_card_authorization_id') <div class="text-danger small">{{ $message }}</div> @enderror
                                @endif
                            @else
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Nombre del titular</label>
                                        <input type="text" class="form-control @error('nuevaAuthNombre') is-invalid @enderror"
                                               wire:model="nuevaAuthNombre">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Marca</label>
                                        <input type="text" class="form-control" placeholder="Visa, MC…" wire:model="nuevaAuthMarca">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Últimos 4</label>
                                        <input type="text" maxlength="4" class="form-control @error('nuevaAuthUltimos4') is-invalid @enderror"
                                               wire:model="nuevaAuthUltimos4">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Mes vence</label>
                                        <input type="number" min="1" max="12" class="form-control @error('nuevaAuthMes') is-invalid @enderror"
                                               wire:model="nuevaAuthMes">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Año vence</label>
                                        <input type="number" class="form-control @error('nuevaAuthAnio') is-invalid @enderror"
                                               wire:model="nuevaAuthAnio">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Monto autorizado</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01" class="form-control" wire:model="nuevaAuthMontoAutorizado"
                                                   placeholder="{{ number_format((float) $amount, 2) }}">
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Firmada el</label>
                                        <input type="date" class="form-control @error('nuevaAuthFirmadaEl') is-invalid @enderror"
                                               wire:model="nuevaAuthFirmadaEl">
                                    </div>

                                    <div class="col-12">
                                        <label class="form-label">Formulario firmado (PDF o foto)</label>
                                        <input type="file" class="form-control @error('nuevaAuthArchivo') is-invalid @enderror"
                                               wire:model="nuevaAuthArchivo">
                                        @error('nuevaAuthArchivo') <div class="text-danger small">{{ $message }}</div> @enderror
                                        <div class="form-text">Sin este documento no se puede procesar la tarjeta (RB-011).</div>
                                    </div>

                                    @if ($cliente && $cliente->type?->verifiesInSunbiz())
                                        <div class="col-12">
                                            <div class="alert alert-{{ $cliente->sunbiz_verified ? 'success' : 'danger' }} small mb-0">
                                                @if ($cliente->sunbiz_verified)
                                                    <i class="bi bi-patch-check me-1"></i> Empresa verificada en Sunbiz.
                                                @else
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    Esta empresa no está verificada en Sunbiz. No se puede aceptar la tarjeta (RB-012).
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if ($cliente && ! $cliente->type?->verifiesInSunbiz())
                                        <div class="col-12">
                                            <div class="form-check">
                                                <input class="form-check-input @error('clientePresenteEnYarda') is-invalid @enderror"
                                                       type="checkbox" id="presente-yarda" wire:model="clientePresenteEnYarda">
                                                <label class="form-check-label small" for="presente-yarda">
                                                    Confirmo que el cliente está presente en la yarda (RB-013).
                                                </label>
                                            </div>
                                            @error('clientePresenteEnYarda') <div class="text-danger small">{{ $message }}</div> @enderror
                                        </div>
                                    @endif
                                </div>
                            @endif

                        </div>
                    </div>
                @endif

                {{-- 4 · APLICAR A FACTURAS --}}
                @if ($customer_id && ! $is_deposit)
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="card-title mb-0">3 · Aplicar a facturas</h6>
                            @if ($this->facturasPendientes->isNotEmpty())
                                <div class="btn-group">
                                    <button type="button" class="btn btn-sm btn-outline-primary" wire:click="aplicarAutomatico">
                                        Aplicar automáticamente
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" wire:click="limpiarAplicaciones">
                                        Limpiar
                                    </button>
                                </div>
                            @endif
                        </div>
                        <div class="card-body p-0">
                            @if ($this->facturasPendientes->isEmpty())
                                <div class="text-secondary small p-3">
                                    Este cliente no tiene facturas pendientes en esta compañía. El
                                    pago quedará sin aplicar y podrás asignarlo después desde su ficha.
                                </div>
                            @else
                                <table class="table align-middle mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Factura</th>
                                            <th>Vence</th>
                                            <th class="text-end">Saldo</th>
                                            <th class="text-end" style="width: 160px;">Aplicar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($this->facturasPendientes as $f)
                                            <tr wire:key="linea-factura-{{ $f->id }}"
                                                class="{{ $f->isOverdue() ? 'table-danger-subtle' : '' }}">
                                                <td>{{ $f->invoice_number }}</td>
                                                <td>{{ $f->due_date?->format('d/m/Y') }}</td>
                                                <td class="text-end">${{ number_format((float) $f->balance_due, 2) }}</td>
                                                <td>
                                                    <div class="input-group input-group-sm">
                                                        <span class="input-group-text">$</span>
                                                        <input type="number" step="0.01" min="0"
                                                               class="form-control text-end"
                                                               wire:model.live="aplicaciones.{{ $f->id }}">
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>

                        @if ($this->facturasPendientes->isNotEmpty())
                            <div class="card-footer d-flex justify-content-between">
                                <span>Aplicado: <strong>${{ number_format($this->totalAplicado, 2) }}</strong></span>
                                <span>
                                    Sin asignar:
                                    <strong class="{{ $this->saldoSinAsignar < -0.001 ? 'text-danger' : '' }}">
                                        ${{ number_format($this->saldoSinAsignar, 2) }}
                                    </strong>
                                </span>
                            </div>
                        @endif
                    </div>
                @endif

            </div>

            {{-- BARRA LATERAL --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <dl class="row mb-0 small">
                            <dt class="col-6">Monto recibido</dt>
                            <dd class="col-6 text-end">${{ number_format((float) $amount, 2) }}</dd>

                            @if (! $is_deposit)
                                <dt class="col-6">Aplicado a facturas</dt>
                                <dd class="col-6 text-end">${{ number_format($this->totalAplicado, 2) }}</dd>
                            @endif

                            <dt class="col-6 fw-semibold">Quedará sin aplicar</dt>
                            <dd class="col-6 text-end fw-semibold">
                                ${{ number_format($is_deposit ? (float) $amount : $this->saldoSinAsignar, 2) }}
                            </dd>
                        </dl>
                    </div>
                    <div class="card-footer d-grid gap-2">
                        <button type="submit" class="btn btn-primary" @disabled(! $customer_id)>
                            <i class="bi bi-check-lg me-1"></i> Registrar cobro
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
