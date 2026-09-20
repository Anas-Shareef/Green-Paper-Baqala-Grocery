<!doctype html>
<html lang="en" class="h-full bg-white text-slate-900">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Baqqala Admin Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script>
      // Automatic session propagation to React admin token if authenticated
      @if(Auth::check())
      if (!localStorage.getItem('admin_token')) {
        localStorage.setItem('admin_token', 'baqqala_session_{{ Auth::id() }}_{{ md5(Auth::user()->email . config("app.key")) }}');
      }
      @endif
    </script>
    <script type="module" crossorigin src="/assets/index-CxHAUFkb.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/index-mvJfxxa_.css">
  </head>
  <body class="h-full bg-white text-slate-900 font-sans antialiased overflow-x-hidden">
    <div id="root" class="min-h-full flex flex-col"></div>
  </body>
</html>
