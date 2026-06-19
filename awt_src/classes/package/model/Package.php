<?php

namespace package\model;

use JsonException;
use model\exceptions\ModelCreationException;
use model\exceptions\ModelCRUDException;
use model\Model;
use object\ObjectCollection;
use package\dependency\Dependency;

class Package extends Model
{
    // Core identification
    public string $name = "";
    public string $author = "";
    public ?string $description = null;
    public ?string $icon = null;
    public ?string $preview_image = null;

    // Version and compatibility
    public string $version = "0.0.0";
    public string $minimum_awt_version = "0.0.0";
    public ?string $maximum_awt_version = null;

    // Status and type
    public int $type = -1;
    public bool $system_package = false;

    // License
    public ?string $license = null;
    public ?string $license_url = null;

    // Dependencies
    public array|string $dependencies = [];
    public ?ObjectCollection $dependenciesCollection;

    /**
     * @throws ModelCreationException
     */
    public function __construct(?int $id = null)
    {
        $this->dependenciesCollection = new ObjectCollection();
        $this->dependenciesCollection->setStrictType(Dependency::class)->setKey("name");
        $this->paramBlackList("dependenciesCollection");

        if ($id !== null) {
            $this->selectByID($id, "awt_package");
        }

        parent::__construct();

    }

    // Getters
    public function getName(): string
    {
        return $this->name;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function getPreviewImage(): ?string
    {
        return $this->preview_image;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getMinimumAwtVersion(): string
    {
        return $this->minimum_awt_version;
    }

    public function getMaximumAwtVersion(): ?string
    {
        return $this->maximum_awt_version;
    }

    public function getType(): int
    {
        return $this->type;
    }

    public function getSystem(): bool
    {
        return $this->system_package;
    }

    public function getLicense(): ?string
    {
        return $this->license;
    }

    public function getLicenseUrl(): ?string
    {
        return $this->license_url;
    }

    public function getDependencies(): ?array
    {
        return $this->dependencies;
    }

    public function getDependenciesCollection(): ?ObjectCollection
    {
        return $this->dependenciesCollection;
    }

    // Setters
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function setIcon(?string $icon): void
    {
        $this->icon = $icon;
    }

    public function setPreviewImage(?string $preview_image): void
    {
        $this->preview_image = $preview_image;
    }

    public function setVersion(string $version): void
    {
        $this->version = $version;
    }

    public function setMinimumAwtVersion(string $minimum_awt_version): void
    {
        $this->minimum_awt_version = $minimum_awt_version;
    }

    public function setMaximumAwtVersion(?string $maximum_awt_version): void
    {
        $this->maximum_awt_version = $maximum_awt_version;
    }

    public function setType(int $type): void
    {
        $this->type = $type;
    }

    public function setSystem(bool $system_package): void
    {
        $this->system_package = $system_package;
    }

    public function setLicense(?string $license): void
    {
        $this->license = $license;
    }

    public function setLicenseUrl(?string $licenseUrl): void
    {
        $this->license_url = $licenseUrl;
    }

    public function setDependencies(?array $dependencies): void
    {
        if (!is_array($dependencies)) {
            $dependencies = [];
        }


        $this->dependencies = $dependencies;
    }


    public function createDependencyCollection(): void
    {
        foreach ($this->dependencies as $dependency) {
            $this->dependenciesCollection->add(Dependency::fromArray($dependency));
        }
    }

    /**
     * @throws JsonException
     */
    private function encodeDependencies(): void
    {
        if (is_array($this->dependencies)) {
            $this->dependencies = json_encode($this->dependencies, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
    }

    /**
     * @throws ModelCRUDException
     * @throws JsonException
     */
    public function saveModel(): int|null
    {
        $this->encodeDependencies();
        return parent::saveModel();
    }

    /**
     * @throws ModelCRUDException
     * @throws JsonException
     */
    public function save(): bool
    {
        $this->encodeDependencies();
        return parent::save();
    }


}