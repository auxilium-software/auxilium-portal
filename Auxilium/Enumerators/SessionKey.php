<?php

namespace Auxilium\Enumerators;

enum SessionKey: string
{
    case SYSTEM_SETTINGS = "system_settings";
    case FORM_SUBMISSION_ERROR = "form_submission_error";
}
