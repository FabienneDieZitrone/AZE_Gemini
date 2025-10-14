<?php
// MP Arbeitszeiterfassung - Main Entry Point
header('Content-Type: text/html; charset=UTF-8');

// Serve the built SPA
$distIndex = __DIR__ . '/dist/index.html';

if (file_exists($distIndex)) {
    readfile($distIndex);
    exit;
}

// Fallback
?>
<!DOCTYPE html>
<html lang="de">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>MP Arbeitszeiterfassung</title>
  </head>
  <body>
    <div id="root"></div>
    <p style="color:red;">Error: Build not found</p>
  </body>
</html>
