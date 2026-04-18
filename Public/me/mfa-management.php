<?php

use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;

require_once __DIR__ . '/../../vendor/autoload.php';

try
{
    $status = APIInteractions::Get(endpoint: '/api/v3/me/totp/status');
    $isEnabled = $status->Payload['isEnabled'] ?? false;

    $remainingCodes = 0;

    if ($isEnabled)
    {
        $codesResult = APIInteractions::Get(endpoint: '/api/v3/me/totp/recovery-codes/count');
        $remainingCodes = $codesResult->Payload['remaining'] ?? 0;
    }

    PageBuilder::AutoRender(
        variables: [
            'TotpEnabled'    => $isEnabled,
            'RemainingCodes' => $remainingCodes,
        ]
    );
}
catch (Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}
