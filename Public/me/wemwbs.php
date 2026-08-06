<?php

use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../../vendor/autoload.php';

try
{
    $assessments = APIInteractions::Get(endpoint: '/api/v3/wemwbs/my-statistics');

    PageBuilder::AutoRender(
        variables: [
            'Assessments' => $assessments->Payload,
        ]
    );
}
catch (Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}
