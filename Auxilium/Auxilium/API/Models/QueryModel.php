<?php

namespace Auxilium\API\Models;

use Auxilium\API\Superclasses\APIModel;
use OpenApi\Attributes\Schema;

#[Schema(
    schema     : "QueryModel",
    title      : "Query",
    description: "Stores information about a Deegraph Query.",
    required   : [
        "Status",
        "ResponseCode",
    ],
)]
class QueryModel extends APIModel
{


    public mixed $Result = null;
    public mixed $Query = null;


    public ?array $Results = null;
    public ?array $Queries = null;


    public mixed $ResultSlice = null;
    public float|int|null $StartIndex = null;
    public ?int $Page = null;


    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            "status" => $this->Status->value,
            "error_message" => $this->ErrorText,

            "result" => $this->Result,
            "query" => $this->Query,

            "results" => $this->Results,
            "queries" => $this->Queries,

            "result_slice" => $this->ResultSlice,
            "start_index" => $this->StartIndex,
            "page" => $this->Page,
        ];
    }
}
