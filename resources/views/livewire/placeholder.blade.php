{{--
    Pantalla provisional.

    Usa clases de Bootstrap y AdminLTE, no de Tailwind, porque el
    layout interno del sistema está armado con AdminLTE. El login sí
    usa Tailwind: son dos pantallas con dueños distintos y está bien
    que sea así.
--}}
<div>

    <div class="row justify-content-center">
        <div class="col-lg-8">

            <div class="card">

                <div class="card-header">
                    <h3 class="card-title mb-0">{{ $titulo }}</h3>
                </div>

                <div class="card-body text-center py-5">

                    <i class="bi bi-cone-striped text-warning" style="font-size: 3rem;"></i>

                    <h5 class="mt-3 mb-2">Módulo en construcción</h5>

                    <p class="text-secondary mb-0">
                        Esta pantalla todavía no está desarrollada.<br>
                        El menú funciona para que puedas recorrer el sistema mientras tanto.
                    </p>

                </div>

            </div>

        </div>
    </div>

</div>