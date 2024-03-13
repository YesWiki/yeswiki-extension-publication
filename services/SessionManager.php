<?php

namespace YesWiki\Publication\Service;

use Throwable;

class SessionManager
{
    private $previousSession;

    public function __construct(
    ) {
        $this->previousSession = null;
    }

    public function reactivateSession()
    {
        $savedSession = null;
        try {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                if (isset($_SESSION)) {
                    $savedSession = $_SESSION;
                }
                session_start();
                $this->updateSession($savedSession);
                // update session in system
                session_write_close();
                session_start();
            } else {
                $this->previousSession = $_SESSION;
            }
        } catch (Throwable $th) {
            // do nothing
        }
    }

    public function safeCloseSession(bool $save = true)
    {
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                if ($save) {
                    $this->previousSession = $_SESSION;
                    session_write_close();
                } else {
                    session_abort();
                }
            }
        } catch (Throwable $th) {
            // do nothing
        }
    }

    protected function updateSession(?array $savedSession)
    {
        if (is_array($this->previousSession) && is_array($savedSession) && isset($_SESSION) && is_array($_SESSION)) {
            foreach ($_SESSION as $k => $v) {
                if (!array_key_exists($k, $savedSession) &&
                    array_key_exists($k, $this->previousSession)) {
                    // delete while session inactive
                    unset($_SESSION[$k]);
                } elseif (!$this->isEqual($_SESSION[$k], $savedSession[$k])) {
                    if (array_key_exists($k, $this->previousSession) &&
                        $this->isEqual($this->previousSession[$k], $_SESSION[$k])) {
                        // update while session inactive
                        $_SESSION[$k] = $savedSession[$k];
                    }
                }
            }
            foreach ($savedSession as $k => $v) {
                if (!array_key_exists($k, $_SESSION) &&
                    !array_key_exists($k, $this->previousSession)) {
                    // add while session inactive
                    $_SESSION[$k] = $savedSession[$k];
                }
            }
        }
        $this->previousSession = $_SESSION;
    }

    public function isEqual($a, $b): bool
    {
        if (is_float($a) && is_float($b)) {
            return (abs($a - $b) < 1E-9);
        } elseif (is_float($a) != is_float($b)) {
            return false;
        } else {
            return $a === $b;
        }
    }
}
