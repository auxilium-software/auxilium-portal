<?php

use Auxilium\TwigHandling\PageBuilder;
use Auxilium\Utilities\AdminConsoleUtilities;
use Auxilium\Utilities\ConfigurationUtilities;
use Auxilium\Utilities\SecurityUtilities;

require_once __DIR__ . '/../vendor/autoload.php';


try
{
    SecurityUtilities::RequireAdmin();

    $subjectKey = explode('/', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    end($subjectKey);
    $subjectKey = prev($subjectKey);

    $subject = ConfigurationUtilities::GetMergeSubjects()[$subjectKey] ?? null;

    $partialsDirectory = __DIR__ . '/../Templates';
    $adapterPath = isset($subject['Adapter']) ? $partialsDirectory . '/' . $subject['Adapter'] : false;

    if (
        $subject === null
        || $adapterPath === false
    )
    {
        PageBuilder::Render404();
    }

    if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $subject['Api']['Controller'] ?? ''))
    {
        throw new Exception("Merge subject '" . $subjectKey . "' has an invalid Api.Controller in MergeSubjects.yaml");
    }

    PageBuilder::Render(
        template: '/VirtualPages/AdminConsoleObjectMergePage.html.twig',
        variables: array_merge(
            AdminConsoleUtilities::GrabVariablesToPassIntoTwig(),
            [
                'MergeSubject' => $subjectKey,
                'MergeConfig'  => $subject,
            ],
        )
    );
}
catch(Exception $e)
{
    PageBuilder::RenderInternalSystemError($e);
}
