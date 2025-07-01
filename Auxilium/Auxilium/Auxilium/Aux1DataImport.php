<?php

namespace Auxilium\Auxilium;

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBServerConnection;
use Auxilium\DatabaseInteractions\MariaDB\MariaDBTable;
use Auxilium\DatabaseInteractions\MariaDB\SQLQueryBuilderWrapper;
use Auxilium\Schemas\CaseSchema;
use Auxilium\Schemas\CollectionSchema;
use Auxilium\Schemas\MessageSchema;
use Auxilium\Schemas\UserSchema;
use Auxilium\TwigHandling\PageBuilder2;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;
use Darksparrow\DeegraphInteractions\Exceptions\InvalidUUIDFormatException;
use Exception;
use JsonException;

class Aux1DataImport
{
    private static array $Data;

    /**
     * @throws JsonException
     * @throws Exception
     */
    public static function Go(string $dumpFilePath): void
    {
        if(!file_exists($dumpFilePath))
        {
            throw new Exception("Dump file not found");
        }

        self::$Data = json_decode(file_get_contents($dumpFilePath), true, 512, JSON_THROW_ON_ERROR);

        $db = new MariaDBServerConnection();


        foreach(self::$Data['Users'] as $userDetails)
        {
            self::CreateUser($db, $userDetails);
        }
        foreach(self::$Data['Cases'] as $userDetails)
        {
            self::CreateCase($db, $userDetails);
        }
    }

    /**
     * @throws InvalidUUIDFormatException
     * @throws Exception
     */
    public static function CreateUser($db, $userDetails): User
    {
        // create the root user node
        $user_node = GraphDatabaseConnection::new_node(
            null,
            null,
            URLHandling::GetURLForSchema(UserSchema::class),
            User::get_system_node()
        );
        $user_node = new User($user_node->getId());


        // create the user in sql
        $db->RunInsert(
            queryBuilder: SQLQueryBuilderWrapper::INSERT(MariaDBTable::STANDARD_LOGINS)
                ->set(col: 'email_address', value: ':__email_address__')
                ->set(col: 'user_uuid', value: ':__user_uuid__')
                ->set(col: 'password', value: ':__password__')
                ->bindValue(name: '__email_address__', value: $userDetails["EmailAddress"])
                ->bindValue(name: '__user_uuid__', value: $user_node->getId())
                ->bindValue(name: '__password__', value: $userDetails["Password"])
        );


        // handle email address
        // Do all of this as the system node, since userIDs shouldn't just be able to randomly change their email address
        $user_node->addProperty(
            key  : "contact_email",
            node : GraphDatabaseConnection::new_node(
                data      : $userDetails["EmailAddress"],
                media_type: "text/plain",
                creator   : User::get_system_node()
            ),
            actor: User::get_system_node()
        );


        // handle language
        // Set it to whatever the language is currently in
        $user_node->addProperty(
            key  : "preferred_language",
            node : GraphDatabaseConnection::new_node(
                data      : strtoupper(PageBuilder2::GetVariable("lang", "en")),
                media_type: "text/plain",
                creator   : $user_node
            ),
            actor: $user_node
        );


        // handle name
        $user_node->addProperty(
            key  : "name",
            node : GraphDatabaseConnection::new_node(
                data      : $userDetails["FullName"],
                media_type: "text/plain",
                creator   : $user_node
            ),
            actor: $user_node
        );
        // Create this as default the user's first name - they can change it later if they want
        $user_node->addProperty(
            key  : "display_name",
            node : GraphDatabaseConnection::new_node(
                data      : explode(" ", $userDetails["FullName"])[0],
                media_type: "text/plain",
                creator   : $user_node
            ),
            actor: $user_node
        );


        // handle extended properties
        foreach($userDetails['ExtendedProperties'] as $subData)
        {
            if(in_array(needle: $subData['ObjectSchema'], haystack: [
                "TEXT",
                "TEXT_ISO_DATE",
            ],          strict: true
            ))
            {
                $user_node->addProperty(
                    key  : strtolower($subData['DataType']),
                    node : GraphDatabaseConnection::new_node(
                        data      : $subData['DataAccess'],
                        media_type: $subData['MIMEType'],
                        creator   : $user_node
                    ),
                    actor: $user_node
                );
            }
        }

        return $user_node;
    }

