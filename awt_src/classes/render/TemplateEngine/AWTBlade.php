<?php

namespace render\TemplateEngine;

use router\http\GlobalsRequestProvider;
use router\http\HttpMethod;
use router\interface\ICsrfTokenManager;
use router\interface\IMethodPolicy;
use router\interface\IRequestProvider;
use router\security\Csrf;
use vfs\resource\exceptions\ResourceException;
use vfs\resource\PublicResource;
use vfs\resource\Resource;

class AWTBlade extends BladeOne
{
    protected array $contentTags = ['{{', '}}', '%%'];
    protected string $packageName = '';
    private ?string $templatePackage = null;

    public function __construct(
        $templatePath = null,
        $compiledPath = null,
        $mode = 0,
        $commentMode = 0,
        private readonly ICsrfTokenManager $csrfTokens = new Csrf(),
        private readonly IRequestProvider $requests = new GlobalsRequestProvider(),
        private readonly IMethodPolicy $httpMethods = new HttpMethod()
    ) {
        parent::__construct($templatePath, $compiledPath, $mode, $commentMode);
    }

    public static function getInstance($templatePath = null, $compiledPath = null, $mode = 0, $commentMode = 0): static
    {
        if (isset(self::$instance) && self::$instance instanceof static) {
            return self::$instance;
        }

        return new static($templatePath, $compiledPath, $mode, $commentMode);
    }

    public function setPackageName(string $packageName): self
    {
        $this->packageName = $packageName;
        return $this;
    }

