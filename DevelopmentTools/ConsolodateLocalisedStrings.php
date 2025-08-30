<?php

require_once __DIR__ . '/../vendor/autoload.php';

echo("reading...");
$localisedStrings = json_decode(
    json: file_get_contents(
        filename: __DIR__ . '/../Configuration/Localisation/LocalisedStrings.json'
    ),
    associative: true
);


echo("building...");
$builder = [];

foreach([
    "ui_text",
    "ui_heading",
    "ui_templates",
    "data_types",
    "email_subjects",
    "search_headings",
    "case_referral",
    "paa_messages",
    "simple_notification_filter_descriptions",
    "simple_notification_filter_headings",
            ] as $target)
{
    foreach($localisedStrings[$target] as $key => $value)
    {
        $builder[$value['en']] = [
            "cy-GB"=>$value['cy'],
            "zh"=>$value['zh']??null,
        ];
    }
}

foreach([
            "node_page_text",
            "forms",
        ] as $target)
{
    foreach($localisedStrings[$target] as $key => $value)
    {
        foreach($value as $key2 => $value2)
        {
            $builder[$value2['en']] = [
                "cy-GB"=>$value2['cy']??null,
                "zh"=>$value2['zh']??null,
            ];
        }
    }
}


$builder[$localisedStrings['blank_notification_filter_description']['en']] = [
    "cy-GB"=>$localisedStrings['blank_notification_filter_description']['cy']??null,
    "zh"=>$localisedStrings['blank_notification_filter_description']['zh']??null,
];

echo("sorting...");
ksort($builder);


echo("writing...");
file_put_contents(
    filename: __DIR__ . '/../Configuration/Localisation/AllTranslations.json',
    data: json_encode(
        $builder,
        JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE
              )
);