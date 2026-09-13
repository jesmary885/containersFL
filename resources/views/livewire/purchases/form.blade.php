{{--
    ═══════════════════════════════════════════════════════════════════════
    REGISTRAR UNA COMPRA
    ═══════════════════════════════════════════════════════════════════════

    Una sola pantalla. Cabe: cabecera, dónde están, y qué se compró.

    ── LO QUE MÁS CUESTA ENTENDER DE ESTA PANTALLA ──

    Aquí NO se registran contenedores. Se registra lo que se PAGÓ: "siete
    unidades de 20ft Cargo Worthy a $1,800". Cuáles son, todavía no se
    sabe — están en el patio del proveedor y nadie ha leído sus números.

    Las unidades se dan de alta una por una cuando llegan, desde la ficha
    de la compra.

    Hacerlo al revés es lo que hace el Excel, y es la razón por la que
    dice 416 contenedores que no están.
--}}
<div>

    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <h4 class="mb-0 fw-semibold">
                {{ $purchaseId ? 'Editar compra' : 'Nueva compra' }}
                @if ($numero)
                    <span class="text-secondary fw-normal font-monospace fs-6">{{ $numero }}</span>
                @endif
            </h4>
            <small class="text-secondary">
                Se registra lo que se pagó. Las unidades se dan de alta cuando llegan.
            </small>
        </div>

        <div class="d-flex align-items-center gap-2">
            <span class="leyenda-obligatorio"><strong>*</strong> Campo obligatorio</span>
            <a href="{{ $purchaseId
                        ? route('compras.compras.show', $purchaseId)
                        : route('compras.compras.index') }}"
               class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Volver
            </a>
        </div>
    </div>

    <x-ui.errores id="resumen-errores" titulo="Falta algo para poder guardar." />

    <form wire:submit.prevent="guardar">
        <div class="row g-3">

            <div class="col-12 col-xl-8">

                {{-- ───── 1 · A QUIÉN Y CUÁNDO ───── --}}
                <div class="card mb-3 seccion seccion-cliente">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">1</span>
                            <i class="bi bi-shop"></i>
                            <span>A quién se le compró</span>
                        </h6>
                    </div>

                    <div class="card-body">
                        <div class="row g-3">

                            <div class="col-12 col-md-5">
                                <label class="form-label">Proveedor <span class="text-danger">*</span></label>
                                <select class="form-select @error('supplier_id') is-invalid @enderror"
                                        wire:model="supplier_id">
                                    <option value="">— Elegir —</option>
                                    @foreach ($proveedores as $p)
                                        <option value="{{ $p->id }}">{{ $p->name }}</option>
                                    @endforeach
                                </select>
                                @error('supplier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">Tipo <span class="text-danger">*</span></label>
                                <select class="form-select" wire:model.live="type">
                                    @foreach ($tipos as $valor => $etiqueta)
                                        <option value="{{ $valor }}">{{ $etiqueta }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    @if ($type === 'release')
                                        Se retira por partes, con plazo.
                                    @else
                                        Se paga y se lleva de una vez.
                                    @endif
                                </div>
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label">Fecha <span class="text-danger">*</span></label>
                                <input type="date" class="form-control @error('purchase_date') is-invalid @enderror"
                                       wire:model.live="purchase_date">
                                @error('purchase_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-12 col-md-2">
                                <label class="form-label">Referencia</label>
                                <input type="text" class="form-control"
                                       placeholder="N° del release"
                                       wire:model.blur="reference">
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ───── 2 · DÓNDE ESTÁN ───── --}}
                <div class="card mb-3 seccion seccion-direccion">
                    <div class="card-header">
                        <h6 class="seccion-titulo">
                            <span class="paso-num">2</span>
                            <i class="bi bi-building"></i>
                            <span>Dónde están y hasta cuándo</span>
                        </h6>
                    </div>

                    <div class="card-body">

                        <div class="row g-3">

                            <div class="col-12 col-md-5">
                                <label class="form-label">
                                    Depósito
                                    @if ($type === 'release')<span class="text-danger">*</span>@endif
                                </label>
                                <select class="form-select @error('depot_id') is-invalid @enderror"
                                        wire:model.live="depot_id">
                                    <option value="">
                                        {{ $type === 'release' ? '— Elegir —' : 'Entrega directa' }}
                                    </option>
                                    @foreach ($depositos as $d)
                                        <option value="{{ $d->id }}">
                                            {{ $d->name }}{{ $d->city ? ' · '.$d->city : '' }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('depot_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">
                                    Al elegirlo se traen su costo de pickup y sus días libres.
                                </div>
                            </div>

                            <div class="col-6 col-md-3">
                                <label class="form-label">
                                    Plazo de retiro
                                    @if ($type === 'release')<span class="text-danger">*</span>@endif
                                </label>
                                <input type="date"
                                       class="form-control @error('pickup_deadline_at') is-invalid @enderror"
                                       wire:model="pickup_deadline_at"
                                       @disabled($type !== 'release')>
                                @error('pickup_deadline_at') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label">Pick up por unidad</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control"
                                           wire:model.live.debounce.500ms="pickup_fee">
                                </div>
                                <div class="form-text">Lo que cuesta traer cada una.</div>
                            </div>

                            <div class="col-6 col-md-2">
                                <label class="form-label">Por día extra</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" class="form-control"
                                           wire:model.blur="daily_late_fee">
                                </div>
                                <div class="form-text">Si se pasa el plazo.</div>
                            </div>

                        </div>
                    </div>
                </div>

                {{-- ───── 3 · QUÉ SE COMPRÓ ───── --}}
                <div class="card mb-3 seccion seccion-lineas">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="seccion-titulo mb-0">
                            <span class="paso-num">3</span>
                            <i class="bi bi-box-seam"></i>
                            <span>Qué se compró</span>
                        </h6>

                        <button type="button" class="btn btn-sm btn-outline-primary"
                                wire:click="agregarLinea">
                            <i class="bi bi-plus-lg me-1"></i> Otro renglón
                        </button>
                    </div>

                    <div class="card-body">

                        <div class="alert alert-light border py-2 small">
                            <i class="bi bi-info-circle me-1"></i>
                            Un renglón es <strong>un lote igual</strong>: "7 de 20ft Cargo Worthy a
                            $1,800". Si el lote trae medidas o calidades distintas, van en renglones
                            separados.
                        </div>

                        @error('lineas')
                            <div class="alert alert-danger py-2 small">{{ $message }}</div>
                        @enderror

                        @foreach ($lineas as $i => $linea)

                            @php $yaRecibidas = (int) ($linea['received_quantity'] ?? 0); @endphp

                            <div class="border rounded p-3 mb-3" wire:key="lin-{{ $linea['id'] ?? 'n'.$i }}">
                                <div class="row g-2">

                                    <div class="col-6 col-md-2">
                                        <label class="form-label small">Medida <span class="text-danger">*</span></label>
                                        <select class="form-select form-select-sm @error('lineas.'.$i.'.container_size_id') is-invalid @enderror"
                                                wire:model="lineas.{{ $i }}.container_size_id">
                                            <option value="">—</option>
                                            @foreach ($medidas as $m)
                                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                                            @endforeach
                                        </select>
                                        @error('lineas.'.$i.'.container_size_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-6 col-md-2">
                                        <label class="form-label small">Tipo</label>
                                        <select class="form-select form-select-sm"
                                                wire:model="lineas.{{ $i }}.container_type_id">
                                            <option value="">—</option>
                                            @foreach ($tiposCont as $t)
                                                <option value="{{ $t->id }}">{{ $t->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-6 col-md-2">
                                        <label class="form-label small">Condición</label>
                                        <select class="form-select form-select-sm"
                                                wire:model="lineas.{{ $i }}.container_condition_id">
                                            <option value="">—</option>
                                            @foreach ($condiciones as $c)
                                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-6 col-md-2">
                                        <label class="form-label small">Calidad</label>
                                        <select class="form-select form-select-sm"
                                                wire:model="lineas.{{ $i }}.container_grade_id">
                                            <option value="">—</option>
                                            @foreach ($calidades as $g)
                                                <option value="{{ $g->id }}">{{ $g->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>

                                    <div class="col-4 col-md-1">
                                        <label class="form-label small">Cant. <span class="text-danger">*</span></label>
                                        <input type="number" min="1"
                                               class="form-control form-control-sm @error('lineas.'.$i.'.quantity') is-invalid @enderror"
                                               wire:model.live.debounce.500ms="lineas.{{ $i }}.quantity">
                                        @error('lineas.'.$i.'.quantity')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-8 col-md-2">
                                        <label class="form-label small">Costo c/u <span class="text-danger">*</span></label>
                                        <div class="input-group input-group-sm">
                                            <span class="input-group-text">$</span>
                                            <input type="number" step="0.01"
                                                   class="form-control @error('lineas.'.$i.'.unit_cost') is-invalid @enderror"
                                                   wire:model.live.debounce.500ms="lineas.{{ $i }}.unit_cost">
                                        </div>
                                        @error('lineas.'.$i.'.unit_cost')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    <div class="col-12 col-md-1 text-end">
                                        <label class="form-label small d-block">&nbsp;</label>
                                        @if (count($lineas) > 1 && $yaRecibidas === 0)
                                            <button type="button" class="btn btn-sm btn-outline-danger"
                                                    wire:click="quitarLinea({{ $i }})">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        @endif
                                    </div>

                                    <div class="col-12 d-flex flex-wrap justify-content-between align-items-center gap-2">
                                        <span class="small text-secondary">
                                            Importe:
                                            <strong>
                                                ${{ number_format((float) ($linea['quantity'] ?? 0) * (float) ($linea['unit_cost'] ?? 0), 2) }}
                                            </strong>
                                        </span>

                                        {{--
                                            LO YA RECIBIDO NO SE EDITA AQUÍ.

                                            Sube solo cuando se da de alta un
                                            contenedor desde la ficha de la compra.
                                            Dejarlo escribir a mano permitiría decir
                                            "llegaron 7" sin que exista ninguno, que
                                            es justo el problema que este módulo
                                            resuelve.
                                        --}}
                                        @if ($yaRecibidas > 0)
                                            <span class="badge bg-success-subtle text-success">
                                                <i class="bi bi-check-circle"></i>
                                                {{ $yaRecibidas }} ya recibidas
                                            </span>
                                        @endif
                                    </div>

                                </div>
                            </div>

                        @endforeach

                    </div>
                </div>

            </div>

            {{-- ───── LAS CUENTAS ───── --}}
            <div class="col-12 col-xl-4">

                <div class="card mb-3 seccion seccion-datos">
                    <div class="card-header">
                        <h6 class="seccion-titulo mb-0">
                            <span class="paso-num">4</span>
                            <i class="bi bi-calculator"></i>
                            <span>Las cuentas</span>
                        </h6>
                    </div>

                    <div class="card-body">

                        {{--
                            LAS TRES COLUMNAS SON LAS DEL EXCEL.

                            La hoja COMPRAS tiene PRECIO, PICK UP y TOTAL, y
                            el total es la suma de los dos. Se replica igual
                            para que el número cuadre con el que la empresa
                            lleva calculando desde siempre.

                            Por eso tampoco hay campo de impuesto: esa hoja no
                            tiene columna de impuesto.
                        --}}
                        <table class="table table-sm mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-secondary">Unidades</td>
                                    <td class="text-end fw-semibold">{{ $this->unidades }}</td>
                                </tr>
                                <tr>
                                    <td class="text-secondary">Precio</td>
                                    <td class="text-end monto">${{ number_format($this->subtotal, 2) }}</td>
                                </tr>
                                <tr>
                                    <td class="text-secondary">
                                        Pick up
                                        @if ((float) ($pickup_fee ?: 0) > 0)
                                            <div class="small">
                                                ${{ number_format((float) $pickup_fee, 2) }}
                                                × {{ $this->unidades }}
                                            </div>
                                        @endif
                                    </td>
                                    <td class="text-end monto">
                                        ${{ number_format($this->pickupTotal, 2) }}
                                    </td>
                                </tr>
                                <tr class="fw-bold border-top fs-5">
                                    <td>TOTAL</td>
                                    <td class="text-end monto">${{ number_format($this->total, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>


                    </div>
                </div>

                <div class="card mb-3 seccion seccion-notas">
                    <div class="card-header">
                        <h6 class="seccion-titulo mb-0">
                            <i class="bi bi-chat-left-text"></i>
                            <span>Notas</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <textarea class="form-control" rows="4"
                                  placeholder="Condiciones pactadas, quién autorizó, lo que haga falta recordar."
                                  wire:model.blur="notes"></textarea>
                    </div>
                </div>

            </div>

        </div>

        <div class="ps-pie">
            <div>
                <a href="{{ $purchaseId
                            ? route('compras.compras.show', $purchaseId)
                            : route('compras.compras.index') }}"
                   class="btn btn-outline-secondary">Cancelar</a>
            </div>

            <div class="ps-pie-medio">
                @if ($errors->any())
                    <span class="text-danger fw-semibold">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        {{ $errors->count() === 1 ? 'Falta 1 dato' : 'Faltan '.$errors->count().' datos' }}
                    </span>
                @endif
                <div wire:loading wire:target="guardar">
                    <span class="spinner-border spinner-border-sm me-1"></span> Guardando…
                </div>
            </div>

            <div>
                <button type="submit" class="btn btn-success" wire:loading.attr="disabled">
                    <i class="bi bi-check-lg me-1"></i>
                    {{ $purchaseId ? 'Guardar cambios' : 'Registrar la compra' }}
                </button>
            </div>
        </div>

    </form>

</div>
