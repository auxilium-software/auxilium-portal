<?php

namespace Auxilium\Auxilium;

use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBServerConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBTable;
use Auxilium\DatabaseInteractions\MariaDB\SQLQueryBuilderWrapper;
use Auxilium\Schemas\UserSchema;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\Security;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;
use Darksparrow\DeegraphInteractions\Exceptions\InvalidUUIDFormatException;
use JetBrains\PhpStorm\NoReturn;

class InitHelpers
{
    #[NoReturn] public static function RenderCriticalError(string $errorMessage): void
    {
        unlink(filename: LOCAL_STORAGE_DIRECTORY . "/setup.lock");
        $errorFile = fopen(LOCAL_STORAGE_DIRECTORY . "/setup.error", "w") or die("Unable to write error file!");
        fwrite($errorFile, $errorMessage);
        fclose($errorFile);
        PageBuilder2::Render(
            template : "Pages/system/~InitSteps/InitFailure.html.twig",
            variables: []
        );
    }

    public static function HandleSetupKey(): string
    {
        $setup_key = null;

        if(isset($_GET["setup_key"]))
        {
            if(file_exists(__DIR__ . "/../../LocalStorage/LocalStorage/setup.key"))
            {
                $key_file = fopen(__DIR__ . "/../../LocalStorage/LocalStorage/setup.key", "r") or die("Unable to read keyfile!");
                $file_size = filesize(__DIR__ . "/../../LocalStorage/LocalStorage/setup.key");
                $match_key = fread($key_file, $file_size);
                if(trim($match_key) === trim($_GET["setup_key"]))
                {
                    $setup_key = trim($_GET["setup_key"]);
                }
                else
                {
                    PageBuilder2::Render(
                        template : "Pages/system/init-locked-out.html.twig",
                        variables: []
                    );
                }
            }
            else
            {
                PageBuilder2::Render(
                    template : "Pages/system/init-step-3-done.html.twig",
                    variables: []
                );
            }
        }
        if($setup_key === null)
        {
            $setup_key = rtrim(strtr(base64_encode(Security::GeneratePseudoRandomBytes(length: 64)), '+/', '-_'), '=');
            $key_file = fopen(__DIR__ . "/../../LocalStorage/LocalStorage/setup.key", "w") or die("Unable to write keyfile!");
            fwrite($key_file, $setup_key);
            fclose($key_file);
            NavigationUtilities::Redirect(target: "/system/init?page=1&setup_key=$setup_key");
        }

        return $setup_key;
    }

    public static function GetVariables(): array
    {
        return json_decode(file_get_contents(LOCAL_STORAGE_DIRECTORY . "/setup.vars"), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function AddVariable(string $key, string|int|null|bool $value): void
    {
        $variables = self::GetVariables();
        $variables[$key] = $value;

        $variableFile = fopen(LOCAL_STORAGE_DIRECTORY . "/setup.vars", "w") or die("Unable to write var file!");
        fwrite($variableFile, json_encode($variables, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        fclose($variableFile);
    }


    /**
     * @throws InvalidUUIDFormatException
     */
    public static function CreateRootAccount(array $variables): void
    {
        $rootUserName           = $variables['rootAccount-name'];
        $rootUserEmailAddress   = $variables['rootAccount-email'];
        $rootUserPassword       = $variables['rootAccount-password'];


        $pre_hashed_password = base64_encode(hash("sha256", $rootUserPassword, true));
        $user_node = GraphDatabaseConnection::new_node(
            data      : null,
            media_type: null,
            schema    : URLHandling::GetURLForSchema(UserSchema::class),
            creator   : new User(INSTANCE_CREDENTIAL_DDS_LOGIN_NODE)
        );
        $user_node = new User($user_node->getId());

        $hash_options = [
            "cost" => 12,
        ];
        $hashed_password = password_hash($pre_hashed_password, PASSWORD_BCRYPT, $hash_options);


        $db = new MariaDBServerConnection();
        $queryBuilder = SQLQueryBuilderWrapper::INSERT(MariaDBTable::STANDARD_LOGINS)
            ->set(col: 'email_address', value: ':__email_address__')
            ->set(col: 'user_uuid', value: ':__user_uuid__')
            ->set(col: 'password', value: ':__password__')
            ->bindValue('__email_address__', $rootUserEmailAddress)
            ->bindValue('__user_uuid__', $user_node->getId())
            ->bindValue('__password__', $hashed_password)
            ;
        $db->RunInsert($queryBuilder);


        GraphDatabaseConnection::query(new User(INSTANCE_CREDENTIAL_DDS_LOGIN_NODE), "GRANT READ,WRITE,DELETE,ACT WHERE / === {" . $user_node->getId() . "}");

        // $language_prop = GraphDatabaseConnection::new_node(strtoupper(PageBuilder2::GetVariable(variableName: 'selected_lang')), "text/plain", null, $user_node);
        $language_prop = GraphDatabaseConnection::new_node("en-GB", "text/plain", null, $user_node);
        $user_node->addProperty("preferred_language", $language_prop, $user_node);

        $full_name_prop = GraphDatabaseConnection::new_node($rootUserName, "text/plain", null, $user_node);
        $user_node->addProperty("name", $full_name_prop, $user_node);

        $name_prop = GraphDatabaseConnection::new_node(explode(" ", $rootUserName)[0], "text/plain", null, $user_node);
        $user_node->addProperty("display_name", $name_prop, $user_node);

        $email_name_prop = GraphDatabaseConnection::new_node($rootUserEmailAddress, "text/plain", null, $user_node);
        $user_node->addProperty("contact_email", $email_name_prop, $user_node);
    }
}