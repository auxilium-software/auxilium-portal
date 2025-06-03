<?php

use Auxilium\Auxilium\InitHelpers;
use Auxilium\DatabaseInteractions\Deegraph\DeegraphServerConnection;
use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBServerConnection;
use Auxilium\Helpers\ConfigurationManagement\CredentialManagement;
use Auxilium\Helpers\ConfigurationManagement\EnvironmentManagement;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\Security;
use Darksparrow\DeegraphInteractions\Core\DeegraphServer;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;
use Darksparrow\DeegraphInteractions\Exceptions\InvalidUUIDFormatException;
use JetBrains\PhpStorm\NoReturn;

require_once __DIR__ . '/../../vendor/autoload.php';



$setup_key = InitHelpers::HandleSetupKey();

if(!file_exists(__DIR__ . "/../../LocalStorage/LocalStorage/setup.vars"))
{
    file_put_contents(__DIR__ . "/../../LocalStorage/LocalStorage/setup.vars", "{}");

    $creds = new CredentialManagement(newInstance: true, newVariables: [
        'instance-domain' => "",

        'mariadb-host' => "",
        'mariadb-username' => "",
        'mariadb-password' => "",
        'mariadb-database' => "",

        'deegraph-host' => "",
        'deegraph-port' => 0,
        'deegraph-loginNode' => "",
        'deegraph-rootNode' => "",
        'deegraph-token'=>"",
    ]);
    $creds->Write();
    $envs = new EnvironmentManagement(newInstance: true, newVariables: []);
    $envs->Write();
    NavigationUtilities::Redirect(target: "/system/init");
}

require_once __DIR__ . '/../../Configuration/Configuration/Environment.php';

foreach($_POST as $key => $value)
{
    InitHelpers::AddVariable($key, $value);
}
$variables = InitHelpers::GetVariables();






