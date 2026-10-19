<?php

namespace session;

use setting\Config;

class SessionHandler
{
    private const ROTATION_GRACE_SECONDS = 60;
    private int $time;

    /**
     * Initializes and manages session handling.
     *
     * - Starts the session if it’s not already active.
     * - Configures session cookie parameters such as SameSite, Secure, and HttpOnly based on configuration.
     * - Tracks session start time and manages session expiration and ID regeneration.
     * - Retains retired IDs briefly for concurrent or cancelled requests.
     * - Rejects expired sessions and retired IDs after the grace period.
     *
     * @return bool False when the session has expired or its retired ID is too old.
     */
    public function SessionHandler(): bool
    {
        $this->time = time();

        if (session_status() !== PHP_SESSION_ACTIVE) {
            $sameSite = Config::getConfig("AWT", "Session SameSite")->getValue();
            $sameSite = ($sameSite == 'true') ? "Strict" : "None";

            $secure = Config::getConfig("AWT", "Session HTTPS Only")->getValue() == 'true';
            $httpOnly = Config::getConfig("AWT", "Session HTTP Only")->getValue() == 'true';

            session_set_cookie_params([
                "SameSite" => $sameSite,
                "Secure" => $secure,
                "HttpOnly" => $httpOnly
            ]);

            session_start();
        }

        $_SESSION['sessionInfo']['started'] = $_SESSION['sessionInfo']['started'] ?? $this->time;

        $retiredAt = $_SESSION['sessionInfo']['retired_at'] ?? null;
        if ($retiredAt !== null && $this->time - (int) $retiredAt >= self::ROTATION_GRACE_SECONDS) {
            $this->sessionClearing();
            return false;
        }

        if (isset($_SESSION['sessionInfo']['expires'])) {
            if ($_SESSION['sessionInfo']['expires'] < $this->time) {
                $this->sessionClearing();
                return false;
            } else {
                $_SESSION['sessionInfo']['expires'] += Config::getConfig("AWT", "Session ID Regeneration Time")->getValue();
            }
        }

        $interval = max(1, (int) (Config::getConfig('AWT', 'Session ID Regeneration Time')?->getValue() ?? 900));
        $lastRotation = $_SESSION['sessionInfo']['regenerate_id'] ?? $this->time;
        $_SESSION['sessionInfo']['regenerate_id'] = $lastRotation;
        if (($retiredAt !== null || $this->time - (int) $lastRotation >= $interval) && !headers_sent()) {
            // Save the retirement deadline in the old session, then remove it only
            // from the new one. Losing Set-Cookie must not immediately log out a
            // concurrent request still carrying the old ID.
            $_SESSION['sessionInfo']['retired_at'] = $retiredAt ?? $this->time;
            if (session_regenerate_id(false)) {
                unset($_SESSION['sessionInfo']['retired_at']);
                $_SESSION['sessionInfo']['regenerate_id'] = $this->time;
            } elseif ($retiredAt === null) {
                unset($_SESSION['sessionInfo']['retired_at']);
            }
        }

        return true;
    }

    protected function sessionClearing(): void
    {
        unset($_SESSION);
        unset($_COOKIE['LAST_IP']);
        session_destroy();

    }

    public function sessionDumper(): void
    {
        var_dump($_SESSION);
    }
}
