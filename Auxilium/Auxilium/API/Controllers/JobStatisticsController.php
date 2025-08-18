<?php

namespace Auxilium\API\Controllers;

use Auxilium\API\Models\JobStatsModel;
use Auxilium\API\Superclasses\APIController;
use JetBrains\PhpStorm\NoReturn;
use OpenApi\Attributes\Get;
use OpenApi\Attributes\JsonContent;
use OpenApi\Attributes\Response;

class JobStatisticsController extends APIController
{

    public function __construct()
    {
        parent::__construct();
    }


    #[NoReturn]
    #[Get(
        path       : "/api/v2/job-stats",
        operationId: "[GET]/api/v2/job-stats",
        description: "",
        summary    : "Job statistics",
        tags       : [
            "Jobs",
        ],
        responses  : [
            new Response(
                response   : 200,
                description: "",
                content    : new JsonContent(
                    ref: "#/components/schemas/JobStatsModel"
                )
            )
        ],
        deprecated : false,
    )]
    public function Get(): JobStatsModel
    {
        $this->Model = new JobStatsModel();


        $c_time = time();
        $job_names = [];
        $total_jobs = 0;

        $jobs = scandir(LOCAL_EPHEMERAL_CREDENTIAL_STORE . "/Jobs/Queue/");
        foreach($jobs as &$job_name)
        {
            if(!in_array($job_name, [".", "..", "Completed", "Failed"]))
            {
                $total_jobs++;
                $job_name = substr($job_name, 0, -5);
                var_dump($job_name);
                $time = unpack(
                    format: "Jtime",
                    string: hex2bin(
                        string: substr(
                            string: $job_name,
                            offset: 0,
                            length: 16
                        )
                    )
                )["time"];
                $job_info = [
                    "id" => $job_name,
                    "created" => $time,
                    "time_elapsed" => $c_time - $time
                ];
                $job_names[] = $job_info;
            }
        }

        $this->Model->Jobs = $job_names;
        $this->Render();
    }
}
