<?php

namespace Auxilium\Enumerators;

enum InternetMessageTransportService: string
{
    case MS_GRAPH = "MS_APP_GRAPH";
    case STANDARD = "STANDARD";
    case AWS = "AWS_SES";
}
