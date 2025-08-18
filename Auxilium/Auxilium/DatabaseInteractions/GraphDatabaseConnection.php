<?php

namespace Auxilium\DatabaseInteractions;

use Auxilium\DatabaseInteractions\Deegraph\DeegraphNode;
use Auxilium\DatabaseInteractions\Deegraph\DeegraphServerConnection;
use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\Exceptions\DatabaseConnectionException;
use Auxilium\Exceptions\DeegraphException;
use Auxilium\Schemas\CaseSchema;
use Auxilium\Schemas\DocumentSchema;
use Auxilium\Schemas\MessageSchema;
use Auxilium\Schemas\OrganisationSchema;
use Auxilium\Schemas\UserSchema;
use Auxilium\SessionHandling\Session;
use Auxilium\Utilities\Security;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;

require_once CREDENTIALS_FILE_LOCATION;

class GraphDatabaseConnection
{
    public static function get_instance_node()
    {
        return new DeegraphNode(INSTANCE_UUID);
    }

    public static function query(?User $actor, string $query, array $fvars = [])
    {
        if($actor === null)
        {
            $actor = Session::get_current()?->getUser();
        }

        $lookup_table = [];

        $fvars = [
            "passed" => $fvars
        ];
        $fvars["static"] = [
            "case" => URLHandling::GetURLForSchema(CaseSchema::class),
            "user" => URLHandling::GetURLForSchema(UserSchema::class),
            "organisation" => URLHandling::GetURLForSchema(OrganisationSchema::class),
            "document" => URLHandling::GetURLForSchema(DocumentSchema::class),
            "message" => URLHandling::GetURLForSchema(MessageSchema::class),
        ];

        foreach($fvars as $var_type => &$vars)
        {
            foreach($vars as $key => &$var)
            {
                $var_id = null;
                while($var_id === null)
                {
                    $var_id = bin2hex(Security::GeneratePseudoRandomBytes(length: 32));
                    if(strpos($query, $var_id) !== false)
                    {
                        $var_id = null;
                    }
                }
                $var_id = "###" . $var_id . "###";
                $query = str_replace("\$" . $key, $var_id, $query);
                switch($var_type)
                {
                    case "static":
                        $lookup_table[$var_id] = $var;
                        break;
                    default:
                        $lookup_table[$var_id] = $var;
                        break;
                }
            }
        }

        $query_buffer = "";
        $data_buffer = "";
        $backtick_switch = false;
        $escape_char = false;

        foreach(mb_str_split($query) as $char)
        {
            if($char === "`")
            {
                if($escape_char)
                {
                    $escape_char = false;
                    $query_buffer = $query_buffer . "`";
                }
                else
                {
                    if($backtick_switch)
                    {
                        $backtick_switch = false;
                        $query_buffer = $query_buffer . "\"data:text/plain," . urlencode($data_buffer) . "\"";
                    }
                    else
                    {
                        $backtick_switch = true;
                        $data_buffer = "";
                    }
                }
            }
            elseif($char === "\\")
            {
                $escape_char = true;
            }
            else
            {
                if($escape_char)
                {
                    $escape_char = false;
                    if($backtick_switch)
                    {
                        $data_buffer .= "\\";
                    }
                    else
                    {
                        $query_buffer .= "\\";
                    }
                }
                if($backtick_switch)
                {
                    $data_buffer .= $char;
                }
                else
                {
                    $query_buffer .= $char;
                }
            }
        }

        $query = $query_buffer;

        foreach($lookup_table as $key => &$var)
        {
            $query = str_replace(
                search : $key,
                replace: $var,
                subject: $query
            );
        }

        //echo $query;
        $ret_val = GraphDatabaseConnection::raw_request($actor, "/api/v1/@query", "POST", $query);
        if(is_array($ret_val))
        {
            $ret_val["@generated_query"] = $query;
        }
        return $ret_val;
    }

    public static function raw_request(?User $actor, string $path, string $method = "GET", string $body_data = null)
    {
        if($actor === null)
        {
            $actor = Session::get_current()?->getUser();
        }

        $method = strtoupper($method);
        $cmps = explode("/", $path);
        foreach($cmps as &$cmp)
        {
            $cmp = urlencode($cmp);
        }
        $url = "https://" . INSTANCE_CREDENTIAL_DDS_HOST . implode("/", $cmps);
        $curl_handle = curl_init();
        curl_setopt($curl_handle, CURLOPT_URL, $url);
        if($method === "POST")
        {
            curl_setopt($curl_handle, CURLOPT_POST, 1);
        }
        curl_setopt($curl_handle, CURLOPT_PORT, INSTANCE_CREDENTIAL_DDS_PORT);

        if(ACCEPT_SELF_SIGNED_CERTIFICATES)
        {
            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curl_handle, CURLOPT_SSL_VERIFYHOST, false);
        }

