<?php

namespace Auxilium\API\Controllers;

use Auxilium\API\Models\IndexModel;
use Auxilium\API\Superclasses\APIController;
use Auxilium\API\Superclasses\APIModel;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\SessionHandling\Session;
use Darksparrow\DeegraphInteractions\DataStructures\DataURL;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;
use RuntimeException;

class IndexController extends APIController
{

    public function __construct()
    {
        parent::__construct();
    }

    #[NoReturn]
    #[Get(
        path       : "/api/v2/indexes",
        operationId: "[GET]/api/v2/indexes",
        description: "This API endpoint will get the index for the user, if one doesn't exist, it'll create one, and return that.",
        summary    : "Index generation",
        tags       : [
            "Index Generation",
        ],
        responses  : [
            new Response(
                response   : 200,
                description: "",
                content    : new JsonContent(
                    ref: "#/components/schemas/IndexModel"
                )
            )
        ],
        deprecated : false,
    )]
    public function Get(): void
    {
        $this->Model = new IndexModel();


        $index_id = $this->URIUtilities->getURIComponents()[count($this->URIUtilities->getURIComponents()) - 1];
        $index_id = explode(".", $index_id)[0];

        if(!preg_match("/^[0-9a-z_-]+$/", $index_id))
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Malformed index name";
            $this->Render();
        }


        $regenerate_index = false;
        $index_list = json_decode(file_get_contents(__DIR__ . "/../../../indexes.json"), true, 512, JSON_THROW_ON_ERROR);

        if(!array_key_exists($index_id, $index_list))
        {
            $index_id = "global";
            if(!array_key_exists("global", $index_list))
            {
                $this->Model = new APIModel();
                $this->Model->ErrorText = "Broken indexes.json file. Contact system administrator.";
                $this->Render();
            }
        }

        $index_store_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Indexes/" . Session::get_current()->getUser()->getId() . "/" . $index_id . ".json";
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Indexes/" . Session::get_current()->getUser()->getId() . "/"))
        {
            if(!mkdir($concurrentDirectory = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Indexes/" . Session::get_current()->getUser()->getId() . "/", 0700, true) && !is_dir($concurrentDirectory))
            {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        $old_index = ["created" => "1970-01-01T00:00:00Z"];
        if(file_exists($index_store_path))
        {
            $old_index = json_decode(file_get_contents($index_store_path), true);
        }
        else
        {
            $regenerate_index = true;
        }
        $new_index = ["created" => date("c", time())];

        $max_age = 3600;
        if(array_key_exists("max_age", $index_list[$index_id]))
        {
            $max_age = $index_list[$index_id]["max_age"];
        }
        $index_age = time() - strtotime($old_index["created"]);
        $this->Model->Age = $index_age;
        $this->Model->MaxAge = $max_age;
        if((time() - strtotime($old_index["created"])) > $max_age)
        {
            $regenerate_index = true;
            $this->Model->Age = 0;
        }

        if($regenerate_index)
        {
            $queries = $index_list[$index_id]["index_queries"];
            $new_index["lookup_table"] = [];

            foreach($queries as &$query)
            {
                $results = GraphDatabaseConnection::query(Session::get_current()->getUser(), $query)["@rows"];
                foreach($results as &$row)
                {
                    foreach($row as $column_name => &$cell)
                    {
                        foreach($cell as $path => $value)
                        {
                            $value = mb_strtolower((new DataURL($value))->getData());
                            if(!array_key_exists($value, $new_index["lookup_table"]))
                            {
                                $new_index["lookup_table"][$value] = [];
                            }
                            array_push($new_index["lookup_table"][$value], $path);
                        }
                    }

                }
            }

            file_put_contents($index_store_path, json_encode($new_index, JSON_PRETTY_PRINT));
        }
        else
        {
            $new_index = $old_index;
        }

        $this->Model->Index = $new_index;
        $this->Render();
    }
}