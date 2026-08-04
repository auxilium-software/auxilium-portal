<?php

use Auxilium\Utilities\URIParsingUtilities;
use PHPUnit\Framework\TestCase;

class UriParsingUtilitiesTest extends TestCase
{
    private ?string $originalRequestUri = null;

    protected function setUp(): void
    {
        $this->originalRequestUri = $_SERVER['REQUEST_URI'] ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->originalRequestUri !== null)
        {
            $_SERVER['REQUEST_URI'] = $this->originalRequestUri;
        }
        else
        {
            unset($_SERVER['REQUEST_URI']);
        }
    }

    // <editor-fold defaultstate="collapsed" desc="Get UUID from URI">
    public function testGetUuidFromUriSingleUuid(): void
    {
        $_SERVER['REQUEST_URI'] = '/form/00000000-0000-0000-0000-000000000000';

        $result = URIParsingUtilities::GetUUIDFromURI(0);
        $this->assertSame('00000000-0000-0000-0000-000000000000', $result);
    }

    public function testGetUuidFromUriMultipleUuids(): void
    {
        $_SERVER['REQUEST_URI'] = '/case/00000000-0000-0000-0000-000000000000/files/11111111-1111-1111-1111-111111111111';

        $this->assertSame('00000000-0000-0000-0000-000000000000', URIParsingUtilities::GetUUIDFromURI(0));
        $this->assertSame('11111111-1111-1111-1111-111111111111', URIParsingUtilities::GetUUIDFromURI(1));
    }

    public function testGetUuidFromUriIndexOutOfBounds(): void
    {
        $_SERVER['REQUEST_URI'] = '/form/00000000-0000-0000-0000-000000000000';

        $this->assertNull(URIParsingUtilities::GetUUIDFromURI(1));
    }

    public function testGetUuidFromUriNoUuids(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/settings';

        $this->assertNull(URIParsingUtilities::GetUUIDFromURI(0));
    }

    public function testGetUuidFromUriDefaultIndex(): void
    {
        $_SERVER['REQUEST_URI'] = '/form/00000000-0000-0000-0000-000000000000';

        $result = URIParsingUtilities::GetUUIDFromURI();
        $this->assertSame('00000000-0000-0000-0000-000000000000', $result);
    }

    public function testGetUuidFromUriIgnoresNonUuidSegments(): void
    {
        $_SERVER['REQUEST_URI'] = '/admin/not-a-uuid/00000000-0000-0000-0000-000000000000/edit';

        $result = URIParsingUtilities::GetUUIDFromURI(0);
        $this->assertSame('00000000-0000-0000-0000-000000000000', $result);
    }
    // </editor-fold>
}
