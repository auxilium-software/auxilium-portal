<?php

use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;

require_once __DIR__ . '/../../vendor/autoload.php';


/**
 * This is the file path for the "Credentials.php" file.
 *
 * @var string
 */
const CREDENTIALS_FILE_LOCATION = __DIR__ . '/Credentials.php';
/**
 * Directory path for where the portal should store credentials that should be lost after a reboot (ephemeral).
 *
 * @var string
 */
const LOCAL_EPHEMERAL_CREDENTIAL_STORE = __DIR__ . '/../../LocalStorage/EphemeralCredentialsStore';
/**
 * Directory path for where the portal should store encrypted files.
 *
 * @var string
 */
const LOCAL_STORAGE_DIRECTORY = __DIR__ . '/../../LocalStorage/LocalStorage';



/**
 * What the environment type is... is this a production instance? is this a development instance? is this a demo instance?
 *
 * Allowed options:
 * - DEV
 * - DEMO
 * - PROD
 *
 * @var string
 */
const ENVIRONMENT_TYPE = "DEV";



//define("INSTANCE_DOMAIN_NAME", getenv("CONTAINER_FQDN")); // <-- FQDN of instance



const INSTANCE_BRANDING_LOGO = "AuxiliumLogo.png"; // <-- Path relative to \$WEB_ROOT_DIRECTORY/assets/, A version of the logo to use of white/black backgrounds
const INSTANCE_BRANDING_LOGO_CONTRAST_BRAND_COLOR = "AuxiliumLogo-White.png"; // <-- A version of the logo to use on brand color backgrounds
const INSTANCE_BRANDING_NAME = "Auxilium (Docker)"; // <-- Brand name



const INSTANCE_INFO_MAIN_EMAIL = "help-dev@auxiliumsoftware.co.uk"; // <-- This email should match the *external* email accessible via M$ graph with the credentials in \$CREDENTIALS_FILE_LOCATION
const INSTANCE_INFO_MAIN_PHONE = "<helpline phone>"; // <-- Set to null if you do not operate a phone line at the moment
const INSTANCE_INFO_MAIN_PHONE_OPENING_HOURS = "N/a";
const INSTANCE_INFO_MAIN_TEXT = "<helpline text>"; // <-- Set to null if you do not operate a text service at the moment
const INSTANCE_INFO_MAIN_TEXT_OPENING_HOURS = "N/a";



const INSTANCE_INFO_MAINTAINER_NAME = "Cerys Lewis"; // <-- Whoever is managing the system itself
const INSTANCE_INFO_MAINTAINER_EMAIL = "cerys.lewis@aber.ac.uk"; // <-- How to contact them, this email will be shown on error screens or bug reporting pages
const INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_NAME = "Cerys Lewis"; // <-- Whoever is overseeing the project from a research/management perspective
const INSTANCE_INFO_GENERAL_ENQUIRIES_CONTACT_EMAIL = "cerys.lewis@aber.ac.uk"; // <-- How to contact them, this email will be shown next to things like the copyright notice



const CLIENT_SIGN_UP_INVITE_ONLY = false;
const EXTERNAL_ORG_SIGN_UP_INVITE_ONLY = false;
const STAFF_SIGN_UP_INVITE_ONLY = true;



const GLOBAL_DEFAULT_TIMEZONE = "Europe/London";



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
URLHandling::$URLBase = "https://schemas.auxiliumsoftware.co.uk/v1/";
