<?php
// Emergency entry point - bypasses all caching
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$v = time(); // Cache buster
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>MP Arbeitszeiterfassung</title>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🕒</text></svg>">
<script type="module" crossorigin src="/assets/index-C02UeB1c.js?v=<?php echo $v; ?>"></script>
<link rel="stylesheet" crossorigin href="/assets/index-mmLeTg_1.css?v=<?php echo $v; ?>">
</head>
<body>
<div id="root"></div>
</body>
</html>
