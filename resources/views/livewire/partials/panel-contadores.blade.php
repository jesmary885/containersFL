{{--
    ═══════════════════════════════════════════════════════════════════════
    LA TIRA DE CONTADORES
    ═══════════════════════════════════════════════════════════════════════

    Los dos estilos del panel la comparten. Un solo archivo: si mañana se
    agrega un contador, aparece en los dos sin tocar nada más.

    ── POR QUÉ NO SON TARJETAS GRANDES ──

    Porque son CANTIDADES, no dinero. Cinco contenedores y $13.400 no
    pesan lo mismo en una decisión, y darles el mismo tamaño hace que la
    pantalla no tenga jerarquía: todo grita igual y no se mira nada.

    Aquí ocupan una franja baja, se leen de un vistazo, y cada una lleva a
    su módulo.
--}}
<div class="card mb-4">
    <div class="card-body py-3">
        <div class="row text-center g-3">

            {{--
                DISPONIBLES

                No es el total del inventario: es lo que se puede vender
                hoy. En yarda Y sin venta ni renta encima.

                Es justo la distinción que el Excel no hace, y la razón de
                los 416 fantasma.
            --}}
            <div class="col-6 col-md-3">
                <a href="{{ route('operaciones.contenedores.index') }}"
                   class="text-decoration-none text-reset d-block">
                    <div class="fs-3 fw-semibold">{{ $indicadores['contenedores'] }}</div>
                    <div class="small text-secondary">Disponibles</div>
                    <div class="small text-secondary" style="font-size:.7rem">
                        en yarda y sin compromiso
                    </div>
                </a>
            </div>

            {{--
                RENTADOS

                Se cuenta por el ESTADO de la unidad y no por contratos,
                porque el módulo de Rentas no existe todavía. Un contenedor
                en estado "rentado" está con un cliente: eso sí es cierto
                hoy.
            --}}
            <div class="col-6 col-md-3">
                <a href="{{ route('operaciones.contenedores.index') }}"
                   class="text-decoration-none text-reset d-block">
                    <div class="fs-3 fw-semibold">{{ $indicadores['rentas_activas'] }}</div>
                    <div class="small text-secondary">Rentados</div>
                    <div class="small text-secondary" style="font-size:.7rem">
                        con un cliente
                    </div>
                </a>
            </div>

            {{--
                POR RETIRAR

                El número que originó el proyecto: unidades compradas que
                siguen en el depósito del proveedor. El Excel las cuenta
                como stock y físicamente no están.

                En ámbar cuando hay alguna, porque cada día que pasan ahí
                puede costar dinero.
            --}}
            <div class="col-6 col-md-3">
                <a href="{{ route('compras.compras.index') }}"
                   class="text-decoration-none text-reset d-block">
                    <div class="fs-3 fw-semibold {{ $atencion['por_retirar'] > 0 ? 'text-warning' : '' }}">
                        {{ $atencion['por_retirar'] }}
                    </div>
                    <div class="small text-secondary">Por retirar</div>
                    <div class="small text-secondary" style="font-size:.7rem">
                        siguen en el proveedor
                    </div>
                </a>
            </div>

            {{--
                CLIENTES

                Sin filtro de compañía: el cliente es compartido entre las
                dos empresas. Se registra una vez y las dos le venden.
            --}}
            <div class="col-6 col-md-3">
                <a href="{{ route('comercial.clientes.index') }}"
                   class="text-decoration-none text-reset d-block">
                    <div class="fs-3 fw-semibold">{{ $indicadores['clientes'] }}</div>
                    <div class="small text-secondary">Clientes</div>
                    <div class="small text-secondary" style="font-size:.7rem">
                        compartidos entre las dos empresas
                    </div>
                </a>
            </div>

        </div>

        {{--
            UNIDADES EN YARDA SIN PRECIO DE VENTA.

            Es el trabajo pendiente más silencioso del inventario: nadie lo
            ve hasta que hay que cotizar deprisa y alguien se inventa un
            número.

            Solo sale si hay alguna.
        --}}
        @if ($atencion['sin_precio'] > 0)
            <div class="border-top mt-3 pt-2 text-center small text-secondary">
                <i class="bi bi-tag me-1"></i>
                {{ $atencion['sin_precio'] }}
                {{ $atencion['sin_precio'] === 1
                    ? 'unidad en yarda no tiene precio de venta cargado'
                    : 'unidades en yarda no tienen precio de venta cargado' }}
                — no se pueden cotizar sin inventar el número.
            </div>
        @endif

    </div>
</div>
