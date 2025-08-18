<?php

namespace Auxilium\API\Enumerators;

enum JobStatus: string
{
    case PENDING = 'PENDING';
    case DONE = 'DONE';
    case FAILED = 'FAILED';
}
