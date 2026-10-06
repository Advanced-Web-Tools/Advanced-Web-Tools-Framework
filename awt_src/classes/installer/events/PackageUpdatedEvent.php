<?php
namespace installer\events;

/** An existing package has received new files and metadata. */
class PackageUpdatedEvent extends PackageInstalledEvent
{
    public function getName(): string { return 'package.updated'; }
}
