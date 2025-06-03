<?php

namespace Auxilium\API\Superclasses;

use Auxilium\API\Enumerators\APIResponseStatus;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema: "GenericModel",
    title: "Generic",
    description: "Stores the most basic information.",
    required: [
        "Status",
        "ResponseCode",
    ],
)]
class APIModel
{
    #[Property(
        property: "Status",
        description: "The status of the API request.",
        type: "string",
        nullable: false,
    )]
    public APIResponseStatus $Status;
    #[Property(
        property: "ResponseCode",
        description: "The HTTP Status code.",
        type: "int",
        nullable: true,
    )]
    public int $ResponseCode;
    #[Property(
        property: "ErrorText",
        description: "If an error occurs, the message will be placed here.",
        type: "string",
        nullable: true,
    )]
    public ?string $ErrorText = null;



    public function ToAssocArray(): array
    {
        return [
            "response_code"     => $this->ResponseCode,
            "status"            => $this->Status->value,
            "error_message"     => $this->ErrorText,
        ];
    }
}
