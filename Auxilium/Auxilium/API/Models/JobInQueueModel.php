<?php

namespace Auxilium\API\Models;

use Auxilium\API\Superclasses\APIModel;
use OpenApi\Attributes\Schema;

#[Schema(
    schema     : "JobInQueueModel",
    title      : "JobInQueue",
    description: "Stores information about a Job in Queue.",
    required   : [
        "Status",
        "ResponseCode",
    ],
)]
class JobInQueueModel extends APIModel
{


    public ?int $CompletedJobs = null;
    public ?int $AttemptedJobs = null;
    public ?int $RemainingJobs = null;
    public ?float $ElapsedTimeUS = null;
    public ?float $ExecTimeLimitUS = null;


    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            "status" => $this->Status->value,
            "error_message" => $this->ErrorText,

            "completed_jobs" => $this->CompletedJobs,
            "attempted_jobs" => $this->AttemptedJobs,
            "remaining_jobs" => $this->RemainingJobs,
            "elapsed_time_us" => $this->ElapsedTimeUS,
            "exec_time_limit_us" => $this->ExecTimeLimitUS,
        ];
    }
}
