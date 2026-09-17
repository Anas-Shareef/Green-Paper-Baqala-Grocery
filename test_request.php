<?php
require __DIR__ . '/vendor/autoload.php';

$_SERVER['SCRIPT_NAME'] = '/api/index.php';
$_SERVER['REQUEST_URI'] = '/api/home';
$_SERVER['PHP_SELF'] = '/api/index.php';

$req1 = \Illuminate\Http\Request::createFromGlobals();
echo "--- Test 1 (Original Vercel) ---\n";
echo "BaseUrl:  '" . $req1->getBaseUrl() . "'\n";
echo "PathInfo: '" . $req1->getPathInfo() . "'\n\n";

$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['REQUEST_URI'] = '/api/home';

$req2 = \Illuminate\Http\Request::createFromGlobals();
echo "--- Test 2 (SCRIPT_NAME = /index.php) ---\n";
echo "BaseUrl:  '" . $req2->getBaseUrl() . "'\n";
echo "PathInfo: '" . $req2->getPathInfo() . "'\n\n";
