<?php

namespace Auxilium\API\Models;

use Auxilium\API\Superclasses\APIModel;
use OpenApi\Attributes\Schema;

#[Schema(
    schema     : "JobStatsModel",
    title      : "JobStats",
    description: "Stores information about a Job in Queue.",
    required   : [
        "Status",
        "ResponseCode",
    ],
)]
class JobStatsModel extends APIModel
{


    public array $Jobs;


    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            "status" => $this->Status->value,
            "error_message" => $this->ErrorText,

            "jobs" => $this->Jobs,
        ];
    }
}
