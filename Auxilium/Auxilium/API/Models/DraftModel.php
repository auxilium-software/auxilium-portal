<?php

namespace Auxilium\API\Models;

use Auxilium\API\Superclasses\APIModel;
use OpenApi\Attributes\Schema;

#[Schema(
    schema     : "DraftModel",
    title      : "Draft",
    description: "Stores information about an email draft.",
    required   : [
        "Status",
        "ResponseCode",
    ],
)]
class DraftModel extends APIModel
{


    public mixed $DraftID = null;
    public ?int $BytesWritten = null;
    public ?array $Content = null;
    public ?string $JobReference = null;
    public ?array $AttachFailures = null;
    public ?array $AttachedTo = null;
    public ?string $MessageNodeID = null;


    public function ToAssocArray(): array
    {
        return [
            "response_code" => $this->ResponseCode,
            "status" => $this->Status->value,
            "error_message" => $this->ErrorText,

            "draft_id" => $this->DraftID,
            "bytes_written" => $this->BytesWritten,
            "content" => $this->Content,
            "job_reference" => $this->JobReference,
            "attach_failures" => $this->AttachFailures,
            "attached_to" => $this->AttachedTo,
            "message_node_id" => $this->MessageNodeID,
        ];
    }
}