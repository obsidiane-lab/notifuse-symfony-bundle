# Notifuse Symfony Bundle

Symfony bundle to integrate the Notifuse API and its public Notification Center in your application. It focuses on:

- A small, explicit HTTP client wrapper for the Notifuse API (`Service\ApiClient`).
- A helper to build the Notification Center embed URL and script HTML (`Service\NotificationCenterEmbedProvider`).
- A thin configuration layer (DI extension + YAML) to keep endpoints and options fully configurable.

The bundle does not hide Symfony’s HttpClient. Instead, it applies the base URL, headers and common options for you, and gives you escape hatches to pass per‑request options when needed.

## How It Works

- Configuration is declared under the `notifuse` key and loaded by `DependencyInjection\NotifuseExtension`.
- Services are registered in `Resources/config/services.yaml` and autowired into your code.
- `Service\ApiClient` prefixes requests with `api_base_url`, injects authentication headers (`Authorization: Bearer …`, `X-Workspace-ID`) and merges configured HTTP client options.
- `Service\NotificationCenterEmbedProvider` builds the embed script URL from `notification_center_url` and `notification_center.embed_path`, and renders a `<div>` container plus a `<script>` tag with the right `data-*` attributes.

## Installation

Install from your application root:

```bash
composer require obsidiane/notifuse-symfony-bundle
```

If you are not using Symfony Flex auto‑registration, enable the bundle in the kernel:

```php
return [
    // ...
    Notifuse\SymfonyBundle\NotifuseBundle::class => ['all' => true],
];
```

## Configuration

Minimal configuration via environment variables:

```yaml
notifuse:
  api_base_url: '%env(NOTIFUSE_API_BASE_URL)%'
  notification_center_url: '%env(NOTIFUSE_NOTIFICATION_CENTER_URL)%'
  workspace_id: '%env(NOTIFUSE_WORKSPACE_ID)%'
  workspace_api_key: '%env(NOTIFUSE_API_KEY)%'
  default_locale: 'en'
  http_client_options:
    timeout: 10.0
    max_redirects: 5
    verify_peer: true
    headers: { }
  notification_center:
    embed_path: '/notification-center'
    # This ID is used for the generated container <div id="..."> (not the <script>)
    script_element_id: 'notifuse-notification-center'
```

Option reference:

- `api_base_url`: Base URL for the Notifuse API (e.g. `https://api.notifuse.com`).
- `notification_center_url`: Base URL that serves the Notification Center assets.
- `workspace_id`: Your workspace identifier; sent as header and embed parameter.
- `workspace_api_key`: Bearer token used for API calls; keep it in env/secrets.
- `default_locale`: Fallback locale for the embed.
- `http_client_options`: Subset of Symfony HttpClient options applied to every call (`timeout`, `max_redirects`, `verify_peer`, `headers`). You can still override them per request.
- `notification_center.embed_path`: Path part to the embed script relative to the base URL.
- `notification_center.script_element_id`: The id used for the generated container `<div>`.

## API Usage

Inject `Service\ApiClient` and call `request($method, $endpoint, $options = [])`. The `$endpoint` is appended to `api_base_url`.

```php
use Notifuse\SymfonyBundle\Service\ApiClient;

final class MyService
{
    public function __construct(private ApiClient $apiClient) {}

    public function ping(): array
    {
        // Performs GET https://…/api/workspace.status with auth headers
        return $this->apiClient->request('GET', '/api/workspace.status');
    }

    public function createSomething(array $payload): array
    {
        return $this->apiClient->request('POST', '/api/something', [
            'json' => $payload,
            // Optional per‑request overrides
            'headers' => [ 'X-Client-Name' => 'my-app' ],
            'timeout' => 8.0,
        ]);
    }
}
```

Error handling: network/HTTP client errors are wrapped in `Service\Exception\NotifuseClientException` with the original exception as previous. Catch it at your boundary if you want to map to domain errors.

```php
try {
    $data = $this->apiClient->request('GET', '/api/workspace.status');
} catch (\Notifuse\SymfonyBundle\Service\Exception\NotifuseClientException $e) {
    // Log and render a user‑friendly message
}
```

## Endpoint Helpers

The `ApiClient` exposes convenience methods for all Notifuse endpoints:

- `sendTransactional(array $notification, ?string $workspaceId = null, array $options = [])`: POST `/api/transactional.send`
- `upsertContact(array $contact, ?string $workspaceId = null, array $options = [])`: POST `/api/contacts.upsert`
- `getContactByEmail(string $email, ?string $workspaceId = null, array $options = [])`: GET `/api/contacts.getByEmail`
- `getContactByExternalId(string $externalId, ?string $workspaceId = null, array $options = [])`: GET `/api/contacts.getByExternalID`
- `importContacts(array $contacts, array $subscribeToLists = [], ?string $workspaceId = null, array $options = [])`: POST `/api/contacts.import`
- `deleteContact(string $email, ?string $workspaceId = null, array $options = [])`: POST `/api/contacts.delete`
- `updateContactListStatus(string $email, string $listId, string $status, ?string $workspaceId = null, array $options = [])`: POST `/api/contactLists.updateStatus`
- `publicSubscribeToLists(array $contact, array $listIds, ?string $workspaceId = null, array $options = [])`: POST `/subscribe` (no auth header sent)

