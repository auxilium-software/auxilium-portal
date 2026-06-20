<?php

use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../../vendor/autoload.php';

try
{
    $assessments = APIInteractions::Get(endpoint: '/api/v3/wemwbs/my-assessments');

    var_dump($assessments);
    die();

    PageBuilder::AutoRender(
        variables: [
            'Assessments' => $assessments,
        ]
    );
}
catch (Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}
