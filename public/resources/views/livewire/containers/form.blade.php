{{--
    ═══════════════════════════════════════════════════════════════════════
    FICHA DEL CONTENEDOR — crear y editar
    ═══════════════════════════════════════════════════════════════════════

    Una sola pantalla con cinco bloques, sin pasos.

    ── POR QUÉ AQUÍ NO HAY ASISTENTE Y EN CLIENTES SÍ ──

    Porque se usa distinto. Un cliente se registra uno cada tanto, con la
    persona al teléfono. Los contenedores llegan en tanda: un release de
    siete se carga de un tirón, siete fichas casi iguales.

    Ahí un asistente de tres pasos son dos clics de más por unidad.

    Por eso está el botón "Guardar y registrar otro": conserva la
    clasificación, los costos, los precios y la ubicación, y limpia solo
    el número. La segunda unidad del release es teclear el número y
    guardar.
--}}
<div>

    {{-- ───── ENCABEZADO ───── --}}
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">

        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $containerId ? 'Editar contenedor' : 'Nuevo contenedor' }}
            </h4>
            <small class="text-secondary">
                @if ($containerId)
                    Los cambios de estado y ubicación quedan en el historial de la unidad.
                @else
                    El número es la cédula del contenedor: no se puede repetir.
                @endif
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="leyenda-obligatorio"><strong>*</strong> Campo obligatorio</span>

            <a href="{{ route('operaciones.contenedores.index') }}" class="btn btn-outline-secondary">
                <i class="bi bi-list-ul me-1"></i> Ver el inventario
            </a>
        </div>

    </div>

    @if (session('exito'))
        <div class="alert alert-success alert-dismissible fade show">
            <i class="bi bi-check-circle-fill me-1"></i> {{ session('exito') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder guardar." />

    <form wire:submit.prevent="guardar">
        <div class="row g-3">

            {{-- ═════════════════════════════════════════════════════
                 COLUMNA IZQUIERDA
            ═════════════════════════════════════════════════════ --}}
            <div class="col-12 col-xl-8">

                {{-- ───── 1 · CÓMO SE LLAMA ───── --}}
                <div class="card mb-3 seccion seccion-cliente">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">1</span>
                            <i class="bi bi-upc-scan"></i>
                            <span>Cómo se llama</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-5">
                                <label class="form-label">Número de contenedor</label>
                                <input type="text"
                                       class="form-control text-uppercase font-monospace @error('container_number') is-invalid @enderror"
                                       placeholder="MSCU1234567"
                                       wire:model.blur="container_number">
                                @error('container_number')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    El que viene pintado en la unidad. Es único en todo el sistema.
                                </div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Código interno</label>
                                <input type="text"
                                       class="form-control @error('internal_code') is-invalid @enderror"
                                       placeholder="Unit #3"
                                       wire:model.blur="internal_code">
                                @error('internal_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">
                                    Con el que la yarda la pide. Para las que llegan sin número legible.
                                </div>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Año</label>
                                <input type="number"
                                       class="form-control @error('year_manufactured') is-invalid @enderror"
                                       placeholder="{{ now()->year - 12 }}"
                                       wire:model.blur="year_manufactured">
                                @error('year_manufactured')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="col-12">
                                <div class="alert alert-light border py-2 small mb-0">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Hace falta <strong>uno de los dos</strong>. Sin ninguno, la unidad
                                    no se puede buscar ni poner en un presupuesto.
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ───── 2 · QUÉ ES ───── --}}
                <div class="card mb-3 seccion seccion-datos">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">2</span>
                            <i class="bi bi-box-seam"></i>
                            <span>Qué es</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Medida <span class="text-danger">*</span></label>
                                <select class="form-select @error('container_size_id') is-invalid @enderror"
                                        wire:model.live="container_size_id">
                                    <option value="">— Elegir —</option>
                                    @foreach ($medidas as $m)
                                        <option value="{{ $m->id }}">{{ $m->name }}</option>
                                    @endforeach
                                </select>
                                @error('container_size_id')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                                <div class="form-text">Al elegirla se proponen los pesos.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Tipo</label>
                                <select class="form-select" wire:model="container_type_id">
                                    <option value="">— Elegir —</option>
                                    @foreach ($tipos as $t)
                                        <option value="{{ $t->id }}">{{ $t->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Condición</label>
                                <select class="form-select" wire:model="container_condition_id">
                                    <option value="">— Elegir —</option>
                                    @foreach ($condiciones as $c)
                                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">Nuevo, usado, una sola travesía.</div>
                            </div>

                            <div class="col-12 col-md-4">
                                <label class="form-label">Calidad</label>
                                <select class="form-select" wire:model.live="container_grade_id">
                                    <option value="">— Elegir —</option>
                                    @foreach ($calidades as $g)
                                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    Cargo Worthy, WWT o AS-IS. Solo el primero exporta.
                                </div>
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label">Material</label>
                                <select class="form-select" wire:model="material">
                                    @foreach ($materiales as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Tara (lbs)</label>
                                <input type="number" class="form-control"
                                       wire:model.blur="tare_weight_lbs">
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Peso máximo (lbs)</label>
                                <input type="number" class="form-control"
                                       wire:model.blur="max_weight_lbs">
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ───── 3 · DÓNDE ESTÁ ───── --}}
                <div class="card mb-3 seccion seccion-direccion">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">3</span>
                            <i class="bi bi-geo-alt"></i>
                            <span>Dónde está</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-4">
                                <label class="form-label">Estado <span class="text-danger">*</span></label>
                                <select class="form-select @error('status') is-invalid @enderror"
                                        wire:model.live="status">
                                    @foreach ($estados as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                @error('status') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">
                                    Solo <strong>En yarda</strong> cuenta como disponible para vender.
                                </div>
                            </div>

                            {{--
                                LA UBICACIÓN Y EL DEPÓSITO SON EXCLUYENTES.

                                O está en una yarda nuestra, o sigue en el
                                depósito del proveedor. Las dos a la vez no
                                significan nada, y el formulario apaga la que
                                no corresponde al cambiar de estado.
                            --}}
                            @if ($status !== 'at_supplier')
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Ubicación</label>
                                    <select class="form-select" wire:model="location_id">
                                        <option value="">— Sin ubicación —</option>
                                        @foreach ($ubicaciones as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Dónde está parada dentro de la yarda.</div>
                                </div>
                            @else
                                <div class="col-12 col-md-4">
                                    <label class="form-label">Depósito</label>
                                    <select class="form-select" wire:model="depot_id">
                                        <option value="">— Elegir —</option>
                                        @foreach ($depositos as $d)
                                            <option value="{{ $d->id }}">{{ $d->name }}</option>
                                        @endforeach
                                    </select>
                                    <div class="form-text">Dónde está esperando que la recojan.</div>
                                </div>
                            @endif

                            <div class="col-12 col-md-4">
                                <label class="form-label">Recibida el</label>
                                <input type="date" class="form-control"
                                       wire:model="received_at">
                                <div class="form-text">El día que entró a la yarda.</div>
                            </div>

                            <div class="col-12">
                                <label class="form-label">Notas de condición</label>
                                <textarea class="form-control" rows="2"
                                          placeholder="Golpes, óxido, puertas duras... Lo que hay que saber antes de ofrecerla."
                                          wire:model.blur="condition_notes"></textarea>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ───── 4 · EXPORTACIÓN ───── --}}
                <div class="card mb-3 seccion seccion-entrega">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">4</span>
                            <i class="bi bi-globe-americas"></i>
                            <span>Exportación</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           id="exportable" wire:model.live="is_export_eligible">
                                    <label class="form-check-label" for="exportable">
                                        Apta para exportar
                                    </label>
                                </div>
                                <div class="form-text">
                                    Se propone sola según la calidad. Solo el Cargo Worthy califica.
                                </div>
                            </div>

                            @if ($is_export_eligible)
                                <div class="col-12 col-md-6">
                                    <label class="form-label">CSC vigente hasta</label>
                                    <input type="date" class="form-control"
                                           wire:model="csc_valid_through">
                                    <div class="form-text">
                                        La inspección la hace un tercero y es un <strong>gasto</strong>,
                                        no un ingreso. Va incluida en el precio de exportación.
                                    </div>
                                </div>
                            @endif

                        </div>
                    </div>
                </div>

            </div>

            {{-- ═════════════════════════════════════════════════════
                 COLUMNA DERECHA · EL DINERO
            ═════════════════════════════════════════════════════ --}}
            <div class="col-12 col-xl-4">

                <div class="card mb-3 seccion seccion-lineas">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">5</span>
                            <i class="bi bi-cash-coin"></i>
                            <span>Lo que costó y lo que vale</span>
                        </h6>
                    </div>

                    <div class="card-body">

                        <div class="fw-semibold small text-secondary mb-2">LO QUE NOS COSTÓ</div>

                        <div class="mb-2">
                            <label class="form-label small">Compra</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01"
                                       class="form-control @error('acquisition_cost') is-invalid @enderror"
                                       wire:model.live.debounce.500ms="acquisition_cost">
                            </div>
                            @error('acquisition_cost')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="mb-2">
                            <label class="form-label small">Recogida del depósito</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control"
                                       wire:model.live.debounce.500ms="pickup_cost">
                            </div>
                            <div class="form-text">
                                Es costo nuestro. Nunca se le cotiza al cliente.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Reacondicionamiento</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control"
                                       wire:model.live.debounce.500ms="reconditioning_cost">
                            </div>
                        </div>

                        <div class="rn-desglose mb-3">
                            <div class="rn-dg">
                                <span class="rn-dg-k">Costo puesto en yarda</span>
                                <span class="rn-dg-v">${{ number_format($this->costoTotal, 2) }}</span>
                                <span class="rn-dg-n">Compra + recogida + arreglos</span>
                            </div>
                        </div>

                        <hr>

                        <div class="fw-semibold small text-secondary mb-2">LO QUE SE LE COBRA</div>

                        <div class="mb-2">
                            <label class="form-label small">Precio de venta</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01"
                                       class="form-control @error('list_price') is-invalid @enderror"
                                       wire:model.live.debounce.500ms="list_price">
                            </div>
                            @error('list_price')
                                <div class="text-danger small">{{ $message }}</div>
                            @enderror
                            <div class="form-text">
                                Se precarga en el presupuesto. El vendedor lo puede cambiar.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small">Renta mensual</label>
                            <div class="input-group input-group-sm">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" class="form-control"
                                       wire:model.live.debounce.500ms="monthly_rate">
                                <span class="input-group-text">/mes</span>
                            </div>
                        </div>

                        {{--
                            EL MARGEN

                            Es el número que decide si la venta tiene sentido, y
                            hasta hoy había que sacarlo con una calculadora al
                            lado. Sale solo mientras se teclea el precio.
                        --}}
                        @if ($this->margen)
                            <div class="alert {{ $this->margen['monto'] > 0 ? 'alert-success' : 'alert-danger' }} py-2 small mb-0">
                                <i class="bi bi-graph-up-arrow me-1"></i>
                                <strong>
                                    {{ $this->margen['monto'] > 0 ? 'Deja' : 'Pierde' }}
                                    ${{ number_format(abs($this->margen['monto']), 2) }}
                                </strong>
                                ({{ $this->margen['porciento'] }}% sobre el costo)
                            </div>
                        @else
                            <div class="form-text">
                                El margen sale solo en cuanto haya costo y precio.
                            </div>
                        @endif

                    </div>
                </div>

                {{-- ───── DE QUIÉN ES ───── --}}
                <div class="card mb-3 seccion seccion-notas">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <i class="bi bi-building"></i>
                            <span>De quién es</span>
                        </h6>
                    </div>

                    <div class="card-body">

                        <div class="mb-2">
                            <label class="form-label small">Empresa dueña</label>
                            <select class="form-select form-select-sm" wire:model="owner_company_id">
                                @foreach ($empresas as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">Quién puso el dinero.</div>
                        </div>

                        <div>
                            <label class="form-label small">Empresa que la vende</label>
                            <select class="form-select form-select-sm" wire:model="billing_company_id">
                                @foreach ($empresas as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                            <div class="form-text">
                                Es la que decide en qué inventario aparece. Casi siempre es la misma
                                que la dueña.
                            </div>
                        </div>

                    </div>
                </div>

            </div>

        </div>

        {{-- ───── EL PIE ───── --}}
        <div class="ps-pie">

            <div>
                <a href="{{ route('operaciones.contenedores.index') }}"
                   class="btn btn-outline-secondary">
                    Cancelar
                </a>
            </div>

            <div class="ps-pie-medio">
                @if ($errors->any())
                    <span class="text-danger fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        {{ $errors->count() === 1 ? 'Falta 1 dato' : 'Faltan '.$errors->count().' datos' }}
                    </span>
                @endif

                <div wire:loading wire:target="guardar">
                    <span class="spinner-border spinner-border-sm me-1"></span> Guardando...
                </div>
            </div>

            <div class="d-flex gap-2">

                {{--
                    "GUARDAR Y REGISTRAR OTRO"

                    Solo al dar de alta. Al editar no tiene sentido: no
                    estás cargando una tanda, estás corrigiendo una ficha.
                --}}
                @unless ($containerId)
                    <button type="button" class="btn btn-outline-primary"
                            wire:click="guardar(true)"
                            title="Conserva la clasificación, los costos y los precios">
                        <i class="bi bi-plus-square me-1"></i> Guardar y registrar otro
                    </button>
                @endunless

                <button type="submit" class="btn btn-success" wire:loading.attr="disabled">
                    <i class="bi bi-check-lg me-1"></i>
                    {{ $containerId ? 'Guardar cambios' : 'Registrar unidad' }}
                </button>

            </div>

        </div>

    </form>

</div>
