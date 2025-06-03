<?php

use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\Enumerators\CookieKey;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\SessionHandling\CookieHandling;
use Auxilium\TwigHandling\PageBuilder2;
use Auxilium\Utilities\Security;
use Auxilium\Wrappers\ICMPWrapper;

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

try
{
    try
    {
        Security::RequireLogin();
        ICMPWrapper::RequireSchemaRepo();

        $userIDs = GraphDatabaseConnection::query(actor: null, query: 'SELECT @id FROM ** INSTANCEOF "https://schemas.auxiliumsoftware.co.uk/v1/user.json"');
        $caseIDs = GraphDatabaseConnection::query(actor: null, query: 'SELECT @id FROM ** INSTANCEOF "https://schemas.auxiliumsoftware.co.uk/v1/case.json"');

        $users = [];
        $cases = [];

        $links = [];


        foreach($userIDs['@rows'] as $userID)
        {
            $userID = $userID['@id'][array_key_first($userID['@id'])];
            $userData = GraphDatabaseConnection::query(actor: null, query: "SELECT display_name FROM {$userID}")['@rows'];

            if($userData[0]['display_name'] !== null)
            {
                $displayName = $userData[0]['display_name'][array_key_first($userData[0]['display_name'])];
            }

            $users[$userID] = [
                "ID" => $userID,
                "DisplayName" => $displayName??"???",
            ];
        }

        foreach($caseIDs['@rows'] as $caseID)
        {
            $caseID = $caseID['@id'][array_key_first($caseID['@id'])];
            $caseData = GraphDatabaseConnection::query(actor: null, query: "SELECT title FROM {$caseID}")['@rows'];

            if($caseData[0]['title'] !== null)
            {
                $title = $caseData[0]['title'][array_key_first($caseData[0]['title'])];
            }

            $cases[$caseID] = [
                "ID" => $caseID,
                "Title" => $title ?? "???",
            ];
        }

        foreach($cases as $caseID=>$caseDetails)
        {
            $workersRaw = GraphDatabaseConnection::query(actor: null, query: "SELECT @id FROM $caseID/workers/#")['@rows'];
            $clientsRaw = GraphDatabaseConnection::query(actor: null, query: "SELECT @id FROM $caseID/clients/#")['@rows'];

            foreach($workersRaw as $temp1)
            {
                foreach($temp1 as $temp2)
                {
                    $id = $temp2[array_key_first($temp2)];

                    $links[] = [
                        "From"=>$caseID,
                        "To"=>$id,
                        "Label"=>"Case Worker",
                    ];
                }
            }
            foreach($clientsRaw as $temp1)
            {
                foreach($temp1 as $temp2)
                {
                    $id = $temp2[array_key_first($temp2)];

                    $links[] = [
                        "From"=>$caseID,
                        "To"=>$id,
                        "Label"=>"Beneficiary",
                    ];
                }
            }


        }




        PageBuilder2::AutoRender(variables: [
            "all_users"=>$users,
            "all_cases"=>$cases,
            "links"=>$links,
            "progressive_load" => CookieHandling::GetBooleanCookie(CookieKey::PROGRESSIVE_LOAD, false),
            "is_admin" => Security::IsAdmin(),
        ]);
    }
    catch(DatabaseConnectionException $e)
    {
        PageBuilder2::RenderInternalSystemError($e);
    }
}
catch(Exception $e)
{
    PageBuilder2::RenderInternalSystemError($e);
}
