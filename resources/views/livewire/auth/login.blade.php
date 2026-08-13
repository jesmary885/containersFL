<div class="min-h-screen flex bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900">

    @session('status')
        <div class="mb-4 font-medium text-sm text-green-600">
            {{ $value }}
        </div>
    @endsession

    <!-- LADO IZQUIERDO - INSTITUCIONAL -->
    <div
    class="hidden lg:flex w-1/2 relative items-center justify-center px-28 overflow-hidden bg-slate-950"
>

    <!-- Imagen de fondo -->
    <img

       src="{{ Storage::url('logo/IMAGEN_LOGIN.png') }}"
        alt=""
        class="absolute inset-0 w-full h-full object-cover"
    >

    <!-- Overlay oscuro -->
    <div class="absolute inset-0 bg-slate-950/75"></div>

    <!-- Overlay azul sutil -->
    <div class="absolute inset-0 bg-blue-950/30"></div>

    <!-- Efecto decorativo -->
    <div class="absolute w-[600px] h-[600px]
                bg-blue-700/10
                rounded-full
                blur-3xl
                -top-40
                -left-40">
    </div>
        <div class="relative z-10 max-w-lg text-white space-y-10">
            <div class="space-y-6">
                <span class="text-blue-500 uppercase text-md tracking-[0.3em] font-semibold">
                    Plataforma Institucional
                </span>
                <h1 class="text-5xl font-semibold leading-tight tracking-tight">
                    Sistema de Gestión
                    <br>
                    Administrativa
                </h1>
                <div class="w-20 h-[2px] bg-blue-600"></div>
            </div>

            <p class="text-slate-400 text-lg leading-relaxed">
                Entorno seguro diseñado para la supervisión estratégica,
                control operativo y gestión integral de activos logísticos.
            </p>

            <div class="pt-8 border-t border-slate-800">
                <p class="text-sm text-slate-500">
                    Acceso exclusivo para personal autorizado.<br>
                    La protección y confidencialidad de la información
                    es parte esencial de nuestra infraestructura tecnológica.
                </p>
            </div>
        </div>
    </div>

    <!-- LADO DERECHO - LOGIN -->
    <!-- LADO DERECHO - LOGIN -->
<div class="flex w-full lg:w-1/2 items-center justify-center bg-slate-200 px-6 lg:px-10">

    <div class="w-full max-w-md bg-white rounded-2xl shadow-[0_20px_60px_-15px_rgba(0,0,0,0.18)] px-10 py-10">

        <!-- IDENTIDAD DE LA EMPRESA -->
        <div class="flex flex-col items-center mb-4">

            <div class="flex items-center justify-center w-32 h-24 mb-3">
                <img
                    src="{{ Storage::url('logo/logo-Photoroom.png') }}"
                    alt="Containers FL"
                    class="max-w-full  object-contain"
                >
            </div>

            <div class="w-12 h-[2px] bg-blue-700 rounded-full"></div>

        </div>

        <!-- TITULO -->
        <div class="mb-9 space-y-2 text-center">

            <h2 class="text-2xl font-semibold text-slate-900">
                Ingreso al Sistema
            </h2>

            <p class="text-sm text-slate-500 leading-relaxed">
                Introduzca sus credenciales para acceder al entorno administrativo.
            </p>

        </div>

        <form wire:submit.prevent="login" class="space-y-7">

            <!-- Usuario -->
            <div class="space-y-2">

                <label class="block text-xs uppercase tracking-wider text-slate-500 font-semibold">
                    Usuario
                </label>

                <input
                    type="email"
                    wire:model="email"
                    class="w-full px-4 py-3 border border-slate-200 rounded-lg bg-slate-200 focus:bg-slate-100 focus:border-blue-700 focus:ring-2 focus:ring-blue-700/30 focus:outline-none transition-all duration-200"
                    placeholder="tu@email.com"
                >

                @error('email')
                    <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror

            </div>

            <!-- Contraseña -->
            <div class="space-y-2" x-data="{ show: false }">

                <label class="block text-xs uppercase tracking-wider text-slate-500 font-semibold">
                    Contraseña
                </label>

                <div class="relative">

                    <input
                        :type="show ? 'text' : 'password'"
                        wire:model="password"
                        class="w-full px-4 py-3 pr-12 border border-slate-200 rounded-lg bg-slate-200 focus:bg-slate-100 focus:border-blue-700 focus:ring-2 focus:ring-blue-700/30 focus:outline-none transition-all duration-200"
                        placeholder="********"
                    >

                    <button
                        type="button"
                        @click="show = !show"
                        class="absolute inset-y-0 right-3 flex items-center text-slate-500 hover:text-blue-700 transition"
                    >
                        <svg
                            x-show="!show"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"
                            />

                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.477 0 8.268 2.943 9.542 7-1.274 4.057-5.065 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"
                            />
                        </svg>

                        <svg
                            x-show="show"
                            xmlns="http://www.w3.org/2000/svg"
                            class="h-5 w-5"
                            fill="none"
                            viewBox="0 0 24 24"
                            stroke="currentColor"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                stroke-width="1.8"
                                d="M13.875 18.825A10.05 10.05 0 0112 19c-4.477 0-8.268-2.943-9.542-7a9.956 9.956 0 012.293-3.95M6.223 6.223A9.953 9.953 0 0112 5c4.477 0 8.268 2.943 9.542 7a9.978 9.978 0 01-4.043 5.056M6.223 6.223L3 3m3.223 3.223l11.554 11.554"
                            />
                        </svg>
                    </button>

                </div>

                @error('password')
                    <span class="text-red-500 text-xs">{{ $message }}</span>
                @enderror

            </div>

            <!-- Recordarme -->
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input
                    type="checkbox"
                    wire:model="remember"
                    class="rounded border-slate-300"
                >
                Recordarme
            </label>

            <!-- Botón -->
            <button
                type="submit"
                class="w-full bg-slate-900 text-white py-3 rounded-lg font-semibold tracking-wide hover:bg-blue-800 transition-all duration-300 shadow-lg hover:shadow-blue-900/30"
            >
                Acceder
            </button>

        </form>

    </div>

</div>
</div>