<?php

namespace vfs\resource;

use object\ObjectCollection;
use vfs\resource\enums\EResourceType;

class ResourceEntry
{
    public string $alias;
    public string $path;
    public EResourceType $type;
    public ObjectCollection $children;
    public string $middleware;

    public function __construct(string $alias, string $path, EResourceType $type, string $middleware = "")
    {
        $this->alias      = $alias;
        $this->path       = $path;
        $this->type       = $type;
        $this->middleware = $middleware;
        $this->children   = new ObjectCollection();
        $this->children->setStrictType(ResourceEntry::class);
    }

    public function addChild(ResourceEntry $child): void
    {
        $this->children->add($child);
    }

    public function __toArray(): array
    {
        $children = [];
        foreach ($this->children->toArray() as $child) {
            $children[] = $child->__toArray();
        }

        return [
            'alias'      => $this->alias,
            'path'       => $this->path,
            'type'       => $this->type->name,
            'middleware' => $this->middleware,
            'children'   => $children,
        ];
    }

    public function __toString(): string
    {
        return $this->path;
    }
}