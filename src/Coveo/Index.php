<?php

namespace Drupal\coveo\Coveo;

/**
 * Contains all the functions related to one index.
 *
 * You should use Client.initIndex(indexName) to retrieve this object.
 */
class Index {

  /**
   * Client context object.
   *
   * @var \Drupal\coveo\Coveo\ClientContext
   */
  private $context;

  /**
   * Client object.
   *
   * @var \Drupal\coveo\Coveo\Client
   */
  private $client;

  /**
   * Index.
   *
   * @var string
   */
  public $indexName;

  /**
   * Index initialization (You should not instantiate this yourself).
   *
   * @param \Drupal\coveo\Coveo\ClientContext $context
   *   Client context.
   * @param \Drupal\coveo\Coveo\Client $client
   *   Client.
   * @param string $indexName
   *   Index name.
   *
   * @internal
   */
  public function __construct(ClientContext $context, Client $client, $indexName) {
    $this->context = $context;
    $this->client = $client;
    $this->indexName = $indexName;
  }

  /**
   * Perform batch operation on several objects.
   *
   * @param array $objects
   *   Contains an array of objects to update.
   * @param string $objectIDKey
   *   The key in each object that contains the objectID.
   * @param string $objectActionKey
   *   The key in each object that contains the action to perform (addOrUpdate).
   *
   * @return mixed
   *   Batch process results.
   */
  public function batchObjects(array $objects, $objectIDKey = 'objectID', $objectActionKey = 'objectAction') {
    $requestHeaders = func_num_args() === 4 && is_array(func_get_arg(3)) ? func_get_arg(3) : [];

    $requests = [];
    $allowedActions = ['addOrUpdate'];

    foreach ($objects as $obj) {
      // If no or invalid action, assume updateObject.
      if (!isset($obj[$objectActionKey]) || !in_array($obj[$objectActionKey], $allowedActions)) {
        throw new \Exception('invalid or no action detected');
      }
      $action = $obj[$objectActionKey];

      // The action key is not included in the object.
      unset($obj[$objectActionKey]);

      $req = ['action' => $action, 'body' => $obj];

      if (array_key_exists($objectIDKey, $obj)) {
        $req['objectID'] = (string) $obj[$objectIDKey];
      }
      $requests[] = $req;
    }

    return $this->batch(['requests' => $requests], $requestHeaders);
  }

  /**
   * Override the content of several objects.
   *
   * @param array $objects
   *   Contains an array of objects to update
   *   (each object must contains a objectID attribute).
   *
   * @return mixed
   *   Batch process results.
   */
  public function saveObjects(array $objects) {
    $objectIDKey = 'documentId';
    $requestHeaders = [];
    $nbArgs = func_num_args();
    if ($nbArgs > 1) {
      $requestHeaders = is_array(func_get_arg($nbArgs - 1)) ? func_get_arg($nbArgs - 1) : [];
    }

    $requests = $this->buildBatch('addOrUpdate', $objects, TRUE, $objectIDKey);
    $requests['delete'] = [];
    return $this->batch($requests, $requestHeaders);
  }

  /**
   * This function deletes the index content.
   *
   * Settings and index specific API keys are kept untouched.
   *
   * @return mixed
   *   Request response.
   *
   * @throws CoveoException
   */
  public function clearIndex() {
    return $this->client->request(
      $this->context,
      'DELETE',
      '',
      ['orderingId' => time() * 1000],
      [],
      sprintf('https://push.cloud.coveo.com/v1/organizations/%s/sources/%s/documents/olderthan',
        $this->context->applicationID,
        $this->context->sourceID
      )
    );
  }

  /**
   * Send a batch request.
   *
   * @param array $operations
   *   An associative array defining the batch request body.
   *
   * @return mixed
   *   Request response.
   */
  public function batch(array $operations) {
    $step1 = $this->client->request(
      $this->context, 'POST', '', [], [],
      'https://push.cloud.coveo.com/v1/organizations/' . $this->context->applicationID . '/files'
    );

    $parts = parse_url($step1['uploadUri']);
    $upload_url = $parts['scheme'] . '://' . $parts['host'] . $parts['path'];
    parse_str($parts['query'], $query);

    // Step 2.
    $this->client->request(
      $this->context,
      'PUT',
      '',
      $query,
      $operations,
      $upload_url
    );

    // @todo Fix using sleep to rather check for existence.
    sleep(3);

    return $this->client->request(
      $this->context,
      'PUT',
      '',
      ['fileId' => $step1['fileId']],
      [],
      sprintf('https://push.cloud.coveo.com/v1/organizations/%s/sources/%s/documents/batch',
        $this->context->applicationID,
        $this->context->sourceID
      )
    );
  }

  /**
   * Build a batch request.
   *
   * @param string $action
   *   The batch action.
   * @param array $objects
   *   The array of objects.
   * @param string $withObjectID
   *   Set an 'objectID' attribute.
   * @param string $documentIDKey
   *   The documentIDKey.
   *
   * @return array
   *   Array of data to be processed.
   */
  private function buildBatch($action, array $objects, $withObjectID, $documentIDKey = 'documentId') {
    $requests = [];
    foreach ($objects as $obj) {
      $req = $obj;
      if ($withObjectID && array_key_exists($documentIDKey, $obj)) {
        $req['documentId'] = (string) $obj[$documentIDKey];
        if (array_key_exists('body', $req)) {
          $req['data'] = $req['body'];
          $req['permissions'] = [];
          unset($req['body']);
        }
      }
      array_push($requests, $req);
    }

    return [$action => $requests];
  }

  /**
   * Getter for context.
   *
   * @return \Drupal\coveo\Coveo\ClientContext
   *   The context of this index.
   */
  public function getContext() {
    return $this->context;
  }

}
