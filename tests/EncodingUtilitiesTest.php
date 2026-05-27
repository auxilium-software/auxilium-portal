<?php

use Auxilium\Utilities\EncodingUtilities;
use PHPUnit\Framework\TestCase;

class EncodingUtilitiesTest extends TestCase
{
    // <editor-fold defaultstate="collapsed" desc="Base64EncodeURLSafe">
    public function testBase64EncodeUrlSafeBasicString(): void
    {
        $encoded = EncodingUtilities::Base64EncodeURLSafe('hello');
        $this->assertSame('aGVsbG8', $encoded);
    }

    public function testBase64EncodeUrlSafeNoPadding(): void
    {
        $encoded = EncodingUtilities::Base64EncodeURLSafe('a');

        // standard base64 of 'a' is 'YQ==' - URL-safe should strip the padding
        $this->assertStringNotContainsString('=', $encoded);
    }

    public function testBase64EncodeUrlSafeNoPlus(): void
    {
        // binary data that would produce '+' in standard base64
        $data = base64_decode('+/test');
        $encoded = EncodingUtilities::Base64EncodeURLSafe($data);

        $this->assertStringNotContainsString('+', $encoded);
    }

    public function testBase64EncodeUrlSafeNoSlash(): void
    {
        $data = base64_decode('+/test');
        $encoded = EncodingUtilities::Base64EncodeURLSafe($data);

        $this->assertStringNotContainsString('/', $encoded);
    }

    public function testBase64EncodeUrlSafeEmptyString(): void
    {
        $this->assertSame('', EncodingUtilities::Base64EncodeURLSafe(''));
    }

    public function testBase64EncodeUrlSafeBinaryData(): void
    {
        $data = "\x00\xFF\x80\x7F";
        $encoded = EncodingUtilities::Base64EncodeURLSafe($data);

        $this->assertStringNotContainsString('+', $encoded);
        $this->assertStringNotContainsString('/', $encoded);
        $this->assertStringNotContainsString('=', $encoded);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Base64DecodeURLSafe">
    public function testBase64DecodeUrlSafeBasicString(): void
    {
        $decoded = EncodingUtilities::Base64DecodeURLSafe('aGVsbG8');
        $this->assertSame('hello', $decoded);
    }

    public function testBase64DecodeUrlSafeEmptyString(): void
    {
        $decoded = EncodingUtilities::Base64DecodeURLSafe('');
        $this->assertSame('', $decoded);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Roundtrip encode/decode">
    public function testRoundTripSimpleString(): void
    {
        $original = 'Hello, World!';
        $encoded = EncodingUtilities::Base64EncodeURLSafe($original);
        $decoded = EncodingUtilities::Base64DecodeURLSafe($encoded);

        $this->assertSame($original, $decoded);
    }

    public function testRoundTripBinaryData(): void
    {
        $original = "\x00\x01\x02\xFF\xFE\xFD";
        $encoded = EncodingUtilities::Base64EncodeURLSafe($original);
        $decoded = EncodingUtilities::Base64DecodeURLSafe($encoded);

        $this->assertSame($original, $decoded);
    }

    public function testRoundTripUnicodeString(): void
    {
        $original = 'Prynhawn da! 🏴󠁧󠁢󠁷󠁬󠁳󠁿';
        $encoded = EncodingUtilities::Base64EncodeURLSafe($original);
        $decoded = EncodingUtilities::Base64DecodeURLSafe($encoded);

        $this->assertSame($original, $decoded);
    }

    public function testRoundTripLongString(): void
    {
        $original = str_repeat('auxilium', 500);
        $encoded = EncodingUtilities::Base64EncodeURLSafe($original);
        $decoded = EncodingUtilities::Base64DecodeURLSafe($encoded);

        $this->assertSame($original, $decoded);
    }

    public function testRoundTripSpecialCharacters(): void
    {
        $original = 'key=value&foo=bar+baz/qux';
        $encoded = EncodingUtilities::Base64EncodeURLSafe($original);
        $decoded = EncodingUtilities::Base64DecodeURLSafe($encoded);

        $this->assertSame($original, $decoded);
    }
    // </editor-fold>
}
