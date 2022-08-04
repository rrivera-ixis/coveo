<?php

namespace Drupal\coveo\Coveo;

/**
 * Client context class.
 */
class ClientContext {

  /**
   * Application (company) ID.
   *
   * @var string
   */
  public $applicationID;

  /**
   * Push source ID.
   *
   * @var string
   */
  public $sourceID;

  /**
   * API key.
   *
   * @var string
   */
  public $apiKey;

  /**
   * Service host.
   *
   * @var string
   */
  public $host;

  /**
   * A cURL multi handle.
   *
   * @var resource
   */
  public $curlMultiHandle;

  /**
   * Constructor.
   *
   * @param string|null $applicationID
   *   Application (company) ID.
   * @param string|null $sourceID
   *   Push source ID.
   * @param string|null $apiKey
   *   Api key.
   * @param string $host
   *   Host.
   *
   * @throws \Exception
   */
  public function __construct($applicationID = NULL, $sourceID = NULL, $apiKey = NULL, $host = NULL) {
    $this->applicationID = $applicationID;
    $this->sourceID = $sourceID;
    $this->apiKey = $apiKey;

    $this->host = $host;

    if ($this->host == NULL || count($this->host) == 0) {
      $this->host = $this->getHost();
    }

    if (($this->applicationID == NULL || mb_strlen($this->applicationID) == 0)) {
      throw new Exception('CoveoSearch requires an applicationID.');
    }

    if (($this->sourceID == NULL || mb_strlen($this->sourceID) == 0)) {
      throw new Exception('CoveoSearch requires an sourceID.');
    }

    if (($this->apiKey == NULL || mb_strlen($this->apiKey) == 0)) {
      throw new Exception('CoveoSearch requires an apiKey.');
    }

    $this->curlMultiHandle = NULL;
    $this->headers = [];
  }

  /**
   * Write hosts.
   *
   * @return array
   *   List of hosts.
   */
  private function getHost() {
    $host = 'push.cloud.coveo.com/v1/organizations/' . $this->applicationID . '/sources/' . $this->sourceID . '/';
    return $host;
  }

  /**
   * Closes eventually opened curl handles.
   */
  public function __destruct() {
    if (is_resource($this->curlMultiHandle)) {
      curl_multi_close($this->curlMultiHandle);
    }
  }

  /**
   * Add a normal cURL handle to a cURL multi handle.
   *
   * @param resource $curlHandle
   *   A cURL handle.
   *
   * @return resource
   *   A cURL multi handle.
   */
  public function getMultiHandle($curlHandle) {
    if (!is_resource($this->curlMultiHandle)) {
      $this->curlMultiHandle = curl_multi_init();
    }
    curl_multi_add_handle($this->curlMultiHandle, $curlHandle);

    return $this->curlMultiHandle;
  }

  /**
   * Remove a multi handle from a set of cURL handles.
   *
   * @param resource $curlHandle
   *   A cURL handle.
   */
  public function releaseMultiHandle($curlHandle) {
    curl_multi_remove_handle($this->curlMultiHandle, $curlHandle);
  }

}
