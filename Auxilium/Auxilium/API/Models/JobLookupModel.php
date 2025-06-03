<?php

namespace Auxilium\API\Models;

use Auxilium\API\Enumerators\JobStatus;
use Auxilium\API\Superclasses\APIModel;
use OpenApi\Attributes\Property;
use OpenApi\Attributes\Schema;

#[Schema(
    schema     : "JobLookupModel",
    title      : "JobLookup",
    description: "Stores information about a Job in Queue.",
    required   : [
        "Status",
        "ResponseCode",
    ],
)]
class JobLookupModel extends APIModel
{


    #[Property(
        property   : "JobID",
        description: "The unique identifier for the Job.",
        type       : "string",
        nullable   : false,
    )]
    public mixed $JobID = null;
    public ?JobStatus $JobStatus = null;
    public mixed $Content = null;
    public ?string $Note = null;


    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            // "status"        => $this->Status->value,
            "error_message" => $this->ErrorText,

            "job_id" => $this->JobID,
            "status" => $this->JobStatus?->value,
            "content" => $this->Content,
            "note" => $this->Note,
        ];
    }
}
