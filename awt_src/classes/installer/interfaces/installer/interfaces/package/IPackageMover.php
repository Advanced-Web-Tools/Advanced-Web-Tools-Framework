<?php

namespace installer\interfaces\package;

interface IPackageMover
{
    /**
     * Moves all files and directories from the source to the destination recursively.
     * @return void
     */
    public function move(): void;

    /**
     * Returns the source directory path.
     * @return string
     */
    public function getSource(): string;

    /**
     * Returns the destination directory path.
     * @return string
     */
    public function getDestination(): string;

    /**
     * Sets the source directory path.
     * @param string $source
     * @return void
     */
    public function setSource(string $source): void;


    /**
     * Sets the destination directory path.
     * @param string $destination
     * @return void
     */
    public function setDestination(string $destination): void;
}