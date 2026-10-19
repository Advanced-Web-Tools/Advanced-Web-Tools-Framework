<?php

namespace installer\profiles;

final class CmsProfile implements IProfile
{
    public const REPOSITORY_URL = 'https://raw.githubusercontent.com/Advanced-Web-Tools/AWT-CMS-Bundle/main/repo.json';

    public function id(): string { return 'cms'; }
    public function name(): string { return 'AWT CMS'; }
    public function description(): string { return 'A complete content management system with a dashboard, settings, account management, themes, extensions, a visual page editor, and more.'; }
    public function icon(): string { return 'fa-layer-group'; }
    public function requiresAdmin(): bool { return true; }
    public function packages(): array
    {
        return [(new CmsReleaseRepository())->releaseUrl(self::REPOSITORY_URL, AWT_VERSION)];
    }
}
