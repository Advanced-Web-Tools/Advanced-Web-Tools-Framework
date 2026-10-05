<?php

namespace installer\interfaces\package;

interface IVerifyManifest
{
    /**
     * Adds a key to the verification list, e.g. "name"
     * @param string $key
     * @return self
     */
    public function addKey(string $key): self;

    /**
     * Adds a key and its type to the verification list, e.g. "name" => "string"
     * @param string $key
     * @param string $type
     * @return self
     */
    public function addKeyWithType(string $key, string $type): self;

    /**
     * Returns true if all registered keys exist in the manifest.
     * If a type is specified, it will also check if the value is of the specified type.
     * @return bool
     */
    public function verify(): bool;
}