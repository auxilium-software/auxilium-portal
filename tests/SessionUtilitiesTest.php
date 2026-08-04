<?php

use Auxilium\Enumerators\SessionKey;
use Auxilium\Utilities\SessionUtilities;
use PHPUnit\Framework\TestCase;

class SessionUtilitiesTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
    }

    // <editor-fold defaultstate="collapsed" desc="Get">
    public function testGetReturnsStoredValue(): void
    {
        $_SESSION[SessionKey::FORM_SUBMISSION_ERROR->value] = 'Something went wrong';

        $result = SessionUtilities::Get(SessionKey::FORM_SUBMISSION_ERROR);
        $this->assertSame('Something went wrong', $result);
    }

    public function testGetReturnsNullWhenKeyMissing(): void
    {
        $result = SessionUtilities::Get(SessionKey::FORM_SUBMISSION_ERROR);
        $this->assertNull($result);
    }

    public function testGetReturnsDefaultWhenKeyMissing(): void
    {
        $result = SessionUtilities::Get(SessionKey::FORM_SUBMISSION_ERROR, 'fallback');
        $this->assertSame('fallback', $result);
    }

    public function testGetReturnsFalseDefault(): void
    {
        $result = SessionUtilities::Get(SessionKey::FORM_SUBMISSION_ERROR, false);
        $this->assertFalse($result);
    }

    public function testGetReturnsArrayValue(): void
    {
        $_SESSION[SessionKey::SYSTEM_SETTINGS->value] = ['Id' => '123-ABC', 'FullName' => 'Cerys'];

        $result = SessionUtilities::Get(SessionKey::SYSTEM_SETTINGS);

        $this->assertIsArray($result);
        $this->assertSame('123-ABC', $result['Id']);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Set">
    public function testSetStoresValue(): void
    {
        SessionUtilities::Set(SessionKey::FORM_SUBMISSION_ERROR, 'test error');

        $this->assertSame('test error', $_SESSION[SessionKey::FORM_SUBMISSION_ERROR->value]);
    }

    public function testSetOverwritesExistingValue(): void
    {
        $_SESSION[SessionKey::FORM_SUBMISSION_ERROR->value] = 'old';
        SessionUtilities::Set(SessionKey::FORM_SUBMISSION_ERROR, 'new');

        $this->assertSame('new', $_SESSION[SessionKey::FORM_SUBMISSION_ERROR->value]);
    }

    public function testSetStoresArrayValue(): void
    {
        $data = ['key' => 'value'];
        SessionUtilities::Set(SessionKey::SYSTEM_SETTINGS, $data);

        $this->assertSame($data, $_SESSION[SessionKey::SYSTEM_SETTINGS->value]);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Delete">
    public function testDeleteRemovesKey(): void
    {
        $_SESSION[SessionKey::FORM_SUBMISSION_ERROR->value] = 'to be removed';
        SessionUtilities::Delete(SessionKey::FORM_SUBMISSION_ERROR);

        $this->assertArrayNotHasKey(SessionKey::FORM_SUBMISSION_ERROR->value, $_SESSION);
    }

    public function testDeleteNonExistentKeyDoesNotError(): void
    {
        // should not throw
        SessionUtilities::Delete(SessionKey::FORM_SUBMISSION_ERROR);

        $this->assertArrayNotHasKey(SessionKey::FORM_SUBMISSION_ERROR->value, $_SESSION);
    }
    // </editor-fold>

    // <editor-fold defaultstate="collapsed" desc="Set then Get then Delete">
    public function testSetGetDeleteRoundTrip(): void
    {
        SessionUtilities::Set(SessionKey::FORM_SUBMISSION_ERROR, 'round-trip');
        $this->assertSame('round-trip', SessionUtilities::Get(SessionKey::FORM_SUBMISSION_ERROR));

        SessionUtilities::Delete(SessionKey::FORM_SUBMISSION_ERROR);
        $this->assertNull(SessionUtilities::Get(SessionKey::FORM_SUBMISSION_ERROR));
    }
    // </editor-fold>
}
