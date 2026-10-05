<?php

namespace response;

use response\interface\ResponseInterface;
use response\interface\SenderInterface;
use response\registry\SenderRegistry;
use response\resolvers\MimeResolver;
use response\senders\{
    AudioSender,
    BinarySender,
    CssSender,
    HtmlSender,
    ImageSender,
    JsSender,
    JsonSender,
    PdfSender,
    TextSender,
    VideoSender,
    XmlSender
};

/**
 * Fluent HTTP Response Facade.
 *
 * Instantiate via the static factory or new directly.
 *
 * Examples
 * ────────
 *
 * // Auto-resolve sender from the request's Accept header:
 * Response::make(200)->data(['users' => []])->send();
 *
 * // Explicit type helpers:
 * Response::make(200)->data(['ok' => true])->asJson()->send();
 * Response::make(200)->file('/var/www/clip.mp4')->send();
 * Response::make(201)->data(['id' => 42])->asXml()->send();
 *
 * // Custom header + redirect:
 * Response::make(302)->header('Location', '/home')->send();
 * Response::make()->redirect('/login');
 */
class Response implements ResponseInterface
{
    // ── State ─────────────────────────────────────────────────────────────────

    private int     $statusCode = 200;
    private string  $statusMessage = 'OK';
    private array   $headers    = [];
    private mixed   $payload    = null;  // array|string (file path)|null
    private ?string $forcedMime = null;  // set by asJson(), asXml(), …

    // ── Infrastructure (shared across instances) ──────────────────────────────

    private static ?SenderRegistry $registry = null;
    private static ?MimeResolver   $resolver = null;

    // ── Factory ───────────────────────────────────────────────────────────────

    public static function make(int $status = 200, string $message = 'OK'): static
    {
        $instance = new static();
        $instance->statusCode    = $status;
        $instance->statusMessage = $message;
        return $instance;
    }

    // ── Chainable builder methods ─────────────────────────────────────────────

    public function status(int $code): static
    {
        $this->statusCode = $code;
        return $this;
    }

    public function message(string $message): static
    {
        $this->statusMessage = $message;
        return $this;
    }

    public function header(string $name, string $value): static
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** Set structured data payload (array). */
    public function data(array $data): static
    {
        $this->payload = $data;
        return $this;
    }

    /** Set a file path as the payload; auto-resolves MIME from extension. */
    public function file(string $path): static
    {
        $this->payload = $path;
        return $this;
    }

    /** Force a specific MIME type, overriding auto-resolution. */
    public function as(string $mimeType): static
    {
        $this->forcedMime = $mimeType;
        return $this;
    }

    // ── Convenience type aliases (readable & IDE-friendly) ────────────────────

    public function asJson(): static  { return $this->as('application/json'); }
    public function asHtml(): static  { return $this->as('text/html'); }
    public function asXml(): static   { return $this->as('application/xml'); }
    public function asCss(): static   { return $this->as('text/css'); }
    public function asJs(): static    { return $this->as('application/javascript'); }
    public function asText(): static  { return $this->as('text/plain'); }
    public function asPdf(): static   { return $this->as('application/pdf'); }
    public function asFile(): static  { return $this->as('application/octet-stream'); }

    // ── Redirect shortcut ─────────────────────────────────────────────────────

    public function redirect(string $url, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: $url");
        exit;
    }

    // ── Core send ─────────────────────────────────────────────────────────────

    /**
     * Resolve the correct sender and emit the response.
     *
     * Resolution order:
     *  1. Explicitly forced MIME via ->as() / ->asJson() / …
     *  2. MIME inferred from the file extension (when ->file() was used).
     *  3. MIME inferred from the HTTP Accept request header.
     *  4. Default fallback: application/json.
     */
    public function send(): void
    {
        $mime   = $this->resolveMime();
        $sender = static::registry()->resolve($mime);

        // Wrap plain arrays in a standard envelope when using JSON and no file.
        $payload = $this->payload;
        if (is_array($payload) && $mime === 'application/json') {
            $payload = [
                'status'  => $this->statusCode,
                'message' => $this->statusMessage,
                'data'    => $payload,
            ];
        }

        $sender->send($payload, $this->statusCode, $this->headers);
    }

    // ── Private: MIME resolution ──────────────────────────────────────────────

    private function resolveMime(): string
    {
        // 1. Explicit override.
        if ($this->forcedMime !== null) {
            return $this->forcedMime;
        }

        // 2. File extension.
        if (is_string($this->payload) && $this->payload !== '') {
            $resolved = static::resolver()->fromPath($this->payload);
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // 3. HTTP Accept header.
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if ($accept !== '') {
            $resolved = static::resolver()->fromAccept($accept);
            if ($resolved !== null && static::registry()->has($resolved)) {
                return $resolved;
            }
        }

        // 4. Default.
        return 'application/json';
    }

    // ── Private: lazy singleton infrastructure ────────────────────────────────

    private static function registry(): SenderRegistry
    {
        if (static::$registry === null) {
            static::$registry = new SenderRegistry();
            static::$registry->register(new JsonSender());
            static::$registry->register(new HtmlSender());
            static::$registry->register(new CssSender());
            static::$registry->register(new JsSender());
            static::$registry->register(new XmlSender());
            static::$registry->register(new TextSender());
            static::$registry->register(new ImageSender());
            static::$registry->register(new VideoSender());
            static::$registry->register(new AudioSender());
            static::$registry->register(new PdfSender());
            static::$registry->register(new BinarySender());
        }
        return static::$registry;
    }

    private static function resolver(): MimeResolver
    {
        if (static::$resolver === null) {
            static::$resolver = new MimeResolver();
        }
        return static::$resolver;
    }

    /**
     * Swap the registry at runtime (useful for testing or custom senders).
     */
    public static function setRegistry(SenderRegistry $registry): void
    {
        static::$registry = $registry;
    }
}
