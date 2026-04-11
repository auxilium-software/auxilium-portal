<?php

use Auxilium\ServiceInteractions\APIInteractions;
use Auxilium\TwigHandling\PageBuilder;
use chillerlan\QRCode\QRCode;

require_once __DIR__ . '/../../../vendor/autoload.php';

try
{
    // if totp is already enabled, redirect to management
    $status = APIInteractions::Get(endpoint: '/me/totp/status');

    if ($status->Payload['isEnabled'] === true)
    {
        header('Location: /me/mfa-management');
        exit;
    }

    // generate a pending secret and QR code
    $setup = APIInteractions::Post(
        endpoint: '/me/totp/setup',
        payload: []
    );

    $qrCode = (new QRCode())->render($setup->Payload['provisioningUri']);

    PageBuilder::AutoRender(
        variables: [
            'QrCodeImgData' => $qrCode,
            'ManualSecret'  => $setup->Payload['secret'],
        ]
    );
}
catch (Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}