switch($_GET['page'])
{
    case "0":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/00.Welcome.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "1":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/01.InstanceDetails.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "2":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/02.MariaDB.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "2.1":
        $creds21 = new CredentialManagement();
        $creds21->OverwriteVariable(key: 'INSTANCE_CREDENTIAL_SQL_HOST',        value: $variables['mariadb-host']);
        // $creds21->OverwriteVariable(key: 'INSTANCE_CREDENTIAL_SQL_PORT', value: $variables['mariadb-port']);
        $creds21->OverwriteVariable(key: 'INSTANCE_CREDENTIAL_SQL_USERNAME',    value: $variables['mariadb-username']);
        $creds21->OverwriteVariable(key: 'INSTANCE_CREDENTIAL_SQL_PASSWORD',    value: $variables['mariadb-password']);
        $creds21->OverwriteVariable(key: 'INSTANCE_CREDENTIAL_SQL_DATABASE',    value: $variables['mariadb-database']);
        $creds21->Write();

        try {
            $servername = INSTANCE_CREDENTIAL_SQL_HOST;
            $database = INSTANCE_CREDENTIAL_SQL_DATABASE;
            $username = INSTANCE_CREDENTIAL_SQL_USERNAME;
            $password = INSTANCE_CREDENTIAL_SQL_PASSWORD;

            $pdo = new PDO(
                dsn     : "mysql:host=$servername;dbname=$database",
                username: $username,
                password: $password,
            );
            $pdo->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);

            InitHelpers::AddVariable("error", null);
            NavigationUtilities::Redirect(
                target: "/system/init?page=3&setup_key=$setup_key",
            );
        }
        catch (PDOException $e) {
            InitHelpers::AddVariable("error", $e->getMessage());
            NavigationUtilities::Redirect(
                target: "/system/init?page=2&setup_key=$setup_key",
            );
        }
    case "3":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/03.Deegraph.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "3.1":
        $creds31 = new CredentialManagement();
        $creds31->OverwriteVariable(key: 'INSTANCE_UUID',                       value: $variables['deegraph-rootNode']);
        $creds31->OverwriteVariable(key: 'INSTANCE_CREDENTIAL_DDS_HOST',        value: $variables['deegraph-host']);
        $creds31->OverwriteVariable(key: 'INSTANCE_CREDENTIAL_DDS_PORT',        value: $variables['deegraph-port']);
        $creds31->OverwriteVariable(key: 'INSTANCE_CREDENTIAL_DDS_LOGIN_NODE',  value: $variables['deegraph-loginNode']);
        $creds31->OverwriteVariable(key: 'INSTANCE_CREDENTIAL_DDS_TOKEN',       value: $variables['deegraph-token']);
        $creds31->OverwriteVariable(key: 'ACCEPT_SELF_SIGNED_CERTIFICATES',     value: $variables['deegraph-allowSelfSignedCerts'] === "on");
        $creds31->Write();

        try {
            $actorID = new UUID($variables['deegraph-loginNode']);
        }
        catch(InvalidUUIDFormatException $e) {
            InitHelpers::AddVariable("error", $e->getMessage());
            NavigationUtilities::Redirect(
                target: "/system/init?page=3&setup_key=$setup_key",
            );
        }
        try {
            $t = DeegraphServerConnection::GetConnection()->serverInfo($actorID);

            InitHelpers::AddVariable("error", null);
            NavigationUtilities::Redirect(
                target: "/system/init?page=4&setup_key=$setup_key",
            );
        }
        catch (Exception $e) {
            InitHelpers::AddVariable("error", $e->getMessage());
            NavigationUtilities::Redirect(
                target: "/system/init?page=3&setup_key=$setup_key",
            );
        }
    case "4":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/04.RootAccount.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "5":
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/05.Summary.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
    case "5.1":
        if(!(array_key_exists(key: 'setupComplete-mariadb', array: $variables) && $variables['setupComplete-mariadb'] === true))
        {
            InitHelpers::AddVariable("setupComplete-mariadb", (new MariaDBServerConnection())->InitialDatabaseSetup());
        }

        if(!(array_key_exists(key: 'setupComplete-deegraph', array: $variables) && $variables['setupComplete-deegraph'] === true))
        {
            $initialQueries = [
                // system permissions
                "GRANT READ,WRITE,DELETE WHERE @creator === /", // grant CRUD permissions where the creator is the current node
                "GRANT READ,WRITE,DELETE,ACT WHERE . === /",    // grant all permissions where the current node is the root node
                "GRANT READ ON /*",                             //
                "GRANT READ ON {" . INSTANCE_UUID . "}",        //
                // case permissions
                "GRANT READ,WRITE ON /cases/# ON /cases/#/*",   // grant read and write permissions on the user's cases
                "GRANT READ,WRITE ON /cases/#/todos/#",         // grant read and write (for deletion) permissions on the to do items on a case
                "GRANT READ ON /cases/#/workers/#/name ON /cases/#/workers/#/display_name ON /cases/#/workers/#/preferred_language ON /cases/#/workers/#/contact_email", // grant read permissions for case beneficiaries to see their caseworkers
                // misc permissions
                "GRANT READ ON /messages/#",                    //
                "GRANT READ,WRITE,DELETE ON /assigned_cases/# ON /assigned_cases/#/* ON /assigned_cases/#/messages/# DELEGATABLE", //
            ];

            foreach($initialQueries as $query)
            {
                try
                {
                    GraphDatabaseConnection::query(
                        new User(INSTANCE_CREDENTIAL_DDS_LOGIN_NODE),
                        $query
                    );
                }
                catch(Exception $e)
                {
                    InitHelpers::RenderCriticalError(errorMessage: $e->getMessage());
                }
            }
            InitHelpers::AddVariable("setupComplete-deegraph", true);
        }

        $envs = new EnvironmentManagement(newInstance: true, newVariables: $variables);
        $envs->Write();

        if(!(array_key_exists(key: 'setupComplete-rootUser', array: $variables) && $variables['setupComplete-rootUser'] === true))
        {
            InitHelpers::CreateRootAccount($variables);
            InitHelpers::AddVariable("setupComplete-rootUser", true);
        }


        $creds51 = new CredentialManagement();
        $creds51->OverwriteVariable(key: 'INSTANCE_DOMAIN_NAME',    value: $variables['instance-domain']);
        $creds51->Write();


        NavigationUtilities::Redirect(
            target: "/system/init?page=6&setup_key=$setup_key",
        );
    case "6":
        unlink(LOCAL_STORAGE_DIRECTORY . "/setup.key");
        unlink(LOCAL_STORAGE_DIRECTORY . "/setup.vars");
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/06.Completed.html.twig",
            variables: [
                "Variables"=>$variables,
                "SetupKey"=>$setup_key,
            ]
        );
}
