<?php

namespace bootstrap\controllers;

use controller\Controller;
use response\Response;
use vfs\resource\PublicResource;
use vfs\storage\StorageAccess;
use vfs\storage\StorageAccessDenied;
use vfs\storage\StorageRepository;

class StorageController extends Controller
{
    public string $controllerName = 'DefaultStorageController';

    public function index(array|string $params): Response
    {
        if (!is_array($params) || !isset($params['id']) || !ctype_digit((string) $params['id'])) return Response::make(404);
        try {
            // Always hydrate current metadata and authorize before serving, including database cache hits.
            $entry = (new StorageRepository())->fetchById((int) $params['id']);
            StorageAccess::authorize($entry);
        } catch (\OutOfBoundsException $e) {
            return Response::make(404);
        } catch (StorageAccessDenied $e) {
            return Response::make(403);
        }
        $path = $entry->getPath();
        if (!is_file($path) || !is_readable($path)) return Response::make(404);
        return $this->fileResponse($path);
    }

    public function Resource(array|string $params): Response
    {
        if (!is_array($params)) return Response::make(404);
        $package = $params['package'] ?? 'System';
        if (!is_string($package) || !preg_match('/^[a-zA-Z0-9_.-]+$/D', $package) || $package === '.' || $package === '..') return Response::make(404);
        $segments = [];
        for ($i = 0; isset($params['resource' . $i]); $i++) $segments[] = rawurldecode($params['resource' . $i]);
        $segments[] = rawurldecode($params['file'] ?? '');
        $relative = implode('/', $segments);
        // Resolve the exact URL path; filename-only recursive lookup is reserved for internal callers.
        $directory = rtrim(PACKAGES, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $package;
        $path = $directory . DIRECTORY_SEPARATOR . $relative;
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..' || str_contains($segment, '\\') || str_contains($segment, "\0")) return Response::make(404);
        }
        if (!PublicResource::allows($package, $path)) return Response::make(404);
        return $this->fileResponse($path);
    }

    private function fileResponse(string $path): Response
    {
        $response = Response::make(200)->file($path)->header('X-Content-Type-Options', 'nosniff');
        // Unknown extensions are binary files, regardless of the request Accept header.
        if ((new \response\resolvers\MimeResolver())->fromPath($path) === null) $response->asFile();
        return $response;
    }
}