        if($actor === null)
        {
            throw new DatabaseConnectionException("Client's user account is invalid");
        }
        curl_setopt($curl_handle, CURLOPT_HTTPHEADER, [
                "Content-Type: text/plain",
                "Authorization: Bearer " . INSTANCE_CREDENTIAL_DDS_TOKEN,
                "X-Auxilium-Actor: " . $actor->getId()
            ]
        );
        if($method === "POST" || $method === "PUT")
        {
            if($body_data !== null)
            {
                curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $body_data);
            }
        }
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        $server_output = curl_exec($curl_handle); // Send the message

        if($server_output === false)
        {
            throw new DatabaseConnectionException("Deegraph server did not respond to Auxilium query");
        }

        if(str_starts_with($server_output, "{"))
        {
            $server_output = json_decode($server_output, true, 512, JSON_THROW_ON_ERROR);
        }

        if(curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE) >= 500 && curl_getinfo($curl_handle, CURLINFO_RESPONSE_CODE) < 600)
        {
            throw new DeegraphException("Deegraph server responded with an internal error code", 0, null, isset($server_output["@trace"]) ? $server_output["@trace"] : null);
            //throw new DatabaseConnectionException("Client's user account is invalid");
        }

        curl_close($curl_handle);

        return $server_output;
    }

    public static function node_from_path(string $path, User $actor = null): ?DeegraphNode
    {
        if($actor === null)
        {
            $actor = Session::get_current()?->getUser();
        }

        $path_cmps = explode("/", $path);
        foreach($path_cmps as &$path_cmp)
        {
            if(str_starts_with($path_cmp, "~"))
            {
                $path_cmp = "{" . substr($path_cmp, 1) . "}";
            }
        }
        $path = implode("/", $path_cmps);

        $result = GraphDatabaseConnection::raw_request($actor, "/api/v1/@resolve_path", "POST", $path);
        if(is_array($result))
        {
            if(isset($result["@id"]))
            {
                return DeegraphNode::from_id($result["@id"]);
            }
        }
        return null;
    }

    public static function get_raw_node_info(?User $actor, UUID $uuid): array
    {
        if($actor === null)
        {
            $actor = Session::get_current()?->getUser();
        }

        $rawNode = DeegraphServerConnection::GetConnection()->GetRawNode(
            actorID: new UUID($actor->getId()),
            nodeID : $uuid,
        );
        return json_decode($rawNode->AsJSON(), true, 512, JSON_THROW_ON_ERROR);

        /*
        if (!preg_match("/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/", $uuid)) {
            throw new DeegraphException("Invalid UUID", 0, null, null);
        }

        $server_response = GraphDatabaseConnection::raw_request($actor, "/api/v1/{" . $uuid . "}", "GET");

        if (is_array($server_response)) {
            return $server_response;
        }
        */
    }

    public static function new_node($data = null, string $media_type = null, string $schema = null, User $creator = null): ?DeegraphNode
    {
        $data_url = null;
        if($data !== null && $media_type !== null)
        {
            $media_type = mb_strtolower($media_type);
            $data_url = match ($media_type)
            {
                "text/plain",
                "application/json"  => "data:" . $media_type . "," . urlencode($data),
                default             => "data:" . $media_type . ";base64," . base64_encode($data),
            };
        }
        return GraphDatabaseConnection::new_node_raw($data_url, $schema, $creator);
    }

    public static function new_node_raw(string $data_url = null, string $schema = null, User $creator = null)
    {
        $actor = Session::get_current()?->getUser();

        if($creator === null)
        {
            $creator = Session::get_current()?->getUser();
        }

        /*
        $temp = DeegraphServerConnection::GetConnection()->CreateNewNode(
            actorID: new UUID($creator),
            dataURL: $data_url,
            schema : $schema,
            creator: null,
        );
        return new DeegraphNode($temp->ID);
        */

        $body = [
            "@data" => $data_url,
        ];

        if($schema !== null)
        {
            $body["@schema"] = $schema;
        }

        $server_response = GraphDatabaseConnection::raw_request(
            actor    : $creator,
            path     : "/api/v1/@new",
            method   : "PUT",
            body_data: json_encode($body, JSON_THROW_ON_ERROR)
        );

        if(is_array($server_response))
        {
            return new DeegraphNode($server_response["@id"]);
        }
    }
}
