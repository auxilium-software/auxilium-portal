<?php

use Auxilium\FormHandling\FormHandler;

require_once __DIR__ . '/../vendor/autoload.php';

$formInstanceID = explode(separator: '/', string: $_SERVER['REQUEST_URI'])[2];

$formHandler = new FormHandler($formInstanceID);

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $formHandler->handlePostRequest();
}

$formHandler->render();
