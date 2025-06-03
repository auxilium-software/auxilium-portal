<?php

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBServerConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBTable;
use Auxilium\DatabaseInteractions\MariaDB\SQLQueryBuilderWrapper;
use Auxilium\EmailHandling\EmailBuilder;
use Auxilium\EmailHandling\InternetMessageTransport;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\Exceptions\MessageSendException;
use Auxilium\PersistentFormData;
use Auxilium\Schemas\UserSchema;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Utilities\EncodingTools;
use Auxilium\Utilities\NavigationUtilities;
use Auxilium\Utilities\Security;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

try
{
    $db = new MariaDBServerConnection();

    $form_data = Auxilium\PersistentFormData::get();

    if($form_data === null)
    {
        $form_data = [
            "form_step" => null,
            "form_stack" => []
        ];
    }
    if($form_data["form_step"] === null)
    {
        $form_data["form_step"] = "USER_TYPE";
    }
    $form_values = $_POST;
    $form_validation_failures = [];
    $valid_submission = true;
    try
    {
        switch($form_data["form_step"])
        {
            case "INVITE_CODE":
                if(isset($_POST["invite_code"]))
                {
                    $returned_data = $db->RunSelect(
                        queryBuilder: SQLQueryBuilderWrapper::SELECT(MariaDBTable::INVITE_CODES)
                            ->cols(cols: [
                                'invite_rule',
                                'invite_code'
                            ]
                            )
                            ->where(cond: 'invite_code=:__invite_code__')
                            ->bindValue(name: '__invite_code__', value: strtolower(str_replace(" ", "", $_POST["invite_code"])))
                    );
                    if($returned_data === null)
                    {
                        $form_validation_failures["invite_code"] = true;
                    }
                    else
                    {
                        $db->RunDelete(
                            queryBuilder: SQLQueryBuilderWrapper::DELETE(MariaDBTable::INVITE_CODES)
                                ->where(cond: 'invite_code=:__invite_code__')
                                ->bindValue(name: '__invite_code__', value: $returned_data["invite_code"])
                        );
                    }
                }
                break;
            case "VERIFY_ACCOUNT_EMAIL":
                if(isset($_POST["email_address_verification_code"]))
                {
                    //$form_data["form_step"] = null;
                    $returned_data = $db->RunOneRowSelect(
                        queryBuilder: SQLQueryBuilderWrapper::SELECT(MariaDBTable::EMAIL_VERIFICATION_CODES)
                            ->cols(cols: [
                                'user_uuid',
                                'email_address'
                            ]
                            )
                            ->where(cond: 'verification_code=:__verification_code__')
                            ->where(cond: 'user_uuid=:__user_uuid__')
                            ->bindValue(name: '__verification_code__', value: strtolower(str_replace(" ", "", $_POST["email_address_verification_code"])))
                            ->bindValue(name: '__user_uuid__', value: $form_data["user_uuid"])
                    );


                    if($returned_data === null)
                    {
                        $form_validation_failures["email_address_verify_code"] = true;
                    }
                    else
                    {
                        $db->RunInsert(
                            queryBuilder: SQLQueryBuilderWrapper::INSERT(MariaDBTable::STANDARD_LOGINS)
                                ->set(col: 'email_address', value: ':__email_address__')
                                ->set(col: 'user_uuid', value: ':__user_uuid__')
                                ->set(col: 'password', value: ':__password__')
                                ->bindValue(name: '__email_address__', value: $returned_data["email_address"])
                                ->bindValue(name: '__user_uuid__', value: $returned_data["user_uuid"])
                                ->bindValue(name: '__password__', value: $form_data["hashed_password"])
                        );


                        $user_node = DeegraphNode::from_id($returned_data["user_uuid"]);
                        $email_prop = GraphDatabaseConnection::new_node($returned_data["email_address"], "text/plain", null, User::get_system_node());
                        $user_node->addProperty("contact_email", $email_prop, User::get_system_node()); // Do all of this as the system node, since users shouldn't just be able to randomly change their email address

                        $db->RunDelete(
                            queryBuilder: SQLQueryBuilderWrapper::DELETE(MariaDBTable::EMAIL_VERIFICATION_CODES)
                                ->where(cond: 'user_uuid=:__user_uuid__')
                                ->bindValue(name: '__user_uuid__', value: $returned_data["user_uuid"])
                        );
                        $form_data["form_step"] = null;
                        CookieHandling::SetSessionKey(sessionKey: $form_data["session_key"]);
                        PersistentFormData::set($form_data);

                        $next_location = array_pop($form_data["form_stack"]);
                        if($next_location === null)
                        {
                            NavigationUtilities::Redirect(target: "/dashboard");
                        }
                        NavigationUtilities::Redirect(target: $next_location);
                    }
                }
                break;
            case "CREATE_ACCOUNT":

                if(!isset($_POST["privacy_policy_consent"]))
                {
                    $form_validation_failures["privacy_policy_consent"] = true;
                    break;
                }
                else if(!($_POST["privacy_policy_consent"] == "true"))
                {
                    $form_validation_failures["privacy_policy_consent"] = true;
                    break;
                }

                if(isset($_POST["full_name"]))
                {
                    $form_values["full_name"] = $_POST["full_name"];
                    if(strlen($_POST["full_name"]) < 3)
                    {
                        $form_validation_failures["full_name"] = true;
                        $valid_submission = false;
                    }
                }
                else
                {
                    $form_validation_failures["full_name"] = true;
                    $valid_submission = false;
                }

                if(isset($_POST["email_address"]))
                {
                    $form_values["email_address"] = $_POST["email_address"];
                    if(!filter_var($_POST["email_address"], FILTER_VALIDATE_EMAIL))
                    {
                        $form_validation_failures["email_address_valid"] = true;
                        $valid_submission = false;
                    }
                }
                else
                {
                    $form_validation_failures["email_address_valid"] = true;
                    $valid_submission = false;
                }

                if(isset($_POST["password"]))
                {
                    if(strlen($_POST["password"]) >= 8)
                    {
                        if(isset($_POST["password_confirm"]))
                        {
                            if(!($_POST["password_confirm"] == $_POST["password"]))
                            {
                                $form_values["password"] = "";
                                $form_values["password_confirm"] = "";
                                $form_validation_failures["password_confirm"] = true;
                                $valid_submission = false;
                            }
                        }
                        else
                        {
                            $form_values["password"] = "";
                            $form_values["password_confirm"] = "";
                            $form_validation_failures["password_confirm"] = true;
                            $valid_submission = false;
                        }
                    }
                    else
                    {
                        $form_values["password"] = "";
                        $form_values["password_confirm"] = "";
                        $form_validation_failures["password_length"] = true;
                        $valid_submission = false;
                    }
                }
                else
                {
                    $form_values["password"] = "";
                    $form_values["password_confirm"] = "";
                    $form_validation_failures["password_length"] = true;
                    $valid_submission = false;
                }

                $query_response = $db->RunOneRowSelect(
                    queryBuilder: SQLQueryBuilderWrapper::SELECT(MariaDBTable::STANDARD_LOGINS)
                        ->cols(cols: [
                            'COUNT(*) AS Counter'
                        ]
                        )
                        ->where(cond: 'email_address=:__email_address__')
                        ->bindValue(name: '__email_address__', value: strtolower($_POST["email_address"]))
                );

                if($query_response["Counter"] > 0)
                {
                    $form_validation_failures["email_address_unique"] = true;
                    $valid_submission = false;
                }

                if($valid_submission)
                {
                    $pre_hashed_password = base64_encode(hash("sha256", $_POST["password"], true));
                    // NOTE: BCrypt has a max input of 72 chars, so in order to mitigate attacks on sentence based passwords, that are long but lower complexity, we must pre-hash the password and then base64 encode to get down to 44 chars, which is under the limit. These 44 chars still have plenty of entropy thanks to sha256 being a robust hash algorithm.

                    $user_node = \Auxilium\DatabaseInteractions\GraphDatabaseConnection::new_node(null, null, URLHandling::GetURLForSchema(UserSchema::class), User::get_system_node());
                    $user_node = new User($user_node->getId());

                    $hash_options = [
                        "cost" => 12,
                    ];
                    $hashed_password = password_hash($pre_hashed_password, PASSWORD_BCRYPT, $hash_options);

                    $word_list = json_decode(file_get_contents(__DIR__ . "/../byte-word-list.json"), true);

                    $garbage_data = Security::GeneratePseudoRandomBytes(length: 4);

                    $verification_code = $word_list[ord($garbage_data[0])] . " " . $word_list[ord($garbage_data[1])] . " " . $word_list[ord($garbage_data[2])] . " " . $word_list[ord($garbage_data[3])];

                    //$twig_variables["verification_code"] = $verification_code;
                    //$twig_variables["first_name"] = explode(" ", $data["full_name"])[0];
                    $db->RunInsert(
                        queryBuilder: SQLQueryBuilderWrapper::INSERT(MariaDBTable::EMAIL_VERIFICATION_CODES)
                            ->set(col: 'user_uuid', value: ':__user_uuid__')
                            ->set(col: 'verification_code', value: ':__verification_code__')
                            ->set(col: 'email_address', value: ':__email_address__')
                            ->bindValue(name: '__user_uuid__', value: $user_node->getId())
                            ->bindValue(name: '__email_address__', value: strtolower($_POST["email_address"]))
                            ->bindValue(name: '__verification_code__', value: str_replace(" ", "", $verification_code))
                    );

                    $email = (new EmailBuilder())
                        ->setTemplate(template: "new-account-verification-code")
                        ->setTemplateProperty(key: "verification_code", value: $verification_code)
                        ->setTemplateProperty(key: "recipient_name", value: explode(" ", $form_values["full_name"])[0])
                        ->setSubject(subject: "Account creation security code")
                        ->addRecipient(recipient: strtolower($_POST["email_address"]), name: $form_values["full_name"])
                        ->build();
                    InternetMessageTransport::send($email, "MIME");

                    $language_prop = GraphDatabaseConnection::new_node(
                        data      : strtoupper(PageBuilder2::GetVariable("lang", "en")),
                        media_type: "text/plain",
                        schema    : null,
                        creator   : $user_node
                    );
                    $user_node->addProperty(
                        key  : "preferred_language",
                        node : $language_prop,
                        actor: $user_node
                    ); // Set it to whatever the language is currently in
                    $full_name_prop = GraphDatabaseConnection::new_node(
                        data      : $form_values["full_name"],
                        media_type: "text/plain",
                        schema    : null,
                        creator   : $user_node
                    );
                    $user_node->addProperty(
                        key  : "name",
                        node : $full_name_prop,
                        actor: $user_node
                    );
                    $name_prop = GraphDatabaseConnection::new_node(
                        data      : explode(" ", $form_values["full_name"])[0],
                        media_type: "text/plain",
                        schema    : null,
                        creator   : $user_node
                    );
                    $user_node->addProperty(
                        key  : "display_name",
                        node : $name_prop,
                        actor: $user_node
                    ); // Create this as default the user's first name - they can change it later if they want

                    $session_key = rtrim(strtr(base64_encode(Security::GeneratePseudoRandomBytes(length: 64)), '+/', '-_'), '='); // 512 bits should be long enough to be practically impossible to guess. Even allowing one guess per millesecond (which is already better than the bottleneck of the JISC network) it will take 5 395 141 535 403 007 094 485 264 577 years. This is conserably longer than the time we have left before the Earth is consumed by the Sun turning into a red giant.


                    $db->RunInsert(
                        queryBuilder: SQLQueryBuilderWrapper::INSERT(MariaDBTable::PORTAL_SESSIONS)
                            ->set(col: 'session_uuid', value: ':__session_uuid__')
                            ->set(col: 'session_key', value: ':__session_key__')
                            ->set(col: 'user_uuid', value: ':__user_uuid__')
                            ->set(col: 'unique_sub', value: ':__unique_sub__')
                            ->set(col: 'ip_address', value: ':__ip_address__')
                            ->set(col: 'active', value: ':__active__')
                            ->bindValue(name: '__session_uuid__', value: EncodingTools::GenerateNewUUID())
                            ->bindValue(name: '__session_key__', value: $session_key)
                            ->bindValue(name: '__user_uuid__', value: $user_node->getId())
                            ->bindValue(name: '__unique_sub__', value: strtolower($_POST["email_address"]))
                            ->bindValue(name: '__ip_address__', value: $_SERVER["REMOTE_ADDR"])
                            ->bindValue(name: '__active__', value: 1)
                    );

                    $form_data["user_uuid"] = $user_node->getId();
                    $form_data["session_key"] = $session_key;
                    $form_data["email_address"] = strtolower($_POST["email_address"]);
                    $form_data["hashed_password"] = $hashed_password;

                    $form_data["form_step"] = "VERIFY_ACCOUNT_EMAIL";
                }

                break;
            case "USER_TYPE":
            default:
                if(isset($_POST["user_type"]))
                {
                    $form_data["user_type"] = $_POST["user_type"];
                }
                if(isset($form_data["user_type"]))
                {
                    if($form_data["user_type"] == "STAFF")
                    {
                        if(STAFF_SIGN_UP_INVITE_ONLY)
                        {
                            $form_data["form_step"] = "INVITE_CODE";
                        }
                        else
                        {
                            $form_data["form_step"] = "CREATE_ACCOUNT";
                        }
                    }
                    else if($form_data["user_type"] == "ORGANISATION" || $form_data["user_type"] == "LAWYER")
                    {
                        if(EXTERNAL_ORG_SIGN_UP_INVITE_ONLY)
                        {
                            $form_data["form_step"] = "INVITE_CODE";
                        }
                        else
                        {
                            $form_data["form_step"] = "CREATE_ACCOUNT";
                        }
                    }
                    else
                    {
                        if(CLIENT_SIGN_UP_INVITE_ONLY)
                        {
                            $form_data["form_step"] = "INVITE_CODE";
                        }
                        else
                        {
                            $form_data["form_step"] = "CREATE_ACCOUNT";
                        }
                    }
                }
                break;
        }

        PageBuilder2::AddVariable(variableName: 'form_values', variableValue: $form_values);
        PageBuilder2::AddVariable(variableName: 'form_validation_failures', variableValue: $form_validation_failures);
        PageBuilder2::AddVariable(variableName: 'form_persistence_key', variableValue: PersistentFormData::set($form_data));

        switch($form_data["form_step"])
        {
            case "VERIFY_ACCOUNT_EMAIL":
                PageBuilder2::Render(
                    template : "Pages/sign-up-form/account-verify-email.html.twig",
                    variables: [
                        "email_address" => $form_data["email_address"],
                    ]
                );
            case "CREATE_ACCOUNT":
                PageBuilder2::Render(
                    template : "Pages/sign-up-form/account-sign-up.html.twig",
                    variables: [
                    ]
                );
            case "INVITE_CODE":
                PageBuilder2::Render(
                    template : "Pages/sign-up-form/invite-code.html.twig",
                    variables: [
                    ]
                );
            case "USER_TYPE":
            default:
                PageBuilder2::Render(
                    template : "Pages/sign-up-form/account-type.html.twig",
                    variables: [
                    ]
                );
        }
    }
    catch(DatabaseConnectionException|MessageSendException $e)
    {
        PageBuilder2::RenderInternalSystemError($e);
    }
}
catch(Exception $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}
