<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Crypt;

class ConfigService
{
    protected string $configDir;
    protected string $configPath;

    public function __construct()
    {
        $this->configDir = rtrim(getenv('HOME') ?: getenv('USERPROFILE') ?: base_path(), '/') . '/.db-backup';
        $this->configPath = $this->configDir . '/config.json';
    }

    public function getConfigPath(): string
    {
        return $this->configPath;
    }

    public function ensureConfigFileExists(): void
    {
        if (!File::exists($this->configDir)) {
            File::makeDirectory($this->configDir, 0755, true);
        }

        if (!File::exists($this->configPath)) {
            File::put($this->configPath, json_encode(['profiles' => new \stdClass()], JSON_PRETTY_PRINT));
        }
    }

    public function loadProfiles(): array
    {
        $this->ensureConfigFileExists();
        $data = json_decode(File::get($this->configPath), true);
        $profiles = $data['profiles'] ?? [];
        return $profiles;
    }

    public function saveProfiles(array $profiles): void
    {
        $data = ['profiles' => $profiles];
        File::put($this->configPath, json_encode($data, JSON_PRETTY_PRINT));
    }

    public function getProfile(string $name): ?array
    {
        $profiles = $this->loadProfiles();
        return $profiles[$name] ?? null;
    }
}
