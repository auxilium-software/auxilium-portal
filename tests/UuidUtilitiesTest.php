<?php

use Auxilium\Utilities\UUIDUtilities;
use PHPUnit\Framework\TestCase;

class UuidUtilitiesTest extends TestCase
{
    // <editor-fold defaultstate="collapsed" desc="IsValid">
    public function testIsValidWithValidV4Uuid(): void
    {
        $this->assertTrue(UUIDUtilities::IsValid('550e8400-e29b-41d4-a716-446655440000'));
    }

    public function testIsValidWithUppercaseUuid(): void
    {
        $this->assertTrue(UUIDUtilities::IsValid('550E8400-E29B-41D4-A716-446655440000'));
    }

    public function testIsValidWithMixedCaseUuid(): void
    {
        $this->assertTrue(UUIDUtilities::IsValid('550e8400-E29B-41d4-a716-446655440000'));
    }

    public function testIsValidWithEmptyString(): void
    {
        $this->assertFalse(UUIDUtilities::IsValid(''));
    }

    public function testIsValidWithRandomString(): void
    {
        $this->assertFalse(UUIDUtilities::IsValid('not-a-uuid'));
    }

    public function testIsValidWithTooShort(): void
    {
        $this->assertFalse(UUIDUtilities::IsValid('550e8400-e29b-41d4-a716'));
    }

    public function testIsValidWithTooLong(): void
    {
        $this->assertFalse(UUIDUtilities::IsValid('550e8400-e29b-41d4-a716-446655440000-extra'));
    }

    public function testIsValidWithMissingHyphens(): void
    {
        $this->assertFalse(UUIDUtilities::IsValid('550e8400e29b41d4a716446655440000'));
    }

    public function testIsValidWithInvalidHexCharacters(): void
    {
        $this->assertFalse(UUIDUtilities::IsValid('550e8400-e29b-41d4-a716-44665544gggg'));
    }

    public function testIsValidWithNilUuid(): void
    {
        $this->assertTrue(UUIDUtilities::IsValid('00000000-0000-0000-0000-000000000000'));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="CreateV4">
    public function testCreateV4ReturnsValidUuid(): void
    {
        $uuid = UUIDUtilities::CreateV4();
        $this->assertTrue(UUIDUtilities::IsValid($uuid));
    }

    public function testCreateV4ReturnsCorrectFormat(): void
    {
        $uuid = UUIDUtilities::CreateV4();

        // 8-4-4-4-12 format
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testCreateV4HasCorrectVersionNibble(): void
    {
        $uuid = UUIDUtilities::CreateV4();
        $segments = explode('-', $uuid);

        // third segment should start with '4' (version 4)
        $this->assertSame('4', $segments[2][0]);
    }

    public function testCreateV4HasCorrectVariantBits(): void
    {
        $uuid = UUIDUtilities::CreateV4();
        $segments = explode('-', $uuid);

        // fourth segment's first character should be 8, 9, a, or b (variant 1)
        $firstNibble = $segments[3][0];
        $this->assertContains($firstNibble, ['8', '9', 'a', 'b']);
    }

    public function testCreateV4GeneratesUniqueValues(): void
    {
        $uuids = [];
        for ($i = 0; $i < 100; $i++)
        {
            $uuids[] = UUIDUtilities::CreateV4();
        }

        $this->assertCount(100, array_unique($uuids));
    }
    // </editor-fold>
}
