<?php
namespace package\dependency;

readonly class Dependency
{
    public function __construct(public string $name, public string $version = '*', public string $url = '') {}
    public static function fromArray(array $data): static
    {
        if (!isset($data['name']) || !is_string($data['name']) || trim($data['name']) === '') {
            throw new \InvalidArgumentException('Dependencies must specify a package name.');
        }
        $version = $data['version'] ?? '*';
        $url = $data['url'] ?? '';
        if (!is_string($version) || !is_string($url)) throw new \InvalidArgumentException('Dependency version and url must be strings.');
        self::matchesVersion('0.0.0', $version); // Validate even before the dependency is installed.
        return new static($data['name'], $version, $url);
    }
    /** Exact versions, *, and whitespace-separated comparisons (logical AND). */
    public static function matchesVersion(string $version, string $constraint): bool
    {
        if ($constraint === '*') return true;
        $matches = true;
        foreach (preg_split('/\s+/', trim($constraint)) as $part) {
            if (!preg_match('/^(>=|<=|>|<|==|=|!=)?(\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?)$/', $part, $match)) {
                throw new \InvalidArgumentException("Unsupported dependency version constraint: {$constraint}");
            }
            $matches = version_compare($version, $match[2], ($match[1] ?? '') ?: '==') && $matches;
        }
        return $matches;
    }
}
