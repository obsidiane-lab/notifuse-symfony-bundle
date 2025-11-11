<?php

namespace Obsidiane\Notifuse;

use Obsidiane\Notifuse\Exception\NotifuseClientException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class ApiClient
{
    private array $options;
    private string $apiBaseUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $apiBaseUrl,
        private readonly string $workspaceId,
        private readonly string $workspaceApiKey,
        array $options = []
    ) {
        $this->apiBaseUrl = rtrim($apiBaseUrl, '/');
        $this->options = array_merge(
            [
                'timeout' => 10.0,
                'max_redirects' => 5,
                'verify_peer' => true,
                'headers' => [],
            ],
            $options
        );
    }

    /**
     * Low-level request wrapper.
     * Pass Symfony HttpClient options in $options. You may set 'with_auth' => false
     * to avoid sending the Authorization header (for public endpoints like /subscribe).
     */
    public function request(string $method, string $endpoint, array $options = []): array
    {
        $url = $this->buildUrl($endpoint);

        try {
            $response = $this->httpClient->request($method, $url, $this->mergeOptions($options));
            return $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new NotifuseClientException(
                sprintf('Notifuse API request failed [%s %s]: %s', strtoupper($method), $url, $exception->getMessage()),
                $exception->getCode(),
                $exception
            );
        }
    }

    /**
     * POST /api/transactional.send
     * Sends a transactional notification to a contact.
     * Body: { workspace_id, notification: TransactionalNotificationSendParams }
     */
    public function sendTransactional(array $notification, ?string $workspaceId = null, array $options = []): array
    {
        $payload = [
            'workspace_id' => $workspaceId ?? $this->workspaceId,
            'notification' => $notification,
        ];

        return $this->request('POST', '/api/transactional.send', array_merge($options, ['json' => $payload]));
    }

    /**
     * POST /api/contacts.upsert
     * Creates or updates a contact by email.
     * Body: { workspace_id, contact }
     */
    public function upsertContact(array $contact, ?string $workspaceId = null, array $options = []): array
    {
        $payload = [
            'workspace_id' => $workspaceId ?? $this->workspaceId,
            'contact' => $contact,
        ];

        return $this->request('POST', '/api/contacts.upsert', array_merge($options, ['json' => $payload]));
    }

    /**
     * GET /api/contacts.getByEmail
     * Retrieves a contact by email within a workspace.
     * Query: workspace_id, email
     */
    public function getContactByEmail(string $email, ?string $workspaceId = null, array $options = []): array
    {
        $query = [
            'workspace_id' => $workspaceId ?? $this->workspaceId,
            'email' => $email,
        ];

        return $this->request('GET', '/api/contacts.getByEmail', array_merge($options, ['query' => $query]));
    }

    /**
     * GET /api/contacts.getByExternalID
     * Retrieves a contact by external ID within a workspace.
     * Query: workspace_id, external_id
     */
    public function getContactByExternalId(string $externalId, ?string $workspaceId = null, array $options = []): array
    {
        $query = [
            'workspace_id' => $workspaceId ?? $this->workspaceId,
            'external_id' => $externalId,
        ];

        return $this->request('GET', '/api/contacts.getByExternalID', array_merge($options, ['query' => $query]));
    }

    /**
     * POST /api/contacts.import
     * Batch creates/updates contacts; optionally subscribes them to lists.
     * Body: { workspace_id, contacts: Contact[], subscribe_to_lists?: string[] }
     */
    public function importContacts(array $contacts, array $subscribeToLists = [], ?string $workspaceId = null, array $options = []): array
    {
        $payload = [
            'workspace_id' => $workspaceId ?? $this->workspaceId,
            'contacts' => $contacts,
        ];
        if ($subscribeToLists !== []) {
            $payload['subscribe_to_lists'] = $subscribeToLists;
        }

        return $this->request('POST', '/api/contacts.import', array_merge($options, ['json' => $payload]));
    }

    /**
     * POST /api/contacts.delete
     * Deletes a contact by email in the workspace.
     * Body: { workspace_id, email }
     */
    public function deleteContact(string $email, ?string $workspaceId = null, array $options = []): array
    {
        $payload = [
            'workspace_id' => $workspaceId ?? $this->workspaceId,
            'email' => $email,
        ];

        return $this->request('POST', '/api/contacts.delete', array_merge($options, ['json' => $payload]));
    }

    /**
     * POST /api/contactLists.updateStatus
     * Updates the subscription status of a contact in a list.
     * Body: { workspace_id, email, list_id, status }
     * Allowed statuses: active, pending, unsubscribed, bounced, complained
     */
    public function updateContactListStatus(string $email, string $listId, string $status, ?string $workspaceId = null, array $options = []): array
    {
        $allowed = ['active', 'pending', 'unsubscribed', 'bounced', 'complained'];
        if (!in_array($status, $allowed, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid status "%s". Allowed: %s', $status, implode(', ', $allowed)));
        }

        $payload = [
            'workspace_id' => $workspaceId ?? $this->workspaceId,
            'email' => $email,
            'list_id' => $listId,
            'status' => $status,
        ];

        return $this->request('POST', '/api/contactLists.updateStatus', array_merge($options, ['json' => $payload]));
    }

    /**
     * POST /subscribe (public, no auth)
     * Subscribes a contact to one or more email lists.
     * Body: { workspace_id, contact, list_ids }
     */
    public function publicSubscribeToLists(array $contact, array $listIds, ?string $workspaceId = null, array $options = []): array
    {
        $payload = [
            'workspace_id' => $workspaceId ?? $this->workspaceId,
            'contact' => $contact,
            'list_ids' => $listIds,
        ];

        // Ensure Authorization header is not sent for this public endpoint
        $options = array_merge($options, ['json' => $payload, 'with_auth' => false]);

        return $this->request('POST', '/subscribe', $options);
    }

    private function buildUrl(string $endpoint): string
    {
        return $this->apiBaseUrl . '/' . ltrim($endpoint, '/');
    }

    private function mergeOptions(array $options): array
    {
        $customOptions = $options;
        $withAuth = $customOptions['with_auth'] ?? true;
        unset($customOptions['with_auth']);
        $customHeaders = $customOptions['headers'] ?? [];
        unset($customOptions['headers']);

        $mergedHeaders = array_merge($this->buildHeaders($withAuth), $customHeaders);

        return array_merge(
            [
                'headers' => $mergedHeaders,
                'timeout' => $this->options['timeout'],
                'max_redirects' => $this->options['max_redirects'],
                'verify_peer' => $this->options['verify_peer'],
            ],
            $customOptions
        );
    }

    private function buildHeaders(bool $withAuth = true): array
    {
        $headers = [
            'Accept' => 'application/json',
            'X-Workspace-ID' => $this->workspaceId,
        ];
        if ($withAuth) {
            $headers['Authorization'] = sprintf('Bearer %s', $this->workspaceApiKey);
        }

        return array_merge($headers, $this->options['headers'] ?? []);
    }
}
