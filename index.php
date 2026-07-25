<?php

// Ensure Symfony Request computes baseUrl as '/ERP' and pathInfo as the route path (e.g. '/login')
$_SERVER['SCRIPT_NAME'] = '/ERP/index.php';
$_SERVER['PHP_SELF'] = '/ERP/index.php';

require __DIR__.'/backend/public/index.php';
