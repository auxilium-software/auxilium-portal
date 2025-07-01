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

class ExampleData
{
    private static function CreateUser($db, $user): User
    {
        $pre_hashed_password = base64_encode(hash("sha256", $user["Password"], true));
        $hash_options = [
            "cost" => 12,
        ];
        $hashed_password = password_hash($pre_hashed_password, PASSWORD_BCRYPT, $hash_options);

        $user_node = GraphDatabaseConnection::new_node(
            null,
            null,
            URLHandling::GetURLForSchema(UserSchema::class),
            User::get_system_node()
        );
        $user_node = new User($user_node->getId());

        $db->RunInsert(
            queryBuilder: SQLQueryBuilderWrapper::INSERT(MariaDBTable::STANDARD_LOGINS)
                ->set(col: 'email_address', value: ':__email_address__')
                ->set(col: 'user_uuid', value: ':__user_uuid__')
                ->set(col: 'password', value: ':__password__')
                ->bindValue(name: '__email_address__', value: $user["EmailAddress"])
                ->bindValue(name: '__user_uuid__', value: $user_node->getId())
                ->bindValue(name: '__password__', value: $hashed_password)
        );

        $email_prop = GraphDatabaseConnection::new_node($user["EmailAddress"], "text/plain", null, User::get_system_node());
        $user_node->addProperty("contact_email", $email_prop, User::get_system_node()); // Do all of this as the system node, since userIDs shouldn't just be able to randomly change their email address

        $language_prop = GraphDatabaseConnection::new_node(
            data      : strtoupper(PageBuilder2::GetVariable("lang", "en")),
            media_type: "text/plain",
            creator   : $user_node
        );
        $user_node->addProperty(
            key  : "preferred_language",
            node : $language_prop,
            actor: $user_node
        ); // Set it to whatever the language is currently in
        $full_name_prop = GraphDatabaseConnection::new_node(
            data      : $user["Name"],
            media_type: "text/plain",
            creator   : $user_node
        );
        $user_node->addProperty(
            key  : "name",
            node : $full_name_prop,
            actor: $user_node
        );
        $name_prop = GraphDatabaseConnection::new_node(
            data      : explode(" ", $user["Name"])[0],
            media_type: "text/plain",
            schema    : null,
            creator   : $user_node
        );
        $user_node->addProperty(
            key  : "display_name",
            node : $name_prop,
            actor: $user_node
        ); // Create this as default the user's first name - they can change it later if they want

        return $user_node;
    }


    /**
     * @throws InvalidUUIDFormatException
     * @throws \Exception
     */
    private static function CreateCase($case): DeegraphNode
    {
        // create the case node
        $caseNode = GraphDatabaseConnection::new_node(
            schema: URLHandling::GetURLForSchema(CaseSchema::class),
            creator: User::get_system_node()
        );
        $caseNode = new DeegraphNode($caseNode->getId());


        
        // Add title property
        $titleNode = GraphDatabaseConnection::new_node(
            data: $case["Title"],
            media_type: "text/plain",
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key: "title",
            node: $titleNode,
            actor: User::get_system_node()
        );



        // Add description property
        $descriptionNode = GraphDatabaseConnection::new_node(
            data: $case["Description"],
            media_type: "text/plain",
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key: "description",
            node: $descriptionNode,
            actor: User::get_system_node()
        );



        // Create todos collection
        $node_todos = GraphDatabaseConnection::new_node(
            schema: URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key: "todos",
            node: $node_todos,
            actor: User::get_system_node()
        );



        // Create workers collection
        $node_workers = GraphDatabaseConnection::new_node(
            schema: URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key: "workers",
            node: $node_workers,
            actor: User::get_system_node()
        );



        // Create documents collection
        $node_documents = GraphDatabaseConnection::new_node(
            schema: URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key: "documents",
            node: $node_documents,
            actor: User::get_system_node()
        );



        // Create messages collection
        $node_messages = GraphDatabaseConnection::new_node(
            schema: URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key: "messages",
            node: $node_messages,
            actor: User::get_system_node()
        );



        // Create timeline collection
        $node_timeline = GraphDatabaseConnection::new_node(
            schema: URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key: "timeline",
            node: $node_timeline,
            actor: User::get_system_node()
        );



        // Create clients collection
        $node_clients = GraphDatabaseConnection::new_node(
            schema: URLHandling::GetURLForSchema(CollectionSchema::class),
            creator: User::get_system_node()
        );
        $caseNode->addProperty(
            key: "clients",
            node: $node_clients,
            actor: User::get_system_node()
        );

        return $caseNode;
    }



    /**
     * Helper method to ensure a user has a cases collection and link a case to it
     */
    private static function linkCaseToUser(DeegraphNode $caseNode, User $user): void
    {
        $userCasesCollection = $user->getProperty('cases');
        if($userCasesCollection === null)
        {
            $userCasesCollection = GraphDatabaseConnection::new_node(
                schema: URLHandling::GetURLForSchema(CollectionSchema::class),
                creator: User::get_system_node()
            );
            $user->addProperty(
                key: "cases",
                node: $userCasesCollection,
                actor: User::get_system_node()
            );
        }

        $userCasesCollection->addProperty(
            key: "#",
            node: $caseNode,
            actor: User::get_system_node()
        );
    }



    /**
     * @throws InvalidUUIDFormatException
     * @throws \JsonException
     */
    public static function WriteExampleData(): void
    {
        $exampleData = file_get_contents(__DIR__ . "/../../example-data.json");
        $exampleData = json_decode($exampleData, true, 512, JSON_THROW_ON_ERROR);

        $db = new MariaDBServerConnection();

        $allUserIDs = [];

        foreach($exampleData['Users'] as $user)
        {
            $allUserIDs[$user['Name']] = self::CreateUser($db, $user)->getId();
        }


        foreach($exampleData['Cases'] as $case)
        {
            $caseNode = self::CreateCase($case);

            $caseNode_caseWorkers = $caseNode->getProperty(property: 'workers');
            $caseNode_beneficiaries = $caseNode->getProperty(property: 'clients');

            for ($i = 0, $iMax = count($case['CaseWorkers']); $i < $iMax; $i++)
            {
                $caseWorker = new User($allUserIDs[$case['CaseWorkers'][$i]]);
                $caseNode_caseWorkers->addProperty(
                    key:    $i,
                    node:   $caseWorker,
                    actor:  User::get_system_node(),
                );
                self::linkCaseToUser($caseNode, $caseWorker);
            }

            for ($i = 0, $iMax = count($case['Beneficiaries']); $i < $iMax; $i++)
            {
                $client = new User($allUserIDs[$case['Beneficiaries'][$i]]);
                $caseNode_beneficiaries->addProperty(
                    key:    $i,
                    node:   $client,
                    actor:  User::get_system_node(),
                );
                self::linkCaseToUser($caseNode, $client);
            }
        }
    }
}
