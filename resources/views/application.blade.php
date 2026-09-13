<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <link rel="icon" type="image/png" href="{{ asset('logo.png') }}" />
  <meta name="robots" content="noindex, nofollow" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>E-Tresor</title>
  <link rel="stylesheet" type="text/css" href="{{ asset('loader.css') }}" />
  <style>
    @font-face {
      font-family: 'JetBrains Mono';
      font-style: normal;
      font-weight: 400;
      font-display: swap;
      src: url('{{ asset('JetBrainsMono-Regular.ttf') }}') format('truetype');
    }

    html, body {
      font-family: 'JetBrains Mono', ui-monospace, monospace;
    }

    .loading-logo {
      overflow: hidden;
      border-radius: 50%;
      block-size: 86px;
      inline-size: 86px;
    }

    .loading-logo img {
      block-size: 100%;
      inline-size: 100%;
      object-fit: cover;
      transform: scale(1.14);
    }
  </style>
  @vite(['resources/ts/main.ts'])
</head>

<body>
  <div id="app">
    <div id="loading-bg">
      <div class="loading-logo">
        <img src="{{ asset('logo.png') }}" alt="DGTCP" width="86" height="86" />
      </div>
      <div class="loading">
        <div class="effect-1 effects"></div>
        <div class="effect-2 effects"></div>
        <div class="effect-3 effects"></div>
      </div>
    </div>
  </div>

  <script>
    const loaderColor = localStorage.getItem('vuexy-initial-loader-bg') || '#F4F6F2'
    const primaryColor = localStorage.getItem('vuexy-initial-loader-color') || '#0B6B3A'

    if (loaderColor)
      document.documentElement.style.setProperty('--initial-loader-bg', loaderColor)

    if (primaryColor)
      document.documentElement.style.setProperty('--initial-loader-color', primaryColor)
  </script>
</body>
</html>
