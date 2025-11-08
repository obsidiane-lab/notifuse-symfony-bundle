# Notifuse Symfony Bundle

This bundle wraps the Notifuse API and its public notification center into your Symfony application while keeping the HTTP configuration and URLs fully configurable.

## Installation

Require the bundle with Composer (from your application root):

```bash
composer require notifuse/notifuse-symfony-bundle
```

Then enable the bundle in your kernel if you are not using flex auto-registration:

```php
return [
    // ...
    Notifuse\SymfonyBundle\NotifuseBundle::class => ['all' => true],
];
```

## Configuration

```yaml
notifuse:
  api_base_url: '%env(NOTIFUSE_API_BASE_URL)%'
  notification_center_url: '%env(NOTIFUSE_NOTIFICATION_CENTER_URL)%'
  workspace_id: '%env(NOTIFUSE_WORKSPACE_ID)%'
  workspace_api_key: '%env(NOTIFUSE_API_KEY)%'
  default_locale: 'fr'
  http_client_options:
    timeout: 8.0
    max_redirects: 3
    verify_peer: true
    headers:
      X-Client-Name: 'my-app'
  notification_center:
    embed_path: '/notification-center'
    script_element_id: 'notifuse-notification-center'
```

The configuration values are passed to the `Notifuse\SymfonyBundle\Service\ApiClient` and `Notifuse\SymfonyBundle\Service\NotificationCenterEmbedProvider` services. The HTTP client options respect the same keys as Symfony's HTTP client (`headers`, `timeout`, `max_redirects`, `verify_peer`).

## Usage

```php
use Notifuse\SymfonyBundle\Service\ApiClient;
use Notifuse\SymfonyBundle\Service\NotificationCenterEmbedProvider;

public function __construct(
    private readonly ApiClient $apiClient,
    private readonly NotificationCenterEmbedProvider $notificationCenterEmbedProvider
) {
}

public function ping(): array
{
    return $this->apiClient->request('GET', '/api/workspace.status');
}

public function embedNotificationCenter(): string
{
    return $this->notificationCenterEmbedProvider->renderScriptTag([
        'locale' => 'en',
        'query' => ['list' => 'newsletter'],
        'attributes' => ['async' => 'async'],
    ]);
}
```

You can fetch the script URL directly with `->getEmbedScriptUrl()` if you need to inline it or proxy it through your own asset pipeline.

## Services

| Service ID | Class | Description |
|------------|-------|-------------|
| `Notifuse\SymfonyBundle\Service\ApiClient` | Handles authenticated requests to the Notifuse API. |
| `Notifuse\SymfonyBundle\Service\NotificationCenterEmbedProvider` | Builds notification center URLs and script tags. |

## GitLab CI, Package Registry & Versioning

The `.gitlab-ci.yml` pipeline runs `composer validate` on every branch while the default branch triggers a release flow:

1. `define-version-app` (stage `prepare`) runs `script/calculate_tag.sh`, exports `APP_VERSION`/`SDK_VERSION`, saves them via the dotenv report, and tags the repo with the computed semantic version if it does not already exist. The resulting environment variables are carried forward to the release stage.
2. `release-production` installs GitLab’s `release-cli`, creates a release named after `APP_VERSION`, and links it to the `production` environment.
3. `publish-bundle` archives the bundle with the same `APP_VERSION` and pushes it to the GitLab Composer registry using `CI_JOB_TOKEN`.

`Notifuse\SymfonyBundle\PackageVersion::getVersion()` reads the active Git tag (`CI_COMMIT_TAG` in CI or `git describe --tags` locally), so the version your services see is identical to what the registry receives—no manual tagging is required.

## Testing and Quality

This bundle does not ship with its own test-suite; consider mocking `HttpClientInterface` when writing integration tests for your Symfony application.
