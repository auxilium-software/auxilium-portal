<?php


use Auxilium\Utilities\LocalisationUtilities;

require_once __DIR__ . '/../../vendor/autoload.php';


header('Content-Type: application/json');

try
{
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);

    if (!isset($input['text']))
    {
        http_response_code(400);
        echo json_encode(['error' => 'Missing required field: text'], JSON_THROW_ON_ERROR);
        return;
    }

    $text = $input['text'];
    $substitutions = $input['substitutions'] ?? [];

    if (!is_array($substitutions))
    {
        http_response_code(400);
        echo json_encode(['error' => 'Substitutions must be an object'], JSON_THROW_ON_ERROR);
        return;
    }

    $translated = LocalisationUtilities::Translate($text, $substitutions);

    echo json_encode([
        'success' => true,
        'translated' => $translated
    ], JSON_THROW_ON_ERROR
    );

}
catch (\JsonException $e)
{
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON in request body'], JSON_THROW_ON_ERROR);
}
catch (\Exception $e)
{
    http_response_code(500);
    echo json_encode(['error' => 'Translation failed: ' . $e->getMessage()], JSON_THROW_ON_ERROR);
}
