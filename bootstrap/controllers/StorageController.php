<?php

namespace bootstrap\controllers;

use controller\Controller;
use response\Response;
use vfs\cache\Cache;
use vfs\cache\enums\ECacheValidation;
use vfs\resource\Resource;
use vfs\storage\StorageRepository;

class StorageController extends Controller
{

    /**
     * @inheritDoc
     */
    public function index(array|string $params): Response
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $cache = new Cache();
        $cachePool = $cache->pool('storage_request');
        $cached = $cachePool->getCache($uri);

        if (is_array($cached)) {
            $storageEntry = $cached[0];
            return Response::make(200)->file($storageEntry);
        } else {
            $storageRepository = new StorageRepository();
            try {
                $storageEntry = $storageRepository->fetchById($params['id']);
            } catch (\Exception $e) {
                return Response::make(404);
            }

            $path = $storageEntry->getPath();

            $cachePool->createConfig(ECacheValidation::MODIFIED, [str_replace(basename($path), '', $path)]);
            $cachePool->setCache($uri, [$path]);

            return Response::make(200)->file($path);
        }
    }

    public function Resource(array|string $params): Response
    {
        // 1. Check the cache first using the URI
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $cache = new Cache();
        $cachePool = $cache->pool('resource_request');

        $cached = $cachePool->getCache($uri);

        if (is_array($cached)) {
            // Cache hit: Use the stored path
            $resourceEntry = $cached[0];
        } else {
            $package = $params["package"] ?? 'System';
            $file = $params["file"] ?? '';

            $resource = new Resource($package);
            $resourceEntry = $resource->get($file);

            // If it doesn't exist, return 404 immediately (and don't cache a 404 here)
            if ($resourceEntry === null) {
                return Response::make(404);
            }

            $cachePool->createConfig(ECacheValidation::MODIFIED, [str_replace($file, '', $resourceEntry)])
                ->setCache($uri, [$resourceEntry]);
        }

        // 2. Return the file response
        return Response::make(200)->file($resourceEntry);
    }
}