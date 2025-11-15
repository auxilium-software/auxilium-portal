<?php

namespace Auxilium\Enumerators;

enum SessionKey: string
{
    case USER_DETAILS = "user_details";
    case FORM_SUBMISSION_ERROR = "form_submission_error";
}
