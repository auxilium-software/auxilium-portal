<?php

namespace Auxilium\FormHandling;

final class FormSubmissionActionResultWrapper
{
    public bool $Success;
    public string $Message;
    public array $Results;

    public function __construct(bool $success, string $message, array $results = [])
    {
        $this->Success = $success;
        $this->Message = $message;
        $this->Results = $results;
    }
}
