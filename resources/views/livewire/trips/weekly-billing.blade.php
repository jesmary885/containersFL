{{--
    ═══════════════════════════════════════════════════════════════════════
    FACTURAR LA SEMANA
    ═══════════════════════════════════════════════════════════════════════

    Lo que pidió Denisse el 16-09: una compañía, una semana, una factura.

    Arranca con la semana pasada ya puesta, que es lo que se factura el
    lunes. Si hace falta otra, se cambian las fechas.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">Facturar viajes de la semana</h4>
            <small class="text-secondary">
                Todos los viajes de una compañía en una sola factura.
            </small>
        </div>

        <a href="{{ route('operaciones.viajes.index') }}" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left me-1"></i> Volver a viajes
        </a>
    </div>

    <x-ui.errores id="resumen-errores" titulo="No se pudo generar la factura." />

    {{-- ── 1 · QUIÉN Y CUÁNDO ── --}}
    <div class="card mb-3">
        <div class="card-body">
            <div class="row g-3 align-items-end">

                <div class="col-12 col-md-5">
                    <label class="form-label">Compañía cliente</label>
                    <select class="form-select" wire:model.live="customer_id">
                        <option value="">— Elegir —</option>
                        @foreach ($clientes as $cl)
                            <option value="{{ $cl->id }}">{{ $cl->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Desde</label>
                    <input type="date" class="form-control" wire:model.live="desde">
                </div>

                <div class="col-6 col-md-3">
                    <label class="form-label">Hasta</label>
                    <input type="date" class="form-control" wire:model.live="hasta">
                </div>

            </div>
        </div>
    </div>

    {{-- ── 2 · LOS VIAJES ── --}}
    @if (! $customer_id)

        <div class="alert alert-light border">
            <i class="bi bi-arrow-up me-1"></i>
            Elija una compañía para ver sus viajes sin facturar.
        </div>

    @elseif ($viajes->isEmpty())

        <div class="alert alert-info">
            <i class="bi bi-info-circle me-1"></i>
            Esa compañía no tiene viajes completados sin facturar en ese rango de fechas.
            <div class="small mt-1">
                Un viaje solo aparece acá si está <strong>completado</strong>, tiene precio
                y todavía no entró en ninguna factura.
            </div>
        </div>

    @else

        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-semibold">
                    {{ $viajes->count() }} viajes sin facturar
                </h6>

                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            wire:click="marcarTodos">
                        Marcar todos
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary"
                            wire:click="desmarcarTodos">
                        Ninguno
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 50px;"></th>
                            <th>Viaje</th>
                            <th>Completado</th>
                            <th>Destino</th>
                            <th>Contenedor</th>
                            <th class="text-end">Millas</th>
                            <th class="text-end">Importe</th>
                        </tr>
                    </thead>

                    <tbody>
                    @foreach ($viajes as $v)
                        <tr class="{{ ! empty($elegidos[$v->id]) ? 'table-active' : '' }}">
                            <td>
                                <input class="form-check-input" type="checkbox"
                                       wire:model.live="elegidos.{{ $v->id }}">
                            </td>

                            <td>
                                <a href="{{ route('operaciones.viajes.show', $v) }}"
                                   class="doc-numero" target="_blank">{{ $v->trip_number }}</a>
                                <div class="small text-secondary">{{ $v->type?->label() }}</div>
                            </td>

                            <td>{{ $v->completed_at?->format('d/m/Y') ?? '—' }}</td>

                            <td>
                                {{ $v->destination_address['city'] ?? $v->destination_zip ?? '—' }}
                            </td>

                            <td>{{ $v->container?->full_identifier ?? '—' }}</td>

                            <td class="text-end">
                                {{ $v->miles ? rtrim(rtrim(number_format((float) $v->miles, 1), '0'), '.') : '—' }}
                            </td>

                            <td class="text-end fw-semibold">
                                ${{ number_format((float) $v->customer_price, 2) }}
                            </td>
                        </tr>
                    @endforeach
                    </tbody>

                    <tfoot>
                        <tr class="border-top">
                            <td colspan="6" class="text-end fw-semibold">Total a facturar</td>
                            <td class="text-end fw-semibold fs-5">
                                ${{ number_format($total, 2) }}
                            </td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="small text-secondary">
                La factura se crea en <strong>borrador</strong>. Revísela y emítala usted.
            </div>

            <button type="button" class="btn btn-primary"
                    wire:click="generar"
                    wire:loading.attr="disabled"
                    wire:target="generar"
                    @disabled($total <= 0)>
                <span wire:loading.remove wire:target="generar">
                    <i class="bi bi-receipt me-1"></i>
                    Generar factura de ${{ number_format($total, 2) }}
                </span>
                <span wire:loading wire:target="generar">
                    <span class="spinner-border spinner-border-sm me-1"></span>
                    Generando...
                </span>
            </button>
        </div>

    @endif

</div>
