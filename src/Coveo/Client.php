<?php

namespace Drupal\coveo\Coveo;

use Drupal\Component\Serialization\Json;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Entry point in the PHP API.
 */
class Client {

  /**
   * Client context object.
   *
   * @var \Drupal\coveo\Coveo\ClientContext
   */
  private $context;

  /**
   * The HTTP Client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * Coveo Search initialization.
   *
   * @param string $applicationID
   *   The application ID you have in your admin interface.
   * @param string $sourceID
   *   The push source ID.
   * @param string $apiKey
   *   Valid API key for the service.
   * @param string $host
   *   The list of hosts that you have received for the service.
   *
   * @throws \Exception
   */
  public function __construct($applicationID, $sourceID, $apiKey, $host = FALSE) {
    $this->context = new ClientContext($applicationID, $sourceID, $apiKey, $host);
    $this->httpClient = \Drupal::httpClient();
  }

  /**
   * Get the index object initialized - no server call needed for init.
   *
   * @param string $indexName
   *   The name of index.
   *
   * @return \Drupal\coveo\Coveo\Index
   *   Index object.
   *
   * @throws \Drupal\coveo\Coveo\CoveoException
   */
  public function initIndex($indexName) {
    if (empty($indexName)) {
      throw new CoveoException('Invalid index name: empty string');
    }

    return new Index($this->context, $this, $indexName);
  }

  /**
   * Request query builder.
   *
   * @param array $args
   *   Query arguments.
   *
   * @return string
   *   Query.
   */
  public static function buildQuery(array $args) {
    foreach ($args as $key => $value) {
      if (gettype($value) == 'array') {
        $args[$key] = Json::encode($value);
      }
    }

    return http_build_query($args);
  }

  /**
   * Coveo service request.
   *
   * @param \Drupal\coveo\Coveo\ClientContext $context
   *   Client context.
   * @param string $method
   *   HTTP method.
   * @param string $path
   *   Url path.
   * @param array $params
   *   Request parameters.
   * @param array $data
   *   Data.
   * @param string $host
   *   Host.
   *
   * @return mixed
   *   Request response.
   */
  public function request(ClientContext $context, $method, $path, array $params, array $data, $host) {
    $exceptions = [];

    try {
      $res = $this->doRequest($context, $method, $host, $path, $params, $data);
      return $res;
    }
    catch (CoveoException $e) {
      throw $e;
    }
    catch (\Exception $e) {
      $exceptions[$host] = $e->getMessage();
    }
    throw new CoveoConnectionException('Host unreachable: ' . implode(',', $exceptions));
  }

  /**
   * Send a request.
   *
   * @param \Drupal\coveo\Coveo\ClientContext $context
   *   Client context.
   * @param string $method
   *   HTTP method.
   * @param string $host
   *   Server host.
   * @param string $path
   *   Url path.
   * @param array $params
   *   Request parameters.
   * @param array $data
   *   Data.
   *
   * @return mixed
   *   Request response.
   *
   * @throws \Drupal\coveo\Coveo\CoveoException
   * @throws \Exception
   */
  public function doRequest(ClientContext $context, $method, $host, $path, array $params, array $data) {
    if (strpos($host, 'http') === 0) {
      $url = $host . $path;
    }
    else {
      $url = 'https://' . $host . $path;
    }

    if ($params != NULL && count($params) > 0) {
      $params2 = [];
      foreach ($params as $key => $val) {
        if (is_array($val)) {
          $params2[$key] = Json::encode($val);
        }
        else {
          $params2[$key] = $val;
        }
      }
      $url .= '?' . http_build_query($params2);
    }

    $defaultHeaders = [
      'Accept' => 'application/json',
      'Content-Type' => 'application/json',
      'Authorization' => 'Bearer ' . $this->context->apiKey,
    ];

    // Upload the content into the File container.
    if ($method == 'PUT' && strpos($host, 's3.amazonaws') !== FALSE) {
      $defaultHeaders = [
        'Content-Type' => 'application/octet-stream',
        'x-amz-server-side-encryption' => 'AES256',
      ];
    }

    $headers = array_merge($defaultHeaders, $context->headers);

    $options = [
      'headers' => $headers,
    ];

    // Add body to post/put.
    if (in_array($method, ['POST', 'PUT'])) {
      $body = ($data) ? Json::encode($data) : '{}';
      $options['body'] = $body;
    }

    // Make the call.
    try {
      $response = $this->httpClient->request($method, $url, $options);
    }
    catch (GuzzleException $e) {
      if ($e->hasResponse()) {
        $status = $e->getCode();
        $exception = (string) $e->getResponse()->getBody();
        $answer = Json::decode($exception);
        if (intval($status / 100) == 4) {
          throw new CoveoException(isset($answer['message']) ? $answer['message'] : $status . ' error', $status);
        }
        elseif (intval($status / 100) != 2) {
          throw new \Exception($status . ': ' . $answer['message'], $status);
        }
      }
      else {
        throw new \Exception($e->getMessage(), 503);
      }
    }

    $body = $response->getBody()->getContents();
    $status = $response->getStatusCode();

    if (intval($status / 100) == 2) {
      if (in_array($method, ['PUT', 'DELETE']) && (empty($body) || $body == 'null')) {
        return $body;
      }
    }

    $answer = Json::decode($body);

    return $answer;
  }

  /**
   * Get client context.
   */
  public function getContext() {
    return $this->context;
  }

}
