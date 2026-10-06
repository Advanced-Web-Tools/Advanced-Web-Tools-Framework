<?php

namespace router\security;

use router\interface\ICsrfTokenStore;
use session\SessionHandler;

final class SessionTokenStore implements ICsrfTokenStore
{
    public function __construct(private readonly SessionHandler $session = new SessionHandler()) {}

    private function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            $this->session->SessionHandler();
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            throw new \RuntimeException('CSRF protection requires an active session.');
        }
    }

    public function get(string $name): ?string
    {
        $this->start();
        $token = $_SESSION[$name] ?? null;
        return is_string($token) ? $token : null;
    }

    public function put(string $name, string $token): void
    {
        $this->start();
        $_SESSION[$name] = $token;
    }
}
