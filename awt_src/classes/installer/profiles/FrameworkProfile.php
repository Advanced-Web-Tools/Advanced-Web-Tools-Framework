<?php

namespace installer\profiles;

final class FrameworkProfile implements IProfile
{
    public function id(): string { return 'framework'; }
    public function name(): string { return 'AWT Framework'; }
    public function description(): string { return 'A lightweight, modular framework built for custom web applications.'; }
    public function icon(): string { return 'fa-code'; }
    public function requiresAdmin(): bool { return false; }
    public function packages(): array { return []; }
}
