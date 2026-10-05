<?php

namespace response\registry;

use response\interface\SenderInterface;

/**
 * Open/Closed: add new types by registering a new SenderInterface.
 * No existing class needs modification.
 */
class SenderRegistry
{
    /** @var array<string, SenderInterface> mime → sender */
    private array $map = [];

    public function register(SenderInterface $sender): void
    {
        foreach ($sender->supportedMimes() as $mime) {
            $this->map[strtolower($mime)] = $sender;
        }
    }

    /**
     * @throws \RuntimeException when no sender handles the given MIME.
     */
    public function resolve(string $mime): SenderInterface
    {
        $key = strtolower(strtok($mime, ';')); // strip parameters like "; charset=utf-8"

        if (isset($this->map[$key])) {
            return $this->map[$key];
        }

        // Partial match on type group (e.g. "image/*").
        [$group] = explode('/', $key);
        foreach ($this->map as $registeredMime => $sender) {
            if (str_starts_with($registeredMime, $group . '/')) {
                return $sender;
            }
        }

        throw new \RuntimeException("No sender registered for MIME type '$mime'.");
    }

    public function has(string $mime): bool
    {
        try {
            $this->resolve($mime);
            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }
}
