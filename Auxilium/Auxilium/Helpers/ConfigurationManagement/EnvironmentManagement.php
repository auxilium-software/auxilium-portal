<?php

namespace Auxilium\Helpers\ConfigurationManagement;

use Auxilium\Utilities\Security;

class EnvironmentManagement
{
    private string $EnvironmentFileLocation;
    private array $Variables;


    public function __construct(bool $newInstance = false, array $newVariables = [])
    {
        $this->EnvironmentFileLocation = __DIR__ . '/../../../Configuration/Configuration/Environment.php';
        if($newInstance)
        {
            $this->Variables = [
                "CREDENTIALS_FILE_LOCATION"         => __DIR__ . '/../../../Configuration/Configuration/Credentials.php',
                "LOCAL_EPHEMERAL_CREDENTIAL_STORE"  => __DIR__ . '/../../../LocalStorage/EphemeralCredentialsStore',
                "LOCAL_STORAGE_DIRECTORY"           => __DIR__ . '/../../../LocalStorage/LocalStorage',

                "ENVIRONMENT_TYPE"  => "PROD",

                "INSTANCE_BRANDING_LOGO"                        => "AuxiliumLogo.png",
                "INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR"   => "AuxiliumLogo-White.png",
                "INSTANCE_BRANDING_NAME"                        => "Auxilium (Docker)",

                "INSTANCE_INFO_MAIN_EMAIL"                  => "help-dev@auxiliumsoftware.co.uk",
                "INSTANCE_INFO_MAIN_PHONE"                  => "<helpline phone>",
                "INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS"    => "N/a",
                "INSTANCE_INFO_MAIN_TEXT"                   => "<helpline text>",
                "INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS"     => "N/a",

                "INSTANCE_INFO_MAINTAINER_NAME"     => "Cerys Lewis",
                "INSTANCE_INFO_MAINTAINER_EMAIL"    => "cerys.lewis@aber.ac.uk",

                "INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME"  => "Cerys Lewis",
                "INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL" => "cerys.lewis@aber.ac.uk",

                "CLIENT_SIGN_UP_INVITE_ONLY"        => false,
                "EXTERNAL_ORG_SIGN_UP_INVITE_ONLY"  => false,
                "STAFF_SIGN_UP_INVITE_ONLY"         => true,

                "GLOBAL_DEFAULT_TIMEZONE" => "Europe/London",
            ];
        }
        else
        {
            $this->Variables = [
                "CREDENTIALS_FILE_LOCATION"         => CREDENTIALS_FILE_LOCATION,
                "LOCAL_EPHEMERAL_CREDENTIAL_STORE"  => LOCAL_EPHEMERAL_CREDENTIAL_STORE,
                "LOCAL_STORAGE_DIRECTORY"           => LOCAL_STORAGE_DIRECTORY,

                "ENVIRONMENT_TYPE"  => ENVIRONMENT_TYPE,

                "INSTANCE_BRANDING_LOGO"                        => INSTANCE_BRANDING_LOGO,
                "INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR"   => INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR,
                "INSTANCE_BRANDING_NAME"                        => INSTANCE_BRANDING_NAME,

                "INSTANCE_INFO_MAIN_EMAIL"                  => INSTANCE_INFO_MAIN_EMAIL,
                "INSTANCE_INFO_MAIN_PHONE"                  => INSTANCE_INFO_MAIN_PHONE,
                "INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS"    => INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS,
                "INSTANCE_INFO_MAIN_TEXT"                   => INSTANCE_INFO_MAIN_TEXT,
                "INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS"     => INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS,

                "INSTANCE_INFO_MAINTAINER_NAME"     => INSTANCE_INFO_MAINTAINER_NAME,
                "INSTANCE_INFO_MAINTAINER_EMAIL"    => INSTANCE_INFO_MAINTAINER_EMAIL,

                "INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME"  => INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME,
                "INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL" => INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL,

                "CLIENT_SIGN_UP_INVITE_ONLY"        => CLIENT_SIGN_UP_INVITE_ONLY,
                "EXTERNAL_ORG_SIGN_UP_INVITE_ONLY"  => EXTERNAL_ORG_SIGN_UP_INVITE_ONLY,
                "STAFF_SIGN_UP_INVITE_ONLY"         => STAFF_SIGN_UP_INVITE_ONLY,

                "GLOBAL_DEFAULT_TIMEZONE" => GLOBAL_DEFAULT_TIMEZONE,
            ];
        }
    }

