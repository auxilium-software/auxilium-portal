<?php

use Auxilium\Enumerators\CookieKey;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\Helpers\ConfigurationManagement\EnvironmentManagement;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\Security;
use Auxilium\Wrappers\ICMPWrapper;

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../Configuration/Configuration/Environment.php';

if(isset($_POST) && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    $e = new EnvironmentManagement();
    $e->OverwriteVariable(key: 'INSTANCE_INFO_MAIN_EMAIL', value: $_POST['main-emailAddress']);
    $e->OverwriteVariable(key: 'INSTANCE_INFO_MAIN_PHONE', value: $_POST['main-phoneNumber']);
    $e->OverwriteVariable(key: 'INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS', value: $_POST['main-phoneOpeningHours']);
    $e->OverwriteVariable(key: 'INSTANCE_INFO_MAIN_TEXT', value: $_POST['main-textNumber']);
    $e->OverwriteVariable(key: 'INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS', value: $_POST['main-textOpeningHours']);

    $e->OverwriteVariable(key: 'INSTANCE_INFO_MAINTAINER_NAME', value: $_POST['maintainer-name']);
    $e->OverwriteVariable(key: 'INSTANCE_INFO_MAINTAINER_EMAIL', value: $_POST['maintainer-emailAddress']);

    $e->OverwriteVariable(key: 'INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME', value: $_POST['generalEnquiries-name']);
    $e->OverwriteVariable(key: 'INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL', value: $_POST['generalEnquiries-emailAddress']);
    $e->Write();

    NavigationUtilities::Redirect(target: "/system/server-management/contact-details");
}


try
{
    Security::RequireLogin();
    if(Security::IsAdmin())
    {
        PageBuilder2::AutoRender(variables: [
            "Main"=>[
                "EmailAddress"=>INSTANCE_INFO_MAIN_EMAIL,
                "PhoneNumber"=>INSTANCE_INFO_MAIN_PHONE,
                "PhoneOpeningHours"=>INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS,
                "TextNumber"=>INSTANCE_INFO_MAIN_TEXT,
                "TextOpeningHours"=>INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS,
            ],
            "Maintainer"=>[
                "Name"=>INSTANCE_INFO_MAINTAINER_NAME,
                "EmailAddress"=>INSTANCE_INFO_MAINTAINER_EMAIL,
            ],
            "GeneralEnquiries"=>[
                "Name"=>INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME,
                "EmailAddress"=>INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL,
            ],
        ]);
    }
    else
    {
        PageBuilder2::RenderInternalSystemError(new Exception("not admin"));
    }
}
catch(Exception $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}
