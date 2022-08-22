<?php

namespace Platformsh\Client\Model;

use GuzzleHttp\Exception\BadResponseException;
use Platformsh\Client\Model\Activities\HasActivitiesInterface;
use Platformsh\Client\Model\Activities\HasActivitiesTrait;

/**
 * A project integration.
 *
 * @property-read string $id
 * @property-read string $type
 */
class Integration extends Resource implements HasActivitiesInterface
{
    use HasActivitiesTrait;

    /** @var array */
    protected static $required = ['type'];

    /**
     * Trigger the integration's web hook.
     *
     * Normally the external service should do this in response to events, but
     * it may be useful to trigger the hook manually in certain cases.
     */
    public function triggerHook()
    {
        $hookUrl = $this->getLink('#hook');
        $options = [];

        // The API needs us to send an empty JSON object.
        $options['json'] = new \stdClass();

        // Switch off authentication for this request (none is required).
        $options['auth'] = null;

        $this->sendRequest($hookUrl, 'post', $options);
    }

    /**
     * {@inheritDoc}
     *
     * The hook link must use the integration's "self" link as the base, not
     * the overridden base URL. This is because the external API proxy does not
     * yet support unauthenticated requests.
     */
    public function getLink($rel, $absolute = true)
    {
        if ($rel === '#hook') {
            if (!isset($this->data['_links'][$rel]['href'])) {
                throw new \InvalidArgumentException("Link not found: $rel");
            }
            if (!isset($this->data['_links']['self']['href'])) {
                throw new \RuntimeException('Failed to find integration "self" link');
            }
            return $this->makeAbsoluteUrl($this->data['_links'][$rel]['href'], $this->data['_links']['self']['href']);
        }
        return parent::getLink($rel, $absolute);
    }

    /**
     * Validate the integration via the API.
     *
     * @throws \Platformsh\Client\Exception\OperationUnavailableException
     *   If the integration does not support validation.
     * @throws \RuntimeException If an unexpected error occurs.
     *
     * @return string[]
     *   An array of errors, as returned by the API. An empty array indicates
     *   the integration is valid.
     */
    public function validate()
    {
        try {
            $this->runOperation('validate', 'post');
        } catch (BadResponseException $e) {
            return self::listValidationErrors($e);
        }

        return [];
    }

    /**
     * Process an API exception to list integration validation errors.
     *
     * @param \GuzzleHttp\Exception\BadResponseException $exception
     *   An exception received during integration create, update, or validate.
     *
     * @see \Platformsh\Client\Model\Integration::validate()
     *
     * @throws \GuzzleHttp\Exception\BadResponseException
     *   The original exception is re-thrown if specific validation errors
     *   cannot be found.
     *
     * @return string[] A list of errors.
     */
    public static function listValidationErrors(BadResponseException $exception)
    {
        $response = $exception->getResponse();
        if ($response && $response->getStatusCode() === 400) {
            $response->getBody()->seek(0);
            $data = $response->json();
            if (isset($data['detail']) && is_array($data['detail'])) {
                return $data['detail'];
            }
        }

        throw $exception;
    }
}