    /**
     * @throws InvalidUUIDFormatException
     * @throws Exception
     */
    public static function CreateCase($db, $caseDetails): DeegraphNode
    {
        // create the case node
        $caseNode = GraphDatabaseConnection::new_node(
            schema : URLHandling::GetURLForSchema(CaseSchema::class),
            creator: User::get_system_node()
        );
        $caseNode = new DeegraphNode($caseNode->getId());


        // case title
        $caseNode->addProperty(
            key  : "title",
            node : GraphDatabaseConnection::new_node(
                data      : $caseDetails["CaseTitle"],
                media_type: "text/plain",
                creator   : User::get_system_node()
            ),
            actor: User::get_system_node(),
        );


        // case description
        foreach($caseDetails['ExtendedProperties'] as $dataType=>$subData)
        {
            if($dataType === "CASE_DESCRIPTION")
            {
                $tempNode = GraphDatabaseConnection::new_node(
                    data      : $subData['DataAccess'],
                    media_type: $subData['MIMEType'],
                    creator   : User::get_system_node()
                );
                $caseNode->addProperty(
                    key  : "description",
                    node : $tempNode,
                    actor: User::get_system_node(),
                );
            }
        }


        // Create todos collection
        $node_todos = GraphDatabaseConnection::new_node(
            schema : URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key  : "todos",
            node : $node_todos,
            actor: User::get_system_node()
        );
        // Create workers collection
        $node_workers = GraphDatabaseConnection::new_node(
            schema : URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key  : "workers",
            node : $node_workers,
            actor: User::get_system_node()
        );
        // Create documents collection
        $node_documents = GraphDatabaseConnection::new_node(
            schema : URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key  : "documents",
            node : $node_documents,
            actor: User::get_system_node()
        );
        // Create messages collection
        $node_messages = GraphDatabaseConnection::new_node(
            schema : URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key  : "messages",
            node : $node_messages,
            actor: User::get_system_node()
        );
        // Create timeline collection
        $node_timeline = GraphDatabaseConnection::new_node(
            schema : URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key  : "timeline",
            node : $node_timeline,
            actor: User::get_system_node()
        );
        // Create clients collection
        $node_clients = GraphDatabaseConnection::new_node(
            schema : URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key  : "clients",
            node : $node_clients,
            actor: User::get_system_node()
        );


        // handle extended properties
        foreach($caseDetails['ExtendedProperties'] as $dataType=>$subData)
        {
            if(
                    in_array(needle: $subData['ObjectSchema'],  haystack: ["TEXT", "TEXT_ISO_DATE"],        strict: true)
                && !in_array(needle: $dataType,                 haystack: ["TITLE", "CASE_DESCRIPTION"],    strict: true)
            )
            {
                $tempNode = GraphDatabaseConnection::new_node(
                    data      : $subData['DataAccess'],
                    media_type: $subData['MIMEType'],
                    creator   : User::get_system_node()
                );
                $caseNode->addProperty(
                    key  : strtolower($dataType),
                    node : $tempNode,
                    actor: User::get_system_node(),
                );
            }
        }
        foreach($caseDetails['ToDos'] as $subData)
        {
            if($subData['ObjectSchema'] === "TODO_JSON_V1")
            {
                $node_todos->addProperty(
                    key  : '#',
                    node : GraphDatabaseConnection::new_node(
                        data   : self::processToDoJSON($subData['DataAccess']),
                        media_type: "text/calendar",
                        creator: User::get_system_node()
                    ),
                    actor: User::get_system_node(),
                );
            }
        }
        foreach($caseDetails['ExtendedProperties'] as $dataType=>$subData)
        {
            if($subData['ObjectSchema'] === "TIMELINE_NOTE_JSON_V1")
            {
                $node_timeline->addProperty(
                    key  : '#',
                    node : GraphDatabaseConnection::new_node(
                        data   : self::processToDoJSON($subData['DataAccess']),
                        media_type: "text/calendar",
                        creator: User::get_system_node()
                    ),
                    actor: User::get_system_node(),
                );
            }
        }


        return $caseNode;
    }





    private static function iCALSanitise($text): array|string
    {
        return str_replace(
            ["\\", "\r", "\n", ",", ";"],
            ["\\\\", "\\r", "\\n", "\\,", "\\;"],
            $text
        );
    }

    private static function iCALWrap($text): array|string
    {
        $text = str_replace(["\r", "\n"], "", $text);

        if (strlen($text) > 60)
        {
            $chunks = [];
            $textLength = strlen($text);

            for ($i = 1; $i < $textLength; $i += 75)
            {
                if ($i === 1)
                {
                    $chunks[] = substr($text, 0, 76);
                }
                else
                {
                    $chunks[] = " " . substr($text, $i, 75);
                }
            }
            $text = implode("\r\n", $chunks);
        }

        return $text;
    }

    private static function iCALRandomUID(): string
    {
        $output = "";
        $characters = "ABCDEFGHIJKLMNOPQRSTUVWXYZ234567";
        $charactersLength = strlen($characters);
        for ($i = 0; $i < 48; $i++)
        {
            $output .= $characters[random_int(0, $charactersLength - 1)];
        }
        return $output;
    }

    private static function processToDoJSON(string $input): string
    {
        $input = json_decode($input, true, 512, JSON_THROW_ON_ERROR);


        $id = self::iCALRandomUID();
        $title = $input["title"];
        $timestamp = $input["timestamp"];
        $description = $input["description"];

        $summary = self::iCALWrap("SUMMARY:" . self::iCALSanitise($title . "\n" . $description));

        $temp = "BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VTODO
UID:$id
DTSTAMP:$timestamp
$summary
END:VTODO
END:VCALENDAR
";
        // error_log($temp);
        return $temp;
    }

}
