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
    @php
      $jsAsset = null;
      $cssAsset = null;

      $manifestPath = public_path('admin-manifest.json');
      if (file_exists($manifestPath)) {
          $manifest = json_decode(@file_get_contents($manifestPath), true);
          if (!empty($manifest['index.html']['file'])) {
              $jsAsset = '/' . ltrim($manifest['index.html']['file'], '/');
          }
          if (!empty($manifest['index.html']['css'][0])) {
              $cssAsset = '/' . ltrim($manifest['index.html']['css'][0], '/');
          }
      }

      if (!$jsAsset) {
          $jsFiles = glob(public_path('assets/index-*.js'));
          if (!empty($jsFiles)) {
              usort($jsFiles, fn($a, $b) => filemtime($b) - filemtime($a));
              $jsAsset = '/assets/' . basename($jsFiles[0]);
          }
      }

      if (!$cssAsset) {
          $cssFiles = glob(public_path('assets/index-*.css'));
          if (!empty($cssFiles)) {
              usort($cssFiles, fn($a, $b) => filemtime($b) - filemtime($a));
              $cssAsset = '/assets/' . basename($cssFiles[0]);
          }
      }
    @endphp
    @if($jsAsset)
    <script type="module" crossorigin src="{{ $jsAsset }}"></script>
    @endif
    @if($cssAsset)
    <link rel="stylesheet" crossorigin href="{{ $cssAsset }}">
    @endif
  </head>
  <body class="h-full bg-white text-slate-900 font-sans antialiased overflow-x-hidden">
    <div id="root" class="min-h-full flex flex-col"></div>
  </body>
</html>
