<?php

namespace installer\framework;

use RuntimeException;
use Throwable;

final readonly class FrameworkUpdater
{
    public function __construct(
        private string $root,
        private string $lockPath,
        private FrameworkReleaseSource $releases = new FrameworkReleaseSource(),
    ) {}

    public function run(bool $checkOnly = false): string
    {
        $tag = $this->releases->latestTag();
        $latest = ltrim($tag, 'v');
        $current = FrameworkVersion::current($this->root);
        if ($checkOnly || version_compare($latest, $current, '<=')) {
            return $this->status($current, $latest);
        }

        $lock = @fopen($this->lockPath, 'c');
        if ($lock === false) throw new RuntimeException('Could not open the framework update lock.');
        try {
            if (!flock($lock, LOCK_EX | LOCK_NB)) throw new RuntimeException('Another framework update holds the lock.');
            $current = FrameworkVersion::current($this->root);
            if (version_compare($latest, $current, '<=')) return $this->status($current, $latest);

            $plan = $this->plan($this->releases->tree($current), $this->releases->tree($tag));
            $this->apply($plan, $tag, $latest);
            $count = count($plan);
            return "Updated AWT from {$current} to {$latest}. Applied {$count} release file changes.";
        } finally {
            fclose($lock);
        }
    }

    private function status(string $current, string $latest): string
    {
        $status = match (version_compare($latest, $current)) {
            1 => 'An update is available. Run php awt framework_update to apply it.',
            0 => 'AWT is up to date.',
            -1 => 'This installation is newer than the latest GitHub release. Skipping the update.',
        };
        return "Installed AWT {$current}. Latest GitHub release {$latest}. {$status}";
    }

    private function plan(array $previous, array $next): array
    {
        $base = $this->index($previous);
        $target = $this->index($next);
        $plan = [];
        foreach (array_keys($base + $target) as $path) {
            $path = (string) $path;
            $old = $base[$path] ?? null;
            $new = $target[$path] ?? null;
            if (($old['type'] ?? 'tree') === 'tree' && ($new['type'] ?? 'tree') === 'tree') continue;
            if (($old['sha'] ?? null) === ($new['sha'] ?? null) && ($old['mode'] ?? null) === ($new['mode'] ?? null)) continue;

            $protected = in_array(strtolower(explode('/', $path, 2)[0]), ['awt_packages', 'awt_data'], true);
            if ($protected && ($old !== null || ($new['type'] ?? null) !== 'blob')) continue;
            foreach ([$old, $new] as $entry) {
                if ($entry !== null && !in_array($entry['mode'], ['100644', '100755'], true)) {
                    throw new RuntimeException("Unsupported release file type or file/directory transition: {$path}.");
                }
            }

            $file = $this->root . '/' . $path;
            if (!$this->safeParents($path) || is_link($file) || (file_exists($file) && !is_file($file))) {
                if ($protected) continue;
                throw new RuntimeException("A local path conflicts with the framework update: {$path}.");
            }
            if ($protected && is_file($file)) continue;

            $current = $this->fingerprint($file);
            if ($current === null && $new === null) continue;
            $mode = $new === null ? null : $this->permissions($file, $new['mode']);
            if ($new !== null && $current === $new['sha'] && (fileperms($file) & 0777) === $mode) continue;
            if ($current !== null && $current !== ($old['sha'] ?? null) && $current !== ($new['sha'] ?? null)) {
                throw new RuntimeException("Local framework file differs from both releases: {$path}. Back up and reconcile it before retrying.");
            }
            $plan[$path] = ['hash' => $current, 'target' => $new['sha'] ?? null, 'mode' => $mode];
        }
        return $plan;
    }

    private function index(array $tree): array
    {
        $index = [];
        foreach ($tree as $entry) {
            $path = $entry['path'] ?? '';
            if (!is_string($path) || $path === '' || preg_match('~[\\\\\x00-\x1f:]|^/|//|/$|(?:^|/)\.{1,2}(?:/|$)~', $path)) {
                throw new RuntimeException('GitHub returned an invalid framework release path.');
            }
            $segments = explode('/', strtolower($path));
            if (in_array('.git', $segments, true) || strtolower($path) === FrameworkVersion::FILE) continue;
            $root = $segments[0];
            if (count($segments) > 1 && !in_array($root, ['awt_src', 'bootstrap', 'public', 'awt_data', 'awt_packages'], true)) continue;
            if (count($segments) === 1 && in_array($root, ['awt_src', 'bootstrap', 'public', 'awt_data', 'awt_packages'], true)) {
                if (($entry['type'] ?? null) !== 'tree') throw new RuntimeException("Expected a framework directory: {$path}.");
                continue;
            }
            if (isset($index[$path]) || !is_string($entry['sha'] ?? null) || !preg_match('/^[a-f0-9]{40}$/D', $entry['sha'])
                || !is_string($entry['type'] ?? null) || !is_string($entry['mode'] ?? null)) {
                throw new RuntimeException("GitHub returned an invalid framework release entry: {$path}.");
            }
            $index[$path] = $entry;
        }
        return $index;
    }

    private function apply(array $plan, string $tag, string $version): void
    {
        $marker = $this->root . '/' . FrameworkVersion::FILE;
        if (is_link($marker) || (file_exists($marker) && !is_file($marker))) {
            throw new RuntimeException('The framework version marker has an unexpected file type.');
        }
        $stage = sys_get_temp_dir() . '/awt-framework-update-' . bin2hex(random_bytes(12));
        if (!mkdir($stage, 0700)) throw new RuntimeException('Could not create the framework update staging directory.');

        $undo = [];
        $directories = [];
        $preserveStage = false;
        try {
            foreach ($plan as $path => &$change) {
                if ($change['target'] === null) continue;
                $bytes = $this->releases->file($tag, (string) $path);
                if (self::blobHash($bytes) !== $change['target']) {
                    throw new RuntimeException("Downloaded file does not match the GitHub release: {$path}.");
                }
                $change['source'] = $this->stageFile($stage, $bytes);
            }
            unset($change);
            $plan[FrameworkVersion::FILE] = [
                'hash' => $this->fingerprint($marker),
                'source' => $this->stageFile($stage, $version . PHP_EOL),
                'mode' => 0644,
            ];

            foreach ($plan as $path => $change) {
                $path = (string) $path;
                $file = $this->root . '/' . $path;
                clearstatcache(true, $file);
                if (!$this->safeParents($path) || is_link($file) || (file_exists($file) && !is_file($file))
                    || $this->fingerprint($file) !== $change['hash']) {
                    throw new RuntimeException("A local file changed while preparing the update: {$path}.");
                }
                $backup = null;
                $mode = null;
                if (is_file($file)) {
                    $backup = $stage . '/backup-' . count($undo);
                    $mode = fileperms($file) & 0777;
                    if (!copy($file, $backup)) throw new RuntimeException("Could not back up {$path}.");
                }
                if (isset($change['source'])) {
                    $this->createParents(dirname($file), $directories);
                    $this->replace($file, $change['source'], $change['mode']);
                } elseif (!unlink($file)) {
                    throw new RuntimeException("Could not remove {$path}.");
                }
                $undo[] = [$file, $backup, $mode];
            }
        } catch (Throwable $error) {
            foreach (array_reverse($undo) as [$file, $backup, $mode]) {
                try {
                    if ($backup !== null) $this->replace($file, $backup, $mode);
                    elseif (!unlink($file)) $preserveStage = true;
                } catch (Throwable) {
                    $preserveStage = true;
                }
            }
            foreach (array_reverse($directories) as $directory) {
                if (!@rmdir($directory)) $preserveStage = true;
            }
            if ($preserveStage) {
                throw new RuntimeException("Framework update rollback was incomplete. Backups remain in {$stage}.", 0, $error);
            }
            throw $error;
        } finally {
            if (!$preserveStage) {
                foreach (new \FilesystemIterator($stage) as $file) unlink($file->getPathname());
                rmdir($stage);
            }
        }
    }

    private function stageFile(string $stage, string $bytes): string
    {
        $path = $stage . '/release-' . bin2hex(random_bytes(12));
        if (file_put_contents($path, $bytes) !== strlen($bytes)) throw new RuntimeException('Could not stage the framework release file.');
        return $path;
    }

    private function replace(string $file, string $source, int $mode): void
    {
        $temporary = dirname($file) . '/.awt-update-' . bin2hex(random_bytes(12));
        try {
            if (!copy($source, $temporary) || !chmod($temporary, $mode) || !rename($temporary, $file)) {
                throw new RuntimeException("Could not replace {$file}.");
            }
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }

    private function safeParents(string $path): bool
    {
        $segments = explode('/', $path);
        array_pop($segments);
        $parent = $this->root;
        foreach ($segments as $segment) {
            $parent .= '/' . $segment;
            clearstatcache(true, $parent);
            if (is_link($parent) || (file_exists($parent) && !is_dir($parent))) return false;
        }
        return true;
    }

    private function createParents(string $directory, array &$created): void
    {
        if (is_dir($directory)) return;
        $this->createParents(dirname($directory), $created);
        if (!mkdir($directory, 0755)) throw new RuntimeException("Could not create {$directory}.");
        $created[] = $directory;
    }

    private function permissions(string $file, string $gitMode): int
    {
        if (!is_file($file)) return $gitMode === '100755' ? 0755 : 0644;
        $mode = fileperms($file) & 0777;
        return $gitMode === '100755' ? $mode | (($mode & 0444) >> 2) : $mode & ~0111;
    }

    private function fingerprint(string $path): ?string
    {
        if (!is_file($path)) return null;
        $bytes = file_get_contents($path);
        if ($bytes === false) throw new RuntimeException("Could not read {$path}.");
        return self::blobHash($bytes);
    }

    private static function blobHash(string $bytes): string
    {
        return sha1('blob ' . strlen($bytes) . "\0" . $bytes);
    }
}
