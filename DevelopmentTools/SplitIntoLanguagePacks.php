<?php

require_once __DIR__ . '/../vendor/autoload.php';


echo("reading...");
$allTranslations = json_decode(
    json: file_get_contents(
              filename: __DIR__ . '/../Configuration/Localisation/AllTranslations.json'
          ),
    associative: true
);

echo("building...");
$languagePacks = [
    "cy-GB",
    "zh"
];

$builder = [];

foreach($allTranslations as $enGB=>$translations)
{
    foreach($languagePacks as $languagePack)
    {
        $builder[$languagePack][$enGB] = $translations[$languagePack] ?? null;
    }
}

echo "writing...";
foreach($languagePacks as $languagePack)
{
    file_put_contents(
        filename: __DIR__ . "/../Configuration/Localisation/LanguagePacks/{$languagePack}.json",
        data: json_encode($builder[$languagePack], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)
    );
}
