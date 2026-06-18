<?php

namespace App\Services;

use Illuminate\Contracts\Config\Repository as ConfigRepository;

class Setting
{
    protected ConfigRepository $config;

    public function __construct(ConfigRepository $config)
    {
        $this->config = $config;
    }

    public function logoUrl(): string
    {
        $logoPath = $this->config->get('setting.logo', 'assets/images/logo.png');

        return $this->assetUrl($logoPath);
    }

    public function urlForPath(string $type, string $fileName): string
    {
        $basePath = $this->config->get("setting.paths.{$type}");

        if (! $basePath) {
            $basePath = $this->defaultPathForType($type);
        }

        $path = rtrim($basePath, '/') . '/' . ltrim($fileName, '/');

        return $this->assetUrl($path);
    }

    public function get(string $key, $default = null)
    {
        return $this->config->get("setting.{$key}", $default);
    }

    protected function defaultPathForType(string $type): string
    {
        return match ($type) {
            'image' => 'assets/images',
            default => 'assets/' . trim($type, '/'),
        };
    }

    protected function assetUrl(string $path): string
    {
        return asset($path);
    }
}
