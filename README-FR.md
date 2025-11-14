# Notifuse Symfony Bundle — Guide FR

Ce bundle facilite l’intégration de l’API Notifuse dans une application Symfony. Il fournit :

- Un client HTTP simple et explicite pour l’API Notifuse (`Service\ApiClient`).
- Une fine couche de configuration (extension DI + YAML) pour garder les URLs et options totalement configurables.
  L’embed du Notification Center (UI) est géré directement par le script front de Notifuse et ne fait pas partie du bundle.

Le bundle n’essaie pas d’abstraire le HttpClient de Symfony : il applique pour vous la base d’URL, les en‑têtes et options communes, tout en vous laissant passer des options par requête si besoin.

## Fonctionnement

- La configuration est déclarée sous la clé `notifuse` et chargée par `DependencyInjection\NotifuseExtension`.
- Les services sont enregistrés via `Resources/config/services.yaml` et sont autowirés dans votre code.
- `Service\ApiClient` préfixe les requêtes avec `api_base_url`, ajoute l’authentification (`Authorization: Bearer …`, `X-Workspace-ID`) et fusionne les options HttpClient configurées.
  Le widget UI n’est pas généré par ce bundle ; intégrez le Notification Center via le script front de Notifuse.

## Installation

Depuis la racine de votre application :

```bash
composer require obsidiane/notifuse-symfony-bundle
```

Si vous n’utilisez pas l’auto‑enregistrement via Symfony Flex, activez le bundle dans votre kernel :

```php
return [
    // ...
    Obsidiane\Notifuse\NotifuseBundle::class => ['all' => true],
];
```

## Configuration

Exemple minimal (via variables d’environnement) :

```yaml
notifuse:
  api_base_url: '%env(NOTIFUSE_API_BASE_URL)%'
  workspace_id: '%env(NOTIFUSE_WORKSPACE_ID)%'
  workspace_api_key: '%env(NOTIFUSE_API_KEY)%'
  http_client_options:
    timeout: 10.0
    max_redirects: 5
    verify_peer: true
    headers: { }
# L’embed du Notification Center est chargé/configuré via le script front de Notifuse (hors bundle).
```

Référence des options :

- `api_base_url` : Base URL de l’API Notifuse (ex. `https://api.notifuse.com`).
- `workspace_id` : Identifiant d’espace de travail ; envoyé en en‑tête et en paramètre d’embed.
- `workspace_api_key` : Jeton Bearer utilisé pour les appels API ; conservez‑le en env/secrets.
- `http_client_options` : Sous‑ensemble des options HttpClient Symfony appliquées à chaque appel (`timeout`, `max_redirects`, `verify_peer`, `headers`). Vous pouvez les surcharger par requête.
  Le script et le conteneur du Notification Center ne sont pas gérés par ce bundle.

## Utiliser l’API (ApiClient)

Injectez `Service\ApiClient` et appelez `request($method, $endpoint, $options = [])`. Le `$endpoint` est concaténé à `api_base_url`.

```php
use Obsidiane\Notifuse\ApiClient;

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
} catch (\Obsidiane\Notifuse\Exception\NotifuseClientException $e) {
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

L’embed (UI) n’est pas géré par ce bundle. Incluez directement le script front de Notifuse là où vous souhaitez afficher le Notification Center.

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

## Compatibilité

- PHP : `^8.1`
- Symfony : `^6.4` ou `^7.0`

## Services

- `Obsidiane\Notifuse\ApiClient` : client HTTP authentifié vers l’API Notifuse.


## Tests

Le bundle n’embarque pas de suite de tests. Dans votre application, vous pouvez mocker `HttpClientInterface` et vérifier :

- La résolution d’URL et des en‑têtes dans `ApiClient::request()`.
