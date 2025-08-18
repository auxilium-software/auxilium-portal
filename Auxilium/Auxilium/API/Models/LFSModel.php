<?php

namespace Auxilium\API\Models;

use Auxilium\API\Superclasses\APIModel;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema     : "LFSModel",
    title      : "LFS",
    description: "Stores information about a Job in Queue.",
    required   : [
        "Status",
        "ResponseCode",
    ],
)]
class LFSModel extends APIModel
{
    #[Property(
        property   : "id",
        description: "",
        type       : "string",
        nullable   : false,
    )]
    public mixed $ID = null;
    #[Property(
        property   : "hash",
        description: "",
        type       : "string",
        nullable   : false,
    )]
    public mixed $Hash = null;
    #[Property(
        property   : "size",
        description: "",
        type       : "int",
        nullable   : false,
    )]
    public ?int $Size = null;




    #[Property(
        property   : "id",
        description: "",
        type       : "string",
        nullable   : false,
    )]
    public mixed $FileID = null;
    #[Property(
        property   : "file_hash",
        description: "",
        type       : "string",
        nullable   : false,
    )]
    public mixed $FileHash = null;




    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            "status"        => $this->Status->value,
            "error_message" => $this->ErrorText,

            "id" => $this->ID,
            "hash" => $this->Hash,
            "size" => $this->Size,

            "file_id" => $this->FileID,
            "file_hash" => $this->FileHash,
        ];
    }
}
