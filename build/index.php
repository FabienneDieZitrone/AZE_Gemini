<?php
// MP Arbeitszeiterfassung - Main Entry Point

// SECURITY HEADERS - Protection against common attacks
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=(), usb=()');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\'; style-src \'self\' \'unsafe-inline\'; img-src \'self\' data: https:; font-src \'self\' data:; connect-src \'self\' https://login.microsoftonline.com https://graph.microsoft.com; frame-ancestors \'none\'; base-uri \'self\'; form-action \'self\'');

// CRITICAL: Disable all caching for this file!
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

// Clear PHP file stat cache to ensure fresh file reads
clearstatcache(true);

// Direct output with cache busting
$cacheBuster = time(); // Unix timestamp forces fresh load
?>
<!-- Vite template for build -->
<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MP Arbeitszeiterfassung</title>
    <link rel="icon" type="image/svg+xml" href="/app-icon.svg?v=<?php echo $cacheBuster; ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="/app-icon.png?v=<?php echo $cacheBuster; ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="/app-icon.png?v=<?php echo $cacheBuster; ?>">
    <script type="module" crossorigin src="/assets/index-DWVFBKwx.js?v=<?php echo $cacheBuster; ?>"></script>
    <link rel="stylesheet" crossorigin href="/assets/index-DkDIkFCC.css?v=<?php echo $cacheBuster; ?>">
  </head>
  <body>
    <div id="root"></div>
  </body>
</html>
