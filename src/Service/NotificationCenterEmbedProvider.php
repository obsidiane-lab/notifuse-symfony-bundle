<?php

namespace Notifuse\SymfonyBundle\Service;

final class NotificationCenterEmbedProvider
{
    public function __construct(
        private readonly string $notificationCenterUrl,
        private readonly string $workspaceId,
        private readonly string $defaultLocale,
        private readonly array $embedConfig = []
    ) {
    }

    public function getEmbedScriptUrl(array $query = []): string
    {
        $path = $this->normalizePath($this->embedConfig['embed_path'] ?? '/notification-center');
        $baseUrl = rtrim($this->notificationCenterUrl, '/');

        if (!$query) {
            return $baseUrl . $path;
        }

        return sprintf('%s%s?%s', $baseUrl, $path, http_build_query($query));
    }

    public function renderScriptTag(array $context = [], string $elementId = null): string
    {
        $elementId = $elementId ?? ($this->embedConfig['script_element_id'] ?? 'notifuse-notification-center');
        $query = array_merge(
            [
                'workspace_id' => $this->workspaceId,
                'locale' => $context['locale'] ?? $this->defaultLocale,
            ],
            $context['query'] ?? []
        );

        $attributes = array_merge(
            [
                'data-workspace-id' => $this->workspaceId,
                'data-locale' => $query['locale'],
                'src' => $this->getEmbedScriptUrl($query),
            ],
            $context['attributes'] ?? []
        );

        $attributeString = $this->buildAttributeString($attributes);
        $containerId = htmlspecialchars($elementId, ENT_QUOTES);

        return sprintf('<div id="%s"></div><script %s></script>', $containerId, $attributeString);
    }

    private function normalizePath(string $path): string
    {
        if (!$path) {
            return '/';
        }

        return '/' . ltrim($path, '/');
    }

    private function buildAttributeString(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            $parts[] = sprintf('%s="%s"', htmlspecialchars((string) $name, ENT_QUOTES), htmlspecialchars((string) $value, ENT_QUOTES));
        }

        return implode(' ', $parts);
    }
}
