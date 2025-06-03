<?php

namespace Auxilium\API\Controllers;

use Auxilium\API\Enumerators\JobStatus;
use Auxilium\API\Models\JobLookupModel;
use Auxilium\API\Superclasses\APIController;
use Auxilium\API\Superclasses\APIModel;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;

class JobLookupController extends APIController
{

    public function __construct()
    {
        parent::__construct();
    }

    #[NoReturn]
    #[Get(
        path       : "/api/v2/jobs",
        operationId: "[GET]/api/v2/jobs",
        description: "",
        summary    : "Jobs",
        tags       : [
            "Jobs",
        ],
        responses  : [
            new Response(
                response   : 200,
                description: "",
                content    : new JsonContent(
                    ref: "#/components/schemas/JobLookupModel"
                )
            )
        ],
        deprecated : false,
    )]
    public function Get()
    {
        $this->Model = new JobLookupModel();


        $job_id = $this->URIUtilities->getURIComponents()[4];
        $action = "access";
        if(count($this->URIUtilities->getURIComponents()) > 5)
        {
            $action = strtolower($this->URIUtilities->getURIComponents()[5]);
        }
        $job_key = null;

        if(!preg_match("/^[0-9a-f]{16}\\.[0-9a-zA-Z_-]{32}$/", $job_id))
        {
            if(preg_match("/^[0-9a-f]{16}\\.[0-9a-zA-Z_-]{32}\\.[0-9a-zA-Z_-]{64}$/", $job_id))
            {
                $id_cmps = explode(".", $job_id);
                $job_id = $id_cmps[0] . "." . $id_cmps[1];
                $job_key = $id_cmps[2];
            }
            else
            {
                $this->Model->ErrorText = "Malformed job_id";
                $this->Model->JobID = $job_id;
                $this->Render();
            }
        }

        $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_id . ".json";
        $this->Model->JobStatus = JobStatus::PENDING;
        if(!file_exists($job_path))
        {
            $this->Model->JobStatus = JobStatus::DONE;
            $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Completed/" . $job_id . ".json";
        }
        if(!file_exists($job_path))
        {
            $this->Model->JobStatus = JobStatus::FAILED;
            $job_path = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Failed/" . $job_id . ".json";
        }
        if(!file_exists($job_path))
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Invalid job id";
            $this->Render();
        }
        $job_content = json_decode(file_get_contents($job_path), true);
        $key_authed = true;
        if(isset($job_content["job_key"]))
        {
            $key_authed = false;
            if($job_key == $job_content["job_key"])
            {
                $key_authed = true;
            }
            else
            {
                if($job_key != null)
                {
                    $this->Model = new APIModel();
                    $this->Model->ErrorText = "Invalid job key";
                    $this->Render();
                }
            }
        }

        if($action == "access")
        {
            $this->Model->JobID = $job_id;
            if($key_authed)
            {
                $this->Model->Content = $job_content;
            }
            else
            {
                $this->Model->Note = "KEY_REQUIRED_TO_VIEW_CONTENT";
            }
            $this->Render();
        }
        else
        {
            $this->Model = new APIModel();
            $this->Model->ErrorText = "Invalid action";
            $this->Render();
        }
    }
}
