<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Pre Check-In Link Expired</title>
  @if (file_exists(public_path('build/manifest.json')))
    @vite(['resources/css/app.css', 'resources/js/app.js'])
  @else
    <style>
      body { margin: 0; background: #f3f4f6; color: #111827; font-family: Arial, Helvetica, sans-serif; }
      main { max-width: 672px; margin: 0 auto; padding: 24px; }
      .card { margin-top: 40px; background: #fff; border-radius: 16px; box-shadow: 0 10px 25px rgba(15, 23, 42, 0.08); }
      .card-body { padding: 24px; }
      .card-title { margin: 0 0 12px; font-size: 28px; font-weight: 700; color: #b91c1c; }
      p { margin: 0; color: #374151; line-height: 1.5; }
    </style>
  @endif
</head>
<body class="bg-base-200 min-h-screen">
  <main class="max-w-2xl mx-auto p-6">
    <div class="card bg-base-100 shadow-lg mt-10">
      <div class="card-body">
        <h1 class="card-title text-error">Pre Check-In Link Expired</h1>
        <p>This secure pre check-in link has expired. Please contact the facility so we can send you a new link.</p>
      </div>
    </div>
  </main>
</body>
</html>
