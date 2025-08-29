<?php


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../Configuration/Configuration/Environment.php';

use Auxilium\Schemas\CaseSchema;
use Auxilium\Schemas\CollectionSchema;
use Auxilium\Schemas\DocumentSchema;
use Auxilium\Schemas\EnumSchema;
use Auxilium\Schemas\MessageSchema;
use Auxilium\Schemas\OrganisationSchema;
use Auxilium\Schemas\UserSchema;
use Darksparrow\AuxiliumSchemaBuilder\SchemaBuilder\SchemaBuilder;
use Darksparrow\AuxiliumSchemaBuilder\Utilities\URLHandling;
use PHPUnit\Framework\TestCase;

class SchemaGenerationTest extends TestCase
{
    public function testCaseSchema()
    {
        $this->compare(CaseSchema::class);
    }

    private function compare(string $schemaClassName): void
    {
        $useAssoc = true;

        $actualSchemaRawJSON = file_get_contents(URLHandling::GetURLForSchema($schemaClassName));
        $actualSchemaAssocArray = json_decode($actualSchemaRawJSON, $useAssoc);

        $generatedSchema = SchemaBuilder::GenerateSchema($schemaClassName);
        $generatedSchema = json_decode(json_encode($generatedSchema), $useAssoc);

        self::assertEquals(
            expected: $actualSchemaAssocArray,
            actual  : $generatedSchema,
        );
    }

    public function testCollectionSchema()
    {
        $this->compare(CollectionSchema::class);
    }

    public function testDocumentSchema()
    {
        $this->compare(DocumentSchema::class);
    }

    public function testEnumSchema()
    {
        $this->compare(EnumSchema::class);
    }

    public function testMessageSchema()
    {
        $this->compare(MessageSchema::class);
    }

    public function testOrganisationSchema()
    {
        $this->compare(OrganisationSchema::class);
    }

    public function testUserSchema()
    {
        $this->compare(UserSchema::class);
    }
}
