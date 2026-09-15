{{--
    ═══════════════════════════════════════════════════════════════════════
    PANEL DE LA FICHA DEL CLIENTE
    ═══════════════════════════════════════════════════════════════════════

    Se incluye desde el formulario de factura y desde el de presupuesto.
    Una sola vista para los dos, para que se vea igual en ambos sitios.

    Es de SOLO LECTURA a propósito: se abre encima del documento que se
    está escribiendo, y no debe haber forma de perder ese trabajo. Para
    cambiar algo del cliente está su módulo.

    no-imprimir: esto es la pantalla, no el documento.
--}}
@if ($verFichaCliente && $this->fichaDelCliente)
    @php $c = $this->fichaDelCliente; @endphp

    <div class="bu-fondo bu-sobre-modal no-imprimir"
         wire:key="ficha-cliente"
         x-data
         x-on:keydown.escape.window="$wire.cerrarFichaCliente()">

        <div class="bu-panel">

            <div class="bu-cabecera">
                <span class="bu-icono"><i class="bi bi-person-vcard"></i></span>
                <h6 class="bu-titulo">
                    {{ $c->name }}
                    <span class="bu-sub">
                        {{ $c->is_company ? 'Empresa' : 'Persona física' }}
                        @if ($c->legal_name && $c->legal_name !== $c->name)
                            · {{ $c->legal_name }}
                        @endif
                    </span>
                </h6>
                <button type="button" class="bu-cerrar"
                        wire:click="cerrarFichaCliente" title="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <div class="bu-lista p-3">

                {{--
                    LAS NOTAS VAN PRIMERO.

                    Es lo que más se busca y lo único que no está en
                    ningún otro sitio. Todo lo demás se puede deducir;
                    una nota, no.
                --}}
                @if (filled($c->notes))
                    <div class="alert alert-warning py-2 small">
                        <div class="fw-semibold mb-1">
                            <i class="bi bi-sticky me-1"></i> Notas sobre este cliente
                        </div>
                        <div style="white-space: pre-line;">{{ $c->notes }}</div>
                    </div>
                @endif

                {{-- ───── LO QUE DECIDE CÓMO SE COBRA ───── --}}
                <div class="row g-2 mb-3">

                    <div class="col-12 col-md-6">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-secondary">Puede pagar con tarjeta</div>
                            @if ($c->can_pay_credit_card)
                                <span class="badge text-bg-success">Sí</span>

                                {{-- RB-012: sin Sunbiz no se pasa la tarjeta de una empresa. --}}
                                @if ($c->is_company)
                                    @if ($c->sunbiz_verified)
                                        <span class="badge text-bg-success">
                                            Sunbiz verificada
                                            @if ($c->sunbiz_verified_at)
                                                · {{ $c->sunbiz_verified_at->format('d/m/Y') }}
                                            @endif
                                        </span>
                                    @else
                                        <span class="badge text-bg-danger">Sin verificar en Sunbiz</span>
                                    @endif
                                @else
                                    <div class="small text-secondary mt-1">
                                        Persona física: solo con el cliente presente en la yarda.
                                    </div>
                                @endif
                            @else
                                <span class="badge text-bg-secondary">No</span>
                            @endif
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <div class="border rounded p-2 h-100">
                            <div class="small text-secondary">Exención de impuesto</div>

                            @php $cert = $c->certificates->first(); @endphp

                            @if ($cert)
                                <span class="badge text-bg-success">Certificado vigente</span>
                                <div class="small mt-1">
                                    N.º {{ $cert->certificate_number }}
                                    · vence {{ $cert->valid_until?->format('d/m/Y') }}
                                </div>
                            @elseif ($c->tax_exempt)
                                {{--
                                    Marcado como exento pero SIN certificado vigente.

                                    Es el caso peligroso: si el estado audita,
                                    una casilla marcada no prueba nada y el 7%
                                    lo paga la empresa (RB-014).
                                --}}
                                <span class="badge text-bg-danger">
                                    Marcado exento, sin certificado vigente
                                </span>
                                <div class="small mt-1">
                                    Pídale el Annual Resale Certificate antes de facturarle sin tax.
                                </div>
                            @else
                                <span class="badge text-bg-secondary">Paga impuesto</span>
                            @endif
                        </div>
                    </div>

                </div>

                {{--
                    LO QUE YA DEBE.

                    Un cliente que arrastra facturas vencidas es una
                    conversación distinta antes de cotizarle otra cosa.
                --}}
                @if ($this->deudaDelCliente > 0.01)
                    <div class="alert {{ $this->facturasVencidasDelCliente > 0 ? 'alert-danger' : 'alert-info' }} py-2 small">
                        <i class="bi bi-cash-stack me-1"></i>
                        Debe <strong>${{ number_format($this->deudaDelCliente, 2) }}</strong>
                        en esta empresa.
                        @if ($this->facturasVencidasDelCliente > 0)
                            <strong>
                                {{ $this->facturasVencidasDelCliente }}
                                {{ $this->facturasVencidasDelCliente === 1 ? 'factura vencida' : 'facturas vencidas' }}.
                            </strong>
                        @endif
                    </div>
                @endif

                {{-- ───── CÓMO CONTACTARLO ───── --}}
                <div class="fw-semibold small mb-2">
                    <i class="bi bi-telephone me-1"></i> Contacto
                </div>

                <dl class="row small mb-3">
                    @if ($c->phone)
                        <dt class="col-4 text-secondary">Teléfono</dt>
                        <dd class="col-8"><a href="tel:{{ $c->phone }}">{{ $c->phone }}</a></dd>
                    @endif

                    @if ($c->email)
                        <dt class="col-4 text-secondary">Correo</dt>
                        <dd class="col-8"><a href="mailto:{{ $c->email }}">{{ $c->email }}</a></dd>
                    @endif
                </dl>

                @if ($c->contacts->isNotEmpty())
                    <div class="fw-semibold small mb-2">
                        Otros contactos
                        <span class="text-secondary">
                            — las notificaciones de cobranza van a todos
                        </span>
                    </div>

                    @foreach ($c->contacts as $contacto)
                        <div class="border-bottom py-2 small" wire:key="cont-{{ $contacto->id }}">
                            <div class="fw-semibold">
                                {{ $contacto->name }}
                                @if ($contacto->role)
                                    <span class="text-secondary">· {{ $contacto->role }}</span>
                                @endif
                            </div>
                            <div class="text-secondary">
                                @if ($contacto->phone)
                                    <a href="tel:{{ $contacto->phone }}">{{ $contacto->phone }}</a>
                                @endif
                                @if ($contacto->email)
                                    · <a href="mailto:{{ $contacto->email }}">{{ $contacto->email }}</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endif

                {{-- ───── DIRECCIONES ───── --}}
                @if ($c->addresses->isNotEmpty())
                    <div class="fw-semibold small mt-3 mb-2">
                        <i class="bi bi-geo-alt me-1"></i> Direcciones
                    </div>

                    @foreach ($c->addresses as $dir)
                        <div class="border-bottom py-2 small" wire:key="dir-{{ $dir->id }}">
                            <div>{{ $dir->line1 }}</div>
                            @if ($dir->line2)<div>{{ $dir->line2 }}</div>@endif
                            <div class="text-secondary">
                                {{ collect([$dir->city, $dir->state])->filter()->implode(', ') }}
                                {{ $dir->zip }}
                            </div>
                        </div>
                    @endforeach
                @endif

            </div>

            <div class="bu-pie">
                <span class="bu-conteo">Solo lectura. Para editarlo, vaya a Clientes.</span>
                <button type="button" class="bu-btn-cerrar" wire:click="cerrarFichaCliente">
                    Cerrar
                </button>
            </div>

        </div>
    </div>
@endif
