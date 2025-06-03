<?php

namespace Auxilium\API\Controllers;

use Auxilium\API\Models\NodeModel;
use Auxilium\API\Superclasses\APIController;
use Auxilium\API\Superclasses\APIModel;
use Auxilium\DatabaseInteractions\GraphDatabaseConnection;
use Auxilium\SessionHandling\Session;
use Darksparrow\DeegraphInteractions\DataStructures\UUID;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;

class NodeController extends APIController
{

    public function __construct()
    {
        parent::__construct();
    }


    function debug_to_console($data)
    {
        $output = $data;
        if(is_array($output))
            $output = implode(',', $output);

        echo "<script>console.log('" . $output . "');</script>";
    }


    #[NoReturn]
    #[Get(
        path       : "/api/v2/nodes",
        operationId: "[GET]/api/v2/nodes",
        description: "Gets a Deegraph node.",
        summary    : "Renders a PDF",
        tags       : [
            "Deegraph",
        ],
        responses  : [
            new Response(
                response   : 200,
                description: "",
                content    : new JsonContent(
                    ref: "#/components/schemas/NodeModel"
                )
            ),
            new Response(
                response   : 400,
                description: "",
                content    : new JsonContent(
                    ref: "#/components/schemas/GenericModel"
                )
            )
        ],
        deprecated : false,
    )]
    public function Get()
    {
        $this->Model = new NodeModel();


        $uri_components = explode("/", $_SERVER["REQUEST_URI"]);
        $last_uri_component = explode("?", end($uri_components));
        $get_params = "";
        if(count($last_uri_component) > 1)
        {
            $get_params = $last_uri_component[1];
        }
        $uri_components[count($uri_components) - 1] = $last_uri_component[0];

        $node_id = $uri_components[4];

        if(!preg_match("/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/", $node_id))
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Formatting error";
            $this->Model->ResponseCode = 400;
            $this->Render();
        }

        switch($_SERVER['REQUEST_METHOD'])
        {
            case "DELETE":
                $query = "DELETE {" . $node_id . "}";
                GraphDatabaseConnection::query(Session::get_current()->getUser(), $query);
                $this->Render();
                break;
            case "GET":
            default:
                $node_info = GraphDatabaseConnection::get_raw_node_info(
                    actor: Session::get_current()->getUser(),
                    uuid : new UUID($node_id),
                );

                $this->Model->Result = $node_info;
                $this->Model->Request = $node_id;

                $this->Render();
                break;
        }


    }
}