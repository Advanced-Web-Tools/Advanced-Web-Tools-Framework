<?php

namespace vfs;

use object\ObjectCollection;
use vfs\enums\EVFSType;

class VFSEntry {
    public string $alias;
    public string $path;
    public EVFSType $type;
    public ObjectCollection $children;
    public string $middleware;
    public string $hash;

    public function __construct(string $alias, string $path, EVFSType $type, string $middleware = "")
    {
        $this->alias = $alias;
        $this->path = $path;
        $this->type = $type;
        $this->children = new ObjectCollection();
        $this->children->setStrictType(VFSEntry::class);
        $this->middleware = $middleware;
        $this->hash = hash_file('SHA1', $this->path);
    }

    public function addChild(VFSEntry $child): void
    {
        $this->children->add($child);
    }

    public function __toArray(): array
    {
        $children = $this->children->toArray();

        $return = [];

        foreach ($children as $child) {
            $return[] = $child->__toArray();
        }

        return [
            'alias' => $this->alias,
            'path' => $this->path,
            'type' => $this->type,
            'children' => $return,
            'middleware' => $this->middleware,
            'hash' => $this->hash
        ];
    }

    public function __toString(): string
    {
        return $this->path;
    }

}