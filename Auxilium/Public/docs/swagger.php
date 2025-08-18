<?php
require_once __DIR__ . "/../../vendor/autoload.php";
$openapi = \OpenApi\Generator::scan([
    __DIR__ . "/../../Auxilium/Auxilium/API",
]);
header('Content-Type: application/x-yaml');
echo $openapi->toYaml();