    protected function runInternal(string $view, $variables = [], $forced = false, $runFast = false): string
    {
        $previousPackage = $this->templatePackage;
        $packageRoot = defined('PACKAGES') ? rtrim(PACKAGES, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR : null;
        if ($packageRoot !== null && str_starts_with($view, $packageRoot)) {
            $this->templatePackage = explode(DIRECTORY_SEPARATOR, substr($view, strlen($packageRoot)), 2)[0];
        } elseif (!str_contains($view, '/') && str_contains($view, '.')) {
            $this->templatePackage = explode('.', $view, 2)[0];
        }

        try {
            return parent::runInternal($view, $variables, $forced, $runFast);
        } finally {
            $this->templatePackage = $previousPackage;
        }
    }

    protected function compileComponent($expression): string
    {
        return parent::compileComponent('(' . $this->compileTemplateArguments($expression) . ')');
    }

    public function startComponent($name, array $data = []): void
    {
        parent::startComponent($this->resolveTemplateReference($name), $data);
    }

    protected function compileExtends($expression): string
    {
        $arguments = $this->compileTemplateArguments($expression);
        return parent::compileExtends('(...$this->resolveLayoutArguments(' . $arguments . '))');
    }

    protected function compileExtend($expression): string
    {
        return $this->compileExtends($expression);
    }

    protected function resolveLayoutArguments(string $view, $variables = []): array
    {
        return [$this->resolveTemplateReference($view), $variables];
    }

    private function compileTemplateArguments(?string $expression): string
    {
        $arguments = trim($this->stripParentheses($expression));

        // Quote a bare reference at the start and leave the remaining Blade arguments as PHP.
        return preg_replace_callback(
            '~^[\w.-]+(?::[\w.-]+(?:/[\w.-]+)*)?(?=\s*(?:,|$))~',
            static fn(array $match): string => var_export($match[0], true),
            $arguments
        );
    }

    private function resolveTemplateReference(string $reference): string
    {
        if (str_starts_with($reference, '/') || preg_match('~^[A-Za-z]:[/\\\\]~', $reference)) {
            return $reference;
        }

        $qualified = str_contains($reference, ':');
        $hasExtension = str_ends_with($reference, $this->fileExtension);
        if (!$qualified && (str_contains($reference, '/') || (!$hasExtension && str_contains($reference, '.')))) {
            return $reference; // Preserve legacy literal and dotted template paths.
        }

        $package = $qualified ? explode(':', $reference, 2)[0] : ($this->templatePackage ?? $this->packageName);
        if ($package === '') {
            return $reference;
        }

        $alias = $hasExtension ? $reference : $reference . $this->fileExtension;
        return (new Resource($package))->get($alias, $qualified) ?? $reference;
    }

    /**
     * Get the full path of the compiled file.
     *
     * @param string $templateName
     * @return string
     */
    public function getCompiledFile($templateName = ''): string
    {
        $templateName = (empty($templateName)) ? $this->fileName : $templateName;
        $fullPath = $this->getTemplateFile($templateName);
        if ($fullPath == '') {
            throw new \RuntimeException('Template not found: ' . ($this->mode == self::MODE_DEBUG ? $this->templatePath[0] . '/' . $templateName : $templateName));
        }
        $style = $this->compileTypeFileName;
        if ($style === 'auto') {
            $style = 'sha1';
        }
        // Recompile templates that previously called the static CSRF helper.
        $cacheKey = $fullPath . '|csrf-instance-v1';
        $hash = $style === 'md5' ? \md5($cacheKey) : \sha1($cacheKey);
        return $this->compiledPath . '/' . basename($templateName) . '_' . $hash . $this->compileExtension;
    }

    /**
     * Get the full path of the template file.
     * <p>Example: getTemplateFile('.abc.def')</p>
     *
     * @param string $templateName template name. If not template is set then it uses the base template.
     * @return string
     */
    public function getTemplateFile($templateName = ''): string
    {
        $templateName = (empty($templateName)) ? $this->fileName : $templateName;
        if (str_contains($templateName, '/') || str_contains($templateName, DIRECTORY_SEPARATOR)) {
            return $this->locateTemplate($templateName); // it's a literal
        }
        $arr = \explode('.', $templateName);
        $c = \count($arr);
        if ($c == 1) {
            // it's in the root of the template folder.
            return $this->locateTemplate($templateName . $this->fileExtension);
        }
        $file = $arr[$c - 1];
        \array_splice($arr, $c - 1, $c - 1); // delete the last element
        $path = \implode('/', $arr);

        return $this->locateTemplate(PACKAGES . $path . '/' . $file . $this->fileExtension);
    }

    /**
     * Find template file with the given name in all template paths in the order the paths were written
     *
     * @param string $name Filename of the template (without path)
     * @return string template file
     */
    protected function locateTemplate($name): string
    {
        if (is_file($name)) {
            return $name;
        }
        $this->notFoundPath = '';
        foreach ($this->templatePath as $dir) {
            $path = $dir . DIRECTORY_SEPARATOR . $name;
            if (\is_file($path)) {
                return $path;
            }
            $this->notFoundPath .= $path . ",";
        }

        var_dump($this->templatePath);
        return '';
    }

    /**
     * Determine if the view has expired.
     *
     * @param string|null $fileName
     * @return bool
     */
    public function isExpired($fileName): bool
    {
        $compiled = $this->getCompiledFile($fileName);
        $template = $this->getTemplateFile($fileName);
        if (!\is_file($template)) {
            if ($this->mode == self::MODE_DEBUG) {
                $this->showError('Read file', 'Template not found :' . $this->fileName . " on file: $template", true);
            } else {
                $this->showError('Read file', 'Template not found :' . $this->fileName . "in path", true);
            }
        }
        // If the compiled file doesn't exist we will indicate that the view is expired
        // so that it can be re-compiled. Else, we will verify the last modification
        // of the views or compiler is newer than the compiled views.
        if (!$this->compiledPath || !\is_file($compiled)) {
            return true;
        }
        return \filemtime($compiled) < max(
            \filemtime($template),
            \filemtime(__FILE__),
            \filemtime(__DIR__ . '/BladeOne.php')
        );
    }

    /**
     * Returns the current token. if there is not a token then it generates a new one.
     * It could require an open session.
     *
     * @param bool   $fullToken It returns a token with the current ip.
     * @param string $tokenId   [optional] Name of the token.
     *
     * @return string
     */
    public function getCsrfToken($fullToken = false, $tokenId = '_token'): string
    {
        $this->csrf_token = $this->csrfTokens->token($tokenId);
        return $fullToken ? $this->csrf_token . '|' . $this->ipClient() : $this->csrf_token;
    }

    /** Regenerate the shared session token explicitly (invalidates existing forms). */
    public function regenerateToken($tokenId = '_token'): void
    {
        $this->csrf_token = $this->csrfTokens->regenerate($tokenId);
    }

    /** Validate unsafe requests before optionally rotating the shared token. */
    public function csrfIsValid($alwaysRegenerate = false, $tokenId = '_token'): bool
    {
        $request = $this->requests->current();
        if (!$this->httpMethods->isSafe($request->method())
            && !$this->csrfTokens->validateToken($request->csrfToken($tokenId), $tokenId)) {
            return false;
        }
        if ($alwaysRegenerate) {
            $this->regenerateToken($tokenId);
        } else {
            $this->getCsrfToken(false, $tokenId);
        }
        return true;
    }

    protected function compilecsrf($expression = null): string
    {
        $expression = empty($expression) ? "'_token'" : $this->stripParentheses($expression);
        return "<input type='hidden' name='<?= htmlspecialchars($expression, ENT_QUOTES, 'UTF-8') ?>' value='<?= htmlspecialchars(\$this->getCsrfToken(false, $expression), ENT_QUOTES, 'UTF-8') ?>'/>";
    }

    protected function compileAsset($expression): string
    {
        $arguments = $this->compileTemplateArguments($expression);
        return $this->phpTagEcho . '$this->assetUrl(' . $arguments . '); ?>';
    }

    protected function assetUrl(string $reference): string
    {
        if (preg_match('~^[^:/?#]+:(?!//)~', $reference)) {
            return $this->resourceUrl($reference, true);
        }

        $package = $this->templatePackage ?? $this->packageName;
        if ($package !== '' && !str_contains($reference, '/')) {
            $url = $this->resourceUrl($package . ':' . $reference);
            if ($url !== '') {
                return $url;
            }
        }

        $reference = ltrim($reference, '/');
        $base = trim($this->assetDict[0] ?? '', '/');
        $host = rtrim(HOSTNAME, '/');

        if ($package !== '' && $package !== $this->packageName) {
            // Layouts and includes can belong to a different package from the controller's view.
            $path = rawurldecode(substr($reference, 0, strcspn($reference, '?#')));
            foreach (['views/assets/', 'assets/', ''] as $directory) {
                $file = PACKAGES . $package . '/' . $directory . $path;
                if (!str_contains($path, "\0") && PublicResource::allows($package, $file)) {
                    return self::e($host . '/awt_packages/' . rawurlencode($package) . '/' . $directory . $reference);
                }
            }
        }

        return self::e($host . '/' . ($base === '' ? '' : $base . '/') . $reference);
    }

    protected function compileData($expression): string
    {
        $expression = str_replace("('", "", $expression);
        $expression = str_replace("')", "", $expression);
        $expression = str_replace("'", "", $expression);

        $args = explode(',', $expression);

        $file = $args[0];
        $type = $args[1];

        $path = HOSTNAME . "/awt_data/media/packages/$type/" . $this->packageName . "/$file";

        return $this->phpTagEcho . "'$path'; ?>";
    }

    protected function compileResource($expression): string
    {
        return $this->phpTagEcho . '$this->resourceUrl' . $expression . '; ?>';
    }

    /** Resolve a Resource alias at render time using this template's package context. */
    public function resourceUrl(string $alias, bool $must = false): string
    {
        $context = $this->packageName;
        if (str_contains($alias, ':')) {
            $context = explode(':', $alias, 2)[0];
        }
        $resource = new Resource($context);
        $path = $resource->get($alias, $must);
        if ($path === null) {
            return '';
        }

        $root = rtrim(PACKAGES, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if (!str_starts_with($path, $root)) {
            throw new ResourceException('Resource is outside the package root.');
        }
        $relative = substr($path, strlen($root));
        $segments = explode(DIRECTORY_SEPARATOR, $relative);
        $url = rtrim(HOSTNAME, '/') . '/awt_packages/' . implode('/', array_map('rawurlencode', $segments));
        return self::e($url);
    }

    protected function compileUrl($expression): string
    {
        $expression = str_replace("('", "", $expression);
        $expression = str_replace("')", "", $expression);

        $path = HOSTNAME . "/$expression";
        return $this->phpTagEcho . "'$path'; ?>";
    }
}
