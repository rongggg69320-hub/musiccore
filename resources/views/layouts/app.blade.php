<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

       <title>{{ $title ? $title . ' | Music ' : 'Msic' }}</title>

 <link rel="icon" type="image/png" href="{{ app(\App\Services\Setting::class)->logoUrl() }}"> 
    <script src="https://cdn.tailwindcss.com"></script>

    <script src='js/auth/login_animate.js'></script>
    <script src='js/auth/login_loading_animate.js'></script>

    @stack('head')

        @livewireStyles
    </head>
<body class="h-screen bg-gray-100 text-gray-900 flex justify-center items-center">
        {{ $slot }}
@livewireScripts(['defer' => true, 'polling' => true])  
            <script src='js/app.js'></script>
        <script>
            function hideLoader(){
                const el = document.getElementById('loader-overlay');
                if(!el) return;
                el.classList.add('hidden');
                setTimeout(()=> el.remove(), 300);
            }

            window.addEventListener('load', hideLoader);

            document.addEventListener('livewire:load', function () {
                hideLoader();
                if (window.Livewire && Livewire.hook) {
                    Livewire.hook('message.processed', () => hideLoader());
                }
            });
        </script>
        @stack('scripts')
    </body>
</html>
