# Notifuse Symfony Bundle — Guide FR

Ce bundle facilite l’intégration de l’API Notifuse et de son Notification Center public dans une application Symfony. Il fournit :

- Un client HTTP simple et explicite pour l’API Notifuse (`Service\ApiClient`).
- Un utilitaire pour construire l’URL d’embed et générer le HTML du script du Notification Center (`Service\NotificationCenterEmbedProvider`).
- Une fine couche de configuration (extension DI + YAML) pour garder les URLs et options totalement configurables.

Le bundle n’essaie pas d’abstraire le HttpClient de Symfony : il applique pour vous la base d’URL, les en‑têtes et options communes, tout en vous laissant passer des options par requête si besoin.

## Fonctionnement

- La configuration est déclarée sous la clé `notifuse` et chargée par `DependencyInjection\NotifuseExtension`.
- Les services sont enregistrés via `Resources/config/services.yaml` et sont autowirés dans votre code.
- `Service\ApiClient` préfixe les requêtes avec `api_base_url`, ajoute l’authentification (`Authorization: Bearer …`, `X-Workspace-ID`) et fusionne les options HttpClient configurées.
- `Service\NotificationCenterEmbedProvider` construit l’URL d’embed à partir de `notification_center_url` et `notification_center.embed_path`, et rend un bloc `<div>` conteneur et une balise `<script>` munie des attributs `data-*` nécessaires.

## Installation

Depuis la racine de votre application :

```bash
composer require obsidiane/notifuse-symfony-bundle
```

Si vous n’utilisez pas l’auto‑enregistrement via Symfony Flex, activez le bundle dans votre kernel :

```php
return [
    // ...
    Notifuse\SymfonyBundle\NotifuseBundle::class => ['all' => true],
];
```

## Configuration

Exemple minimal (via variables d’environnement) :

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
    # Cet ID est utilisé pour le <div id="..."> conteneur généré (pas pour <script>)
    script_element_id: 'notifuse-notification-center'
```

Référence des options :

- `api_base_url` : Base URL de l’API Notifuse (ex. `https://api.notifuse.com`).
- `notification_center_url` : Base URL qui sert les assets du Notification Center.
- `workspace_id` : Identifiant d’espace de travail ; envoyé en en‑tête et en paramètre d’embed.
- `workspace_api_key` : Jeton Bearer utilisé pour les appels API ; conservez‑le en env/secrets.
- `default_locale` : Langue par défaut pour l’embed.
- `http_client_options` : Sous‑ensemble des options HttpClient Symfony appliquées à chaque appel (`timeout`, `max_redirects`, `verify_peer`, `headers`). Vous pouvez les surcharger par requête.
- `notification_center.embed_path` : Chemin de l’embed relatif à `notification_center_url`.
- `notification_center.script_element_id` : ID utilisé pour le `<div>` conteneur généré.

## Utiliser l’API (ApiClient)

Injectez `Service\ApiClient` et appelez `request($method, $endpoint, $options = [])`. Le `$endpoint` est concaténé à `api_base_url`.

```php
use Notifuse\SymfonyBundle\Service\ApiClient;

final class MyService
{
    public function __construct(private ApiClient $apiClient) {}

    public function ping(): array
    {
        // Fait un GET https://…/api/workspace.status avec en-têtes d’auth
        return $this->apiClient->request('GET', '/api/workspace.status');
    }

    public function createSomething(array $payload): array
    {
        return $this->apiClient->request('POST', '/api/something', [
            'json' => $payload,
            // Surcharges optionnelles
            'headers' => [ 'X-Client-Name' => 'my-app' ],
            'timeout' => 8.0,
        ]);
    }
}
```

### Gestion des erreurs

Les erreurs réseau/HTTP de Symfony HttpClient sont encapsulées dans `Service\Exception\NotifuseClientException` (avec l’exception d’origine en « previous »). Interceptez‑la à votre frontière d’application pour retourner une erreur métier adaptée.

```php
try {
    $data = $this->apiClient->request('GET', '/api/workspace.status');
} catch (\Notifuse\SymfonyBundle\Service\Exception\NotifuseClientException $e) {
    // Log puis renvoyer une réponse adaptée côté utilisateur
}
```

## Raccourcis d’endpoints

Le `ApiClient` expose des méthodes pratiques pour chaque endpoint Notifuse :

