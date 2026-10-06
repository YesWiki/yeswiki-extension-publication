<?php

namespace YesWiki\Publication\Service;

use YesWiki\Kernel\Service\RequestScopedState;

/** Opens the session only for the moment a print job writes its progress, so the page polling that progress is never locked out. */
class SessionManager implements RequestScopedState
{
    /** @var array<string, mixed>|null */
    private ?array $previousSession = null;

    public function startNewRequest(): void
    {
        $this->previousSession = null;
    }

    public function reactivateSession(): void
    {
        try {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $this->previousSession = $_SESSION;

                return;
            }
            $savedSession = isset($_SESSION) ? $_SESSION : null;
            if (headers_sent() || !session_start()) {
                return;
            }
            $this->updateSession($savedSession);
            session_write_close();
            session_start();
        } catch (\Throwable) {
        }
    }

    public function safeCloseSession(bool $save = true): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        if ($save) {
            $this->previousSession = $_SESSION;
            session_write_close();
        } else {
            session_abort();
        }
    }

    /**
     * Carry into the reopened session what this request changed while it was closed, and drop what it removed.
     *
     * @param array<string, mixed>|null $savedSession
     */
    private function updateSession(?array $savedSession): void
    {
        if (is_array($this->previousSession) && is_array($savedSession)) {
            foreach ($_SESSION as $key => $value) {
                $wasThere = array_key_exists($key, $this->previousSession);
                if (!array_key_exists($key, $savedSession)) {
                    if ($wasThere) {
                        unset($_SESSION[$key]);
                    }
                } elseif (!$this->isEqual($value, $savedSession[$key]) && $wasThere && $this->isEqual($this->previousSession[$key], $value)) {
                    $_SESSION[$key] = $savedSession[$key];
                }
            }
            foreach ($savedSession as $key => $value) {
                if (!array_key_exists($key, $_SESSION) && !array_key_exists($key, $this->previousSession)) {
                    $_SESSION[$key] = $value;
                }
            }
        }
        $this->previousSession = $_SESSION;
    }

    public function isEqual(mixed $a, mixed $b): bool
    {
        if (is_float($a) && is_float($b)) {
            return abs($a - $b) < 1E-9;
        }

        return $a === $b;
    }
}
