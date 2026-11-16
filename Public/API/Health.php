<?php

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    "status" => "online",
], JSON_THROW_ON_ERROR);
die();