Examples:

```php
// 1) Send transactional notification
$client->sendTransactional([
    'id' => 'welcome_email',
    'contact' => ['email' => 'user@example.com'],
    'channels' => ['email'],
    'data' => ['user_name' => 'John'],
]);

// 2) Upsert a contact
$client->upsertContact([
    'email' => 'user@example.com',
    'first_name' => 'John',
]);

// 3) Get a contact
$client->getContactByEmail('user@example.com');
$client->getContactByExternalId('user_123');

// 4) Import contacts in batch
$client->importContacts([
    ['email' => 'user1@example.com'],
    ['email' => 'user2@example.com'],
], ['newsletter']);

// 5) Delete a contact
$client->deleteContact('user@example.com');

// 6) Update list subscription status
$client->updateContactListStatus('user@example.com', 'newsletter', 'active');

// 7) Public subscription (no Authorization header)
$client->publicSubscribeToLists([
    'email' => 'user@example.com',
], ['newsletter']);
```

## Embedding the Notification Center

Generate the script URL or the full HTML `<div> + <script>` block. You can pass a locale, extra query parameters, and custom attributes (e.g. `async`, CSP `nonce`).

```php
use Notifuse\SymfonyBundle\Service\NotificationCenterEmbedProvider;

final class SettingsController
{
    public function __construct(private NotificationCenterEmbedProvider $embed) {}

    public function embedNotificationCenter(): string
    {
        // Renders: <div id="notifuse-notification-center"></div>
        //          <script src="…/notification-center?workspace_id=…&locale=en" data-workspace-id="…" data-locale="en" async></script>
        return $this->embed->renderScriptTag([
            'locale' => 'en',
            'query' => ['list' => 'newsletter'],
            'attributes' => ['async' => 'async'],
        ]);
    }
}
```

In a Twig template, you can inject the service in a controller, pass the HTML string to the view and render it with `|raw`:

```twig
{# templates/settings/notifications.html.twig #}
<h2>Notifications</h2>
{{ notifuse_embed_html|raw }}
```

If you need only the URL, use `->getEmbedScriptUrl(array $query = [])` and include it via your asset pipeline.

## HTTP Client Options

Global defaults are provided via `notifuse.http_client_options`. You can still override on each call via `$options` in `ApiClient::request()`.

Common examples:

```yaml
notifuse:
  http_client_options:
    timeout: 8.0
    verify_peer: true
    headers:
      X-Client-Name: 'my-app'
```

```php
$this->apiClient->request('GET', '/api/endpoint', [
    'headers' => ['Accept-Language' => 'fr'],
    'timeout' => 5.0,
]);
```

## Version Information (advanced)

`Notifuse\SymfonyBundle\PackageVersion::getVersion()` tries to read the active Git tag:

- In CI: from `CI_COMMIT_TAG` when running on a tagged commit.
- Locally: from `git describe --tags` if a Git repository is present.
- Fallback: `0.0.0` if no tag can be resolved (e.g. dist packages without `.git`).

The provided `.gitlab-ci.yml` creates a release for `${APP_VERSION}` on the default branch, which also creates the corresponding Git tag if it does not exist.

## Security Notes

- Keep `workspace_api_key` in environment variables or Symfony Secrets. Do not commit secrets.
- Avoid logging sensitive headers. The bundle itself does not log request headers.
- If you use a CSP, pass your `nonce` via `attributes` to `renderScriptTag()`.

## Compatibility

- PHP: `^8.1`
- Symfony: `^6.4` or `^7.0`

## Services Summary

| Service ID | Class | Description |
|------------|-------|-------------|
| `Notifuse\SymfonyBundle\Service\ApiClient` | `Service\ApiClient` | Authenticated requests to the Notifuse API. |
| `Notifuse\SymfonyBundle\Service\NotificationCenterEmbedProvider` | `Service\NotificationCenterEmbedProvider` | Builds Notification Center URLs and script HTML. |

## CI, Releases and Composer Registry

The pipeline validates the package on all branches and, on the default branch, computes a semantic version and creates a GitLab Release and tag. A separate job then notifies the GitLab Composer registry with the same version so consumers can install it via Composer.

See `.gitlab-ci.yml` for job names and the exact flow (`define-version-app`, `release-production`, `publish-composer`).

## Testing

This bundle does not ship with its own test-suite. When writing tests in your application, you can mock `HttpClientInterface` and assert:

- URL resolution/headers in `ApiClient::request()`.
- The output of `NotificationCenterEmbedProvider::getEmbedScriptUrl()` and `::renderScriptTag()` under different contexts.