    public function OverwriteVariable(string $key, string $value): void
    {
        $this->Variables[$key] = $value;
    }

    public function Write()
    {
        $clientSignUpInviteOnly = $this->Variables["CLIENT_SIGN_UP_INVITE_ONLY"] ? 'true' : 'false';
        $externalOrgSignupInviteOnly = $this->Variables["EXTERNAL_ORG_SIGN_UP_INVITE_ONLY"] ? 'true' : 'false';
        $staffSignUpInviteOnly = $this->Variables["STAFF_SIGN_UP_INVITE_ONLY"] ? 'true' : 'false';

        $fileContents = <<<PHP
<?php

require_once __DIR__ . '/../../vendor/autoload.php';

const CREDENTIALS_FILE_LOCATION         = "{$this->Variables["CREDENTIALS_FILE_LOCATION"]}";
const LOCAL_EPHEMERAL_CREDENTIAL_STORE  = "{$this->Variables["LOCAL_EPHEMERAL_CREDENTIAL_STORE"]}";
const LOCAL_STORAGE_DIRECTORY           = "{$this->Variables["LOCAL_STORAGE_DIRECTORY"]}";

const ENVIRONMENT_TYPE  = "{$this->Variables["ENVIRONMENT_TYPE"]}";

const INSTANCE_BRANDING_LOGO                        = "{$this->Variables["INSTANCE_BRANDING_LOGO"]}";
const INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR   = "{$this->Variables["INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR"]}";
const INSTANCE_BRANDING_NAME                        = "{$this->Variables["INSTANCE_BRANDING_NAME"]}";

const INSTANCE_INFO_MAIN_EMAIL                  = "{$this->Variables["INSTANCE_INFO_MAIN_EMAIL"]}";
const INSTANCE_INFO_MAIN_PHONE                  = "{$this->Variables["INSTANCE_INFO_MAIN_PHONE"]}";
const INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS    = "{$this->Variables["INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS"]}";
const INSTANCE_INFO_MAIN_TEXT                   = "{$this->Variables["INSTANCE_INFO_MAIN_TEXT"]}";
const INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS     = "{$this->Variables["INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS"]}";

const INSTANCE_INFO_MAINTAINER_NAME                 = "{$this->Variables["INSTANCE_INFO_MAINTAINER_NAME"]}";
const INSTANCE_INFO_MAINTAINER_EMAIL                = "{$this->Variables["INSTANCE_INFO_MAINTAINER_EMAIL"]}";
const INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME  = "{$this->Variables["INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME"]}";
const INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL = "{$this->Variables["INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL"]}";

const CLIENT_SIGN_UP_INVITE_ONLY        = {$clientSignUpInviteOnly};
const EXTERNAL_ORG_SIGN_UP_INVITE_ONLY  = {$externalOrgSignupInviteOnly};
const STAFF_SIGN_UP_INVITE_ONLY         = {$staffSignUpInviteOnly};

const GLOBAL_DEFAULT_TIMEZONE   = "{$this->Variables["GLOBAL_DEFAULT_TIMEZONE"]}";



// Nothing below this line should realistically be changed
switch (ENVIRONMENT_TYPE) {
    case "DEV":
        error_reporting(E_ALL);
        ini_set("display_errors", 1);
        break;
    case "DEMO":
        error_reporting(E_ERROR);
        ini_set("display_errors", 1);
        break;
    default:
        error_reporting(E_ERROR);
        ini_set("display_errors", 0);
        break;
}
require_once CREDENTIALS_FILE_LOCATION;
const INSTANCE_BRANDING_DOMAIN_NAME = INSTANCE_DOMAIN_NAME; // For backwards compatibility, these must be the same;
\Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling::\$URLBase = "https://schemas.auxiliumsoftware.co.uk/v1/";

PHP;

        file_put_contents($this->EnvironmentFileLocation, $fileContents);
    }

}