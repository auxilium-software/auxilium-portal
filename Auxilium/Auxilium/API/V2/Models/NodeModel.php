<?php

namespace Auxilium\API\V2\Models;

use Auxilium\API\V2\Superclasses\APIModel;
use OpenApi\Attributes\Schema;

#[Schema(
    schema     : "NodeModel",
    title      : "Node",
    description: "Stores information about a Deegraph Node.",
    required   : [
        "Status",
        "ResponseCode",
    ],
)]
class NodeModel extends APIModel
{


    public mixed $Result = null;
    public mixed $Request = null;


    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            "status" => $this->Status->value,
            "error_message" => $this->ErrorText,

            "result" => $this->Result,
            "request" => $this->Request,
        ];
    }
}
