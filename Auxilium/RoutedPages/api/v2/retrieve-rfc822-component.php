<?php

use Auxilium\Auxilium\AuxiliumLFSObject;
use Auxilium\Utilities\URIUtilities;
use ZBateson\MailMimeParser\Message;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

$at = Auxilium\APITools::get_instance();
$at->requireLogin();

$file_id = null;
$file_hash = null;
$metadata = false;
$mime_type = null;

$uri = new URIUtilities();

if(count($uri->getURIComponents()) > 4)
{
    $spl = explode("+", $uri->getURIComponents()[4]);
    $file_id = strtolower($spl[0]);
    if(count($spl) > 1)
    {
        $file_hash = $spl[1];
    }
    if(count($spl) > 2)
    {
        $mime_type = str_replace(":", "/", urldecode($spl[2]));
    }
}

if($mime_type == null)
{
    // $mime_type = "application/octet-stream";
    $mime_type = "message/rfc822";
}

$desired_components = explode(",", strtolower($uri->getGetParameters()));

$lfsobj = new AuxiliumLFSObject("auxlfs://" . INSTANCE_CREDENTIAL_DDS_HOST . "/" . $file_id . "+" . $file_hash . "+" . urlencode($mime_type));

if(!$lfsobj->canRead())
{
    $at->setErrorText("Missing read permission");
    $at->output();
}

$message_headers = [];
$full_message = Message::from($lfsobj->getData(), false);

foreach($full_message->getAllHeaders() as $header)
{
    $parts = $header->getParts();
    if(count($parts) > 1)
    {
        $res = [];
        foreach($parts as &$part)
        {
            $res[] = $part->getValue();
        }
        $message_headers[strtolower($header->getName())] = $res;
    }
    else
    {
        if(count($parts) > 0)
        {
            $message_headers[strtolower($header->getName())] = $parts[0]->getValue();
        }
    }
}

foreach($desired_components as &$desired_component)
{
    switch(trim($desired_component))
    {
        case "subject":
        case "from":
        case "to":
        case "reply-to":
        case "delivered-to":
        case "return-path":
        case "x-auxilium-message-version":
        case "cc":
        case "bcc":
            $at->setVariable($desired_component, isset($message_headers[$desired_component]) ? $message_headers[$desired_component] : null);
            break;
        case "*":
            foreach($message_headers as $header_name => &$header)
            {
                $at->setVariable($header_name, $header);
            }
            break;
        case "text-content":
            $at->setVariable("text-content", $full_message->getTextContent());
            break;
        default:
            $at->setVariable($desired_component, null);
    }
}

//$at->setVariable("uri", strval($lfsobj));
//$at->setVariable("data", $lfsobj->getData());
$at->output();
