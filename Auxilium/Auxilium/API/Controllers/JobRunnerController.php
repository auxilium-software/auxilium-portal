<?php

namespace Auxilium\API\Controllers;

use Auxilium\API\Models\JobInQueueModel;
use Auxilium\API\Superclasses\APIController;
use Auxilium\Auxilium\API\Controllers\RuntimeException;
use Auxilium\EmailHandling\InternetMessageTransport;
use Exception;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;

class JobRunnerController extends APIController
{
    //const EXEC_TIME_LIMIT = 5000000000; // stop after 5 seconds
    private int $EXEC_TIME_LIMIT = 1000000000; // stop after 1000 msec
    //const EXEC_TIME_LIMIT = 100000000; // stop after 100 msec
    private int $REFRESH_RATE = 3;

    public function __construct()
    {
        parent::__construct();
    }


    #[NoReturn]
    #[Get(
        path       : "/api/v2/job-run",
        operationId: "[GET]/api/v2/job-run",
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
                    ref: "#/components/schemas/JobInQueueModel"
                )
            )
        ],
        deprecated : false,
    )]
    public function Get()
    {
        $this->Model = new JobInQueueModel();


        $time_pre = hrtime(true);
        $completed_jobs = 0;
        $attempted_jobs = 0;
        $total_jobs = 0;

        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/LastJobRun"))
        {
            file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/LastJobRun", time());
        }
        $last_run = (int)file_get_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/LastJobRun");
        $this_run = time();
        $run_diff = $this_run - $last_run;
        file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/LastJobRun", $this_run);

        // create directories
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/"))
        {
            if(!mkdir($concurrentDirectory = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/", 0700, true) && !is_dir($concurrentDirectory))
            {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Completed/"))
        {
            if(!mkdir($concurrentDirectory = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Completed/", 0700, true) && !is_dir($concurrentDirectory))
            {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }
        if(!file_exists(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Failed/"))
        {
            if(!mkdir($concurrentDirectory = LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Failed/", 0700, true) && !is_dir($concurrentDirectory))
            {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $concurrentDirectory));
            }
        }

        if($run_diff > $this->REFRESH_RATE)
        {
            $job_name = "cron-" . $this_run;
            $job_payload = [
                "type" => "SCAN_INBOXES",
                "tries" => 0,
                "max_tries" => 1
            ];
            file_put_contents(
                LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name,
                json_encode($job_payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT)
            );
        }

        $jobs = scandir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/");
        foreach($jobs as &$job_name)
        {
            if(!in_array($job_name, [".", "..", "Completed", "Failed"]))
            {
                $total_jobs++;
            }
        }

        foreach($jobs as &$job_name)
        {
            if(!in_array($job_name, [".", "..", "Completed", "Failed"]))
            {
                $job_payload = json_decode(file_get_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name), true);
                $success = false;
                $error_message = null;
                $exception = null;
                $job_payload["tries"]++;

                try
                {
                    switch($job_payload["type"])
                    {
                        case "SEND_EMAIL":
                            $success = InternetMessageTransport::send_now($job_payload["content"]);
                            break;
                        case "SCAN_INBOXES":
                            $success = InternetMessageTransport::scan_inboxes();
                            break;
                        case "INGEST_S3_EMAIL":
                            //$success = \auxilium\InternetMessageTransport::ingest_s3_object($job_payload["key"]);
                            break;
                    }
                }
                catch(Exception $e)
                {
                    $success = false;
                    $exception = $e;
                }

                if($success !== true)
                {
                    $error_message = [
                        "try" => ($job_payload["tries"] - 1)
                    ];
                    if($exception != null)
                    {
                        $error_message["exception"] = $exception->getMessage();
                    }
                    if($success !== false)
                    {
                        $error_message["return_object"] = $success;
                    }
                    $success = false;
                }


                if($error_message != null)
                {
                    if(!array_key_exists("errors", $job_payload))
                    {
                        $job_payload["errors"] = [];
                    }
                    $job_payload["errors"][] = $error_message;
                }

                if($success)
                {
                    unlink(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name);
                    file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Completed/" . $job_name, json_encode($job_payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                }
                else
                {
                    if($job_payload["tries"] < $job_payload["max_tries"])
                    {
                        file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name, json_encode($job_payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                    }
                    else
                    {
                        unlink(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/" . $job_name);
                        file_put_contents(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Failed/" . $job_name, json_encode($job_payload, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
                    }
                }
                if((hrtime(true) - $time_pre) > $this->EXEC_TIME_LIMIT)
                {
                    break;
                }
            }
        }

        $this->Model->CompletedJobs = $completed_jobs;
        $this->Model->AttemptedJobs = $attempted_jobs;
        $this->Model->RemainingJobs = $total_jobs - $completed_jobs;
        $this->Model->ElapsedTimeUS = ceil((hrtime(true) - $time_pre) / 1000);
        $this->Model->ExecTimeLimitUS = $this->EXEC_TIME_LIMIT;

        $this->Render();
    }
}