- `sendTransactional(array $notification, ?string $workspaceId = null, array $options = [])` : POST `/api/transactional.send`
- `upsertContact(array $contact, ?string $workspaceId = null, array $options = [])` : POST `/api/contacts.upsert`
- `getContactByEmail(string $email, ?string $workspaceId = null, array $options = [])` : GET `/api/contacts.getByEmail`
- `getContactByExternalId(string $externalId, ?string $workspaceId = null, array $options = [])` : GET `/api/contacts.getByExternalID`
- `importContacts(array $contacts, array $subscribeToLists = [], ?string $workspaceId = null, array $options = [])` : POST `/api/contacts.import`
- `deleteContact(string $email, ?string $workspaceId = null, array $options = [])` : POST `/api/contacts.delete`
- `updateContactListStatus(string $email, string $listId, string $status, ?string $workspaceId = null, array $options = [])` : POST `/api/contactLists.updateStatus`
- `publicSubscribeToLists(array $contact, array $listIds, ?string $workspaceId = null, array $options = [])` : POST `/subscribe` (pas d’en‑tête Authorization)

Exemples :

```php
// 1) Envoyer une notification transactionnelle
$client->sendTransactional([
    'id' => 'welcome_email',
    'contact' => ['email' => 'user@example.com'],
    'channels' => ['email'],
    'data' => ['user_name' => 'John'],
]);

// 2) Upsert d’un contact
$client->upsertContact([
    'email' => 'user@example.com',
    'first_name' => 'John',
]);

// 3) Récupérer un contact
$client->getContactByEmail('user@example.com');
$client->getContactByExternalId('user_123');

// 4) Import de contacts en lot
$client->importContacts([
    ['email' => 'user1@example.com'],
    ['email' => 'user2@example.com'],
], ['newsletter']);

// 5) Supprimer un contact
$client->deleteContact('user@example.com');

// 6) Mettre à jour le statut d’abonnement à une liste
$client->updateContactListStatus('user@example.com', 'newsletter', 'active');

// 7) Abonnement public (sans Authorization)
$client->publicSubscribeToLists([
    'email' => 'user@example.com',
], ['newsletter']);
```

## Intégrer le Notification Center

Générez l’URL du script ou le HTML complet (`<div>` + `<script>`). Vous pouvez passer la locale, des paramètres de requête supplémentaires, ainsi que des attributs (ex. `async`, `nonce` CSP).

```php
use Notifuse\SymfonyBundle\Service\NotificationCenterEmbedProvider;

final class SettingsController
{
    public function __construct(private NotificationCenterEmbedProvider $embed) {}

    public function embedNotificationCenter(): string
    {
        // Rend : <div id="notifuse-notification-center"></div>
        //       <script src="…/notification-center?workspace_id=…&locale=en" data-workspace-id="…" data-locale="en" async></script>
        return $this->embed->renderScriptTag([
            'locale' => 'en',
            'query' => ['list' => 'newsletter'],
            'attributes' => ['async' => 'async'],
        ]);
    }
}
```

Dans un template Twig, transmettez le HTML généré depuis le contrôleur et affichez‑le avec `|raw` :

```twig
{# templates/settings/notifications.html.twig #}
<h2>Notifications</h2>
{{ notifuse_embed_html|raw }}
```

Si vous n’avez besoin que de l’URL, utilisez `->getEmbedScriptUrl(array $query = [])` et intégrez‑la à votre pipeline d’assets.

## Options HTTP

Les valeurs par défaut globales proviennent de `notifuse.http_client_options`. Elles restent surchageables par appel via `$options` dans `ApiClient::request()`.

Exemples :

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

## Version et CI/CD

`Notifuse\SymfonyBundle\PackageVersion::getVersion()` tente de lire le tag Git actif :

- En CI : via `CI_COMMIT_TAG` si le job s’exécute sur un commit taggé.
- En local : via `git describe --tags` si un dépôt Git est présent.
- Secours : `0.0.0` si aucun tag n’est résolu (ex. paquets dist sans `.git`).

Le pipeline `.gitlab-ci.yml` :

- Calcule une version sémantique et l’exporte (`define-version-app`).
- Crée une Release GitLab (et le tag correspondant si nécessaire) (`release-production`).
- Notifie le registre Composer GitLab avec cette même version (`publish-composer`).

## Sécurité

- Conservez `workspace_api_key` dans les variables d’environnement ou Secrets Symfony. Ne commitez pas de secrets.
- Évitez de logger des en‑têtes sensibles. Le bundle ne log pas les en‑têtes des requêtes.
- Si vous utilisez une CSP, passez votre `nonce` via `attributes` à `renderScriptTag()`.

## Compatibilité

- PHP : `^8.1`
- Symfony : `^6.4` ou `^7.0`

## Services

- `Notifuse\SymfonyBundle\Service\ApiClient` : client HTTP authentifié vers l’API Notifuse.
- `Notifuse\SymfonyBundle\Service\NotificationCenterEmbedProvider` : construit les URLs d’embed et le HTML du script.

## Tests

Le bundle n’embarque pas de suite de tests. Dans votre application, vous pouvez mocker `HttpClientInterface` et vérifier :

- La résolution d’URL et des en‑têtes dans `ApiClient::request()`.
- Le rendu de `NotificationCenterEmbedProvider::getEmbedScriptUrl()` et `::renderScriptTag()` selon différents contextes.
