<?php

namespace Auxilium\SessionHandling;

use Auxilium\DatabaseInteractions\Deegraph\Nodes\User;
use Auxilium\DatabaseInteractions\RelationalDatabaseConnection;
use Auxilium\Enumerators\CookieKey;
use Exception;

class Session
{
    private static ?Session $current = null;
    private ?User $currentUser;

    private function __construct()
    {
        $this->currentUser = null;
        if(isset($_COOKIE["session_key"]))
        {
            try
            {
                $ts = date("Y-m-d G:i:s", time() - (3600 * 48)); // 48 hr Sessions
                $bindVariables = [
                    "session_key" => $_COOKIE["session_key"],
                    "min_issue_date" => $ts
                ];
                $sql = "SELECT user_uuid FROM portal_sessions WHERE session_key=:session_key AND start_timestamp>:min_issue_date AND active=1";
                $statement = RelationalDatabaseConnection::get_pdo()->prepare($sql);
                $statement->execute($bindVariables);
                $sessionInfo = $statement->fetch();
                if($sessionInfo == null)
                { // This isn't a valid session
                    CookieHandling::DeleteCookie(targetCookie: CookieKey::SESSION_KEY);
                }
                else
                { // This *is* a valid session
                    $this->currentUser = new User($sessionInfo["user_uuid"]);
                }
            }
            catch(Exception $e)
            { // Something has gone very wrong with this session key, time to trash it
                $this->currentUser = null;
                CookieHandling::DeleteCookie(targetCookie: CookieKey::SESSION_KEY);
            }
        }
    }

    public static function get_current()
    {
        if(self::$current === null)
        {
            self::$current = new Session();
        }

        return self::$current;
    }

    public function getUser(): ?User
    {
        return $this->currentUser;
    }

    public function forceSetCurrentUser($user)
    {
        return $this->currentUser = $user;
    }

    public function sessionAuthenticated(): bool
    {
        return !($this->currentUser === null);
    }
}
