<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

use Auxilium\Enumerators\Language;
use Auxilium\Enumerators\QueryParamKey;
use Auxilium\Wrappers\QueryParamWrapper;
use PHPUnit\Framework\TestCase;

class QueryParamWrapperTest extends TestCase
{
    public function testQueryParamPresent()
    {
        $_GET = [
            QueryParamKey::LANGUAGE->value => Language::ENGLISH->value,
        ];

        $key = QueryParamKey::LANGUAGE;
        $defaultVal = Language::WELSH->value;

        $this->assertEquals(
            expected: 'en',
            actual  : QueryParamWrapper::Get($key, $defaultVal),
        );
    }

    public function testQueryParamNotPresent()
    {
        $_GET = [];

        $key = QueryParamKey::LANGUAGE;
        $defaultVal = Language::WELSH->value;

        $this->assertEquals(
            expected: $defaultVal,
            actual  : QueryParamWrapper::Get($key, $defaultVal),
        );
    }
}
