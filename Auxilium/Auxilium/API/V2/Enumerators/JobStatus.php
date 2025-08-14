<?php

namespace Auxilium\API\V2\Enumerators;

enum JobStatus: string
{
    case PENDING = 'PENDING';
    case DONE = 'DONE';
    case FAILED = 'FAILED';
}
