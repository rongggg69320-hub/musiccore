<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login | Music</title>
    <link rel="icon" type="image/png" href="{{ app(\App\Services\Setting::class)->logoUrl() }}">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="min-h-screen bg-gray-100 text-gray-900 flex justify-center items-center">
    <div id="main-content" class="min-h-screen w-full m-0 sm:m-10 bg-white shadow lg:rounded-lg flex flex-col lg:flex-row">

        <!-- Left Side -->
        <div class="w-full lg:w-1/2 xl:w-5/12 p-6 sm:p-12 flex flex-col justify-center lg:rounded-l-lg">

            <!-- Logo -->
            <div class="flex flex-col items-center">
                <img src="{{ app(\App\Services\Setting::class)->logoUrl() }}" style="width:200px">
            </div>

            <!-- Title -->
            <div class="flex flex-col items-center mt-6">
                <h1 class="text-2xl xl:text-3xl font-extrabold text-center">Login</h1>

                <div class="w-full flex-1 mt-6">
                    <div class="mx-auto max-w-xs w-full">

                        <!-- Form starts -->
                        <form method="post" action="{{ route('admin.authenticate') }}">
                            @csrf

                            <!-- Username or email -->
                            <input type="text" name="username" value="{{ old('username') }}"
                                class="w-full px-8 py-4 rounded-lg bg-gray-100 border border-gray-200 text-sm"
                                placeholder="Username or email" autocomplete="username" required>
                            @error('username')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror

                            <!-- Password -->
                            <input type="password" name="password"
                                class="w-full px-8 py-4 rounded-lg bg-gray-100 border border-gray-200 mt-5 text-sm"
                                placeholder="Password" autocomplete="current-password" required>
                            @error('password')
                                <p class="text-red-500 text-xs mt-1">{{ $message }}</p>
                            @enderror

                            <!-- Login error -->
                            @if ($errors->any())
                                <p class="text-red-500 text-sm mt-3 text-center">{{ $errors->first() }}</p>
                            @endif

                            <!-- Login Button -->
                            <button type="submit"
                                class="mt-5 w-full py-4 bg-indigo-500 text-white rounded-lg flex justify-center items-center gap-2">
                                Login
                            </button>

                            <!-- Terms -->
                            <p class="mt-6 text-xs text-gray-600 text-center">
                                I agree to
                                <a href="#" class="border-b border-gray-500 border-dotted">Terms of Service</a>
                                and
                                <a href="#" class="border-b border-gray-500 border-dotted">Privacy Policy</a>
                            </p>
                        </form>
                        <!-- Form ends -->

                    </div>
                </div>
            </div>
        </div>

        <!-- Right Side Image -->
        <div class="flex-1 hidden lg:flex bg-indigo-100 justify-center items-center lg:rounded-r-lg">
            <div class="w-full h-full bg-cover bg-center"
                style="background-image:url('{{ app(\App\Services\Setting::class)->urlForPath('image', 'school.jpg') }}')"></div>
        </div>

    </div>
</body>
</html>
