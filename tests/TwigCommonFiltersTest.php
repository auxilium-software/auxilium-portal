<?php

use Auxilium\TwigHandling\Extensions\CommonFilters;
use PHPUnit\Framework\TestCase;

class TwigCommonFiltersTest extends TestCase
{
    private CommonFilters $filters;

    protected function setUp(): void
    {
        $this->filters = new CommonFilters();
    }

    // <editor-fold defaultstate="collapsed" desc="Get Filters">
    public function testGetFiltersReturnsArray(): void
    {
        $filters = $this->filters->getFilters();

        $this->assertIsArray($filters);
        $this->assertNotEmpty($filters);
    }

    public function testGetFiltersContainsExpectedNames(): void
    {
        $filters = $this->filters->getFilters();
        $names = array_map(fn($f) => $f->getName(), $filters);

        $this->assertContains('translate', $names);
        $this->assertContains('b64_url_safe', $names);
        $this->assertContains('human_filesize', $names);
        $this->assertContains('is_uuid', $names);
        $this->assertContains('ndtitle', $names);
        $this->assertContains('ndsentence', $names);
        $this->assertContains('format_as_sentence', $names);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Format as Sentence">
    public function testFormatAsSentenceLowercaseInput(): void
    {
        $this->assertSame('Hello world', $this->filters->format_as_sentence('hello world'));
    }

    public function testFormatAsSentenceAlreadyCapitalised(): void
    {
        $this->assertSame('Hello', $this->filters->format_as_sentence('Hello'));
    }

    public function testFormatAsSentenceSingleCharacter(): void
    {
        $this->assertSame('A', $this->filters->format_as_sentence('a'));
    }

    public function testFormatAsSentenceUnicodeCharacter(): void
    {
        $this->assertSame('Ŵyrdd', $this->filters->format_as_sentence('ŵyrdd'));
    }

    public function testFormatAsSentenceEmptyString(): void
    {
        $this->assertSame('', $this->filters->format_as_sentence(''));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Human Filesize">
    public function testHumanFilesizeBytes(): void
    {
        $this->assertSame('100 B', $this->filters->human_filesize('100'));
    }

    public function testHumanFilesizeZero(): void
    {
        $this->assertSame('0 B', $this->filters->human_filesize('0'));
    }

    public function testHumanFilesizeKibibytes(): void
    {
        $result = $this->filters->human_filesize((string)(50 * 1024));
        $this->assertStringEndsWith('KiB', $result);
    }

    public function testHumanFilesizeMebibytes(): void
    {
        $result = $this->filters->human_filesize((string)(50 * 1024 * 1024));
        $this->assertStringEndsWith('MiB', $result);
    }

    public function testHumanFilesizeGibibytes(): void
    {
        $result = $this->filters->human_filesize((string)(50 * 1024 * 1024 * 1024));
        $this->assertStringEndsWith('GiB', $result);
    }

    public function testHumanFilesizeTebibytes(): void
    {
        $result = $this->filters->human_filesize((string)(500 * 1024 * 1024 * 1024 * 1024));
        $this->assertStringEndsWith('TiB', $result);
    }

    public function testHumanFilesizeBoundaryAt256Bytes(): void
    {
        $this->assertSame('256 B', $this->filters->human_filesize('256'));
    }

    public function testHumanFilesizeJustAbove256Bytes(): void
    {
        $result = $this->filters->human_filesize('257');
        $this->assertStringEndsWith('KiB', $result);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Is UUID">
    public function testIsUuidValid(): void
    {
        $this->assertTrue((bool)$this->filters->is_uuid('00000000-0000-0000-0000-000000000000'));
    }

    public function testIsUuidInvalid(): void
    {
        $this->assertFalse((bool)$this->filters->is_uuid('not-a-uuid'));
    }

    public function testIsUuidNull(): void
    {
        $this->assertFalse((bool)$this->filters->is_uuid(null));
    }

    public function testIsUuidArray(): void
    {
        $this->assertFalse((bool)$this->filters->is_uuid(['an', 'array']));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Is AuxLFS URL">
    public function testIsAuxLfsUrlValidCaseFile(): void
    {
        $url = 'auxlfs://localhost/case-file/00000000-0000-0000-0000-000000000000';
        $this->assertTrue((bool)$this->filters->is_auxlfs_url($url));
    }

    public function testIsAuxLfsUrlValidUserFile(): void
    {
        $url = 'auxlfs://localhost/user-file/00000000-0000-0000-0000-000000000000';
        $this->assertTrue((bool)$this->filters->is_auxlfs_url($url));
    }

    public function testIsAuxLfsUrlInvalidScheme(): void
    {
        $url = 'https://localhost/case-file/00000000-0000-0000-0000-000000000000';
        $this->assertFalse((bool)$this->filters->is_auxlfs_url($url));
    }

    public function testIsAuxLfsUrlInvalidType(): void
    {
        $url = 'auxlfs://localhost/other-file/00000000-0000-0000-0000-000000000000';
        $this->assertFalse((bool)$this->filters->is_auxlfs_url($url));
    }

    public function testIsAuxLfsUrlMissingUuid(): void
    {
        $url = 'auxlfs://localhost/case-file/';
        $this->assertFalse((bool)$this->filters->is_auxlfs_url($url));
    }

    public function testIsAuxLfsUrlTrailingContent(): void
    {
        $url = 'auxlfs://localhost/case-file/00000000-0000-0000-0000-000000000000/extra';
        $this->assertFalse((bool)$this->filters->is_auxlfs_url($url));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Is AuxMsg URL">
    public function testIsAuxMsgUrlValid(): void
    {
        $url = 'auxmsg://localhost/message/00000000-0000-0000-0000-000000000000';
        $this->assertTrue((bool)$this->filters->is_auxmsg_url($url));
    }

    public function testIsAuxMsgUrlInvalidScheme(): void
    {
        $url = 'https://localhost/message/00000000-0000-0000-0000-000000000000';
        $this->assertFalse((bool)$this->filters->is_auxmsg_url($url));
    }

    public function testIsAuxMsgUrlWrongPath(): void
    {
        $url = 'auxmsg://localhost/chat/00000000-0000-0000-0000-000000000000';
        $this->assertFalse((bool)$this->filters->is_auxmsg_url($url));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Extract Type from AuxLFS URL">
    public function testExtractTypeUserFile(): void
    {
        $url = 'auxlfs://localhost/user-file/00000000-0000-0000-0000-000000000000';
        $this->assertSame('USER', $this->filters->extract_type_from_auxlfs_url($url));
    }

    public function testExtractTypeCaseFile(): void
    {
        $url = 'auxlfs://localhost/case-file/00000000-0000-0000-0000-000000000000';
        $this->assertSame('CASE', $this->filters->extract_type_from_auxlfs_url($url));
    }

    public function testExtractTypeDefaultsToCase(): void
    {
        $this->assertSame('CASE', $this->filters->extract_type_from_auxlfs_url('something-else'));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Extract File ID from AuxLFS URL">
    public function testExtractFileIdFromAuxLfsUrl(): void
    {
        $url = 'auxlfs://localhost/case-file/00000000-0000-0000-0000-000000000000';
        $this->assertSame('00000000-0000-0000-0000-000000000000', $this->filters->extract_file_id_from_auxlfs_url($url));
    }

    public function testExtractFileIdFromAuxLfsUrlNoUuid(): void
    {
        $this->assertSame('', $this->filters->extract_file_id_from_auxlfs_url('no-uuid-here'));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="ndtitle">
    public function testNdtitleBasic(): void
    {
        $this->assertSame('Hello World', $this->filters->ndtitle('hello world'));
    }

    public function testNdtitleSingleWord(): void
    {
        $this->assertSame('Hello', $this->filters->ndtitle('hello'));
    }

    public function testNdtitleAlreadyCapitalised(): void
    {
        $this->assertSame('Already Done', $this->filters->ndtitle('Already Done'));
    }

    public function testNdtitleUnicode(): void
    {
        $this->assertSame('Prynhawn Da', $this->filters->ndtitle('prynhawn da'));
    }

    public function testNdtitlePreservesRestOfWord(): void
    {
        $this->assertSame('HeLLO', $this->filters->ndtitle('heLLO'));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="ndsentence">
    public function testNdsentenceBasic(): void
    {
        $this->assertSame('Hello world', $this->filters->ndsentence('hello world'));
    }

    public function testNdsentenceAlreadyCapitalised(): void
    {
        $this->assertSame('Hello', $this->filters->ndsentence('Hello'));
    }

    public function testNdsentenceUnicode(): void
    {
        $this->assertSame('Ŵyrdd', $this->filters->ndsentence('ŵyrdd'));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Base64 Encode/Decode">
    public function testBase64EncodeBasic(): void
    {
        $this->assertSame('aGVsbG8=', $this->filters->base64_encode('hello'));
    }

    public function testBase64DecodeBasic(): void
    {
        $this->assertSame('hello', $this->filters->base64_decode('aGVsbG8='));
    }

    public function testBase64RoundTrip(): void
    {
        $original = 'Auxilium Software';
        $this->assertSame($original, $this->filters->base64_decode($this->filters->base64_encode($original)));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="B64 URL safe round-trip">
    public function testB64UrlSafeRoundTrip(): void
    {
        $original = 'key=value&foo=bar+baz/qux';
        $encoded = $this->filters->b64_url_safe($original);
        $decoded = $this->filters->un_b64_url_safe($encoded);

        $this->assertSame($original, $decoded);
    }

    public function testB64UrlSafeNoPaddingOrUnsafeChars(): void
    {
        $encoded = $this->filters->b64_url_safe('test data with special chars!');

        $this->assertStringNotContainsString('=', $encoded);
        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Hexadecimal to Binary">
    public function testHex2binBasic(): void
    {
        $this->assertSame('hello', $this->filters->hex2bin('68656c6c6f'));
    }

    public function testHex2binEmptyString(): void
    {
        $this->assertSame('', $this->filters->hex2bin(''));
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Translate null (null passthrough only - the actual translation depends on LocalisationUtilities)">
    public function testTranslateNullReturnsNull(): void
    {
        $this->assertNull($this->filters->translate(null));
    }
    // </editor-fold>
}
