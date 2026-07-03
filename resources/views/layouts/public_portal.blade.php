<!doctype html>
<html lang="en" class="group/html">
  <head>
    <title>@yield('title') - Sunshine</title>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="{{ asset('images/favicon-dark.png') }}" media="(prefers-color-scheme: dark)" />
    <link rel="shortcut icon" href="{{ asset('images/favicon-light.png') }}" media="(prefers-color-scheme: light)" />
    @yield('page-css')
    <script>
      try {
        const localStorageItem = localStorage.getItem("__NEXUS_CONFIG_v2.0__");
        if (localStorageItem) {
          const theme = JSON.parse(localStorageItem).theme;
          if (theme !== "system") {
            document.documentElement.setAttribute("data-theme", theme);
          }
        }
      } catch (err) {
        console.log(err);
      }
    </script>

    <link href="{{ asset('src/assets/app.css') }}" rel="stylesheet" />
  </head>
  <body class="bg-base-200 min-h-screen">
    <main class="w-full p-4 md:p-6">
      <div class="flex items-center justify-center py-3 md:py-5">
        <img alt="Sunshine Logo" src="{{ asset('images/logo.png') }}" class="h-10 md:h-12 w-auto" />
      </div>
      @yield('content')
    </main>

    <script src="{{ asset('src/js/components/password-field.js') }}"></script>
    <script src="{{ asset('src/js/jquery.min.js') }}"></script>
    <script src="{{ asset('src/libs/select2/select2.min.js') }}"></script>
    <script src="{{ asset('src/js/app.js') }}"></script>

    @yield('page-js')
  </body>
</html>
