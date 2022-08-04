<?php

namespace Drupal\coveo\Coveo;

/**
 * Class Field.
 *
 * This will eventually be used for adding fields to the index, but for now
 * is just simply to add the main 'search_api_id' field needed to work with
 * drupal.
 */
class Field {

  /**
   * The Coveo Field base.
   */
  const BASE_URL = 'https://platform.cloud.coveo.com/rest/organizations/%s';

  /**
   * The Coveo Field Batch path.
   */
  const FIELD_BATCH = '/indexes/fields/batch';

  /**
   * The Coveo Field page path.
   */
  const FIELD_PAGE = '/indexes/page/fields';

  /**
   * The Coveo client class.
   *
   * @var \Drupal\coveo\Coveo\Client
   */
  protected $client;

  /**
   * The field name.
   *
   * @var string
   */
  protected $name;

  /**
   * The field description.
   *
   * @var string
   */
  protected $description;

  /**
   * The field type.
   *
   * @var string
   */
  protected $type;

  /**
   * Field constructor.
   *
   * @param \Drupal\coveo\Coveo\Client $client
   *   The coveo client.
   */
  public function __construct(Client $client) {
    $this->client = $client;
  }

  /**
   * Loads the current USER type fields from coveo.
   *
   * @return mixed
   *   Response or FALSE.
   *
   * @throws \Drupal\coveo\Coveo\CoveoConnectionException
   * @throws \Drupal\coveo\Coveo\CoveoException
   */
  public function loadUserFields() {
    $context = $this->client->getContext();
    $url = sprintf(self::BASE_URL, $context->applicationID);
    return $this->client->request($context, 'GET', self::FIELD_PAGE, ['origin' => 'USER'], [], $url);
  }

  /**
   * Set data on a field from an array.
   *
   * @param array $data
   *   The data to set.
   */
  public function setData(array $data) {
    foreach ($data as $key => $val) {
      if (method_exists($this, 'set' . ucfirst($key))) {
        $this->{'set' . ucfirst($key)}($val);
      }
    }
  }

  /**
   * Saves a field into coveo.
   *
   * @param array $bulk
   *   An array to bulk create fields.
   *
   * @return mixed
   *   The response.
   *
   * @throws \Drupal\coveo\Coveo\CoveoConnectionException
   * @throws \Drupal\coveo\Coveo\CoveoException
   */
  public function create(array $bulk = []) {
    $context = $this->client->getContext();
    $url = sprintf(self::BASE_URL, $context->applicationID);
    $data = !empty($bulk) ? $bulk : $this->getRequestData();
    return $this->client->request($context, 'POST', self::FIELD_BATCH . '/create', [], $data, $url);
  }

  /**
   * Updates a field into coveo.
   *
   * @param array $bulk
   *   An array to bulk create fields.
   *
   * @return mixed
   *   The response.
   *
   * @throws \Drupal\coveo\Coveo\CoveoConnectionException
   * @throws \Drupal\coveo\Coveo\CoveoException
   */
  public function update(array $bulk = []) {
    $context = $this->client->getContext();
    $url = sprintf(self::BASE_URL, $context->applicationID);
    $data = !empty($bulk) ? $bulk : $this->getRequestData();
    return $this->client->request($context, 'PUT', self::FIELD_BATCH . '/update', [], $data, $url);
  }

  /**
   * Deletes a field into coveo.
   *
   * @param array $bulk
   *   A bulk group to delete.
   *
   * @return mixed
   *   The response.
   *
   * @throws \Drupal\coveo\Coveo\CoveoConnectionException
   * @throws \Drupal\coveo\Coveo\CoveoException
   */
  public function delete(array $bulk = []) {
    $context = $this->client->getContext();
    $url = sprintf(self::BASE_URL, $context->applicationID);
    $data = !empty($bulk) ? $bulk : [$this->getName()];
    $fields = ['fields' => implode(',', $data)];
    return $this->client->request($context, 'DELETE', self::FIELD_BATCH . '/delete', $fields, [], $url);
  }

  /**
   * Loads the data for the request.
   *
   * @return array
   *   The data in the request format.
   */
  public function getRequestData() {
    return [
      [
        'name' => $this->getName(),
        'description' => $this->getDescription(),
        'type' => $this->getType(),
      ],
    ];
  }

  /**
   * Converts a field type into a Coveo type.
   *
   * @param string $type
   *   The original type.
   *
   * @return string
   *   The new type.
   */
  public function mapCoveoType($type) {
    switch ($type) {
      case 'boolean':
      case 'string':
      case 'text':
        $mappedType = 'STRING';
        break;

      case 'date':
        $mappedType = 'DATE';
        break;

      case 'integer':
        $mappedType = 'LONG_64';
        break;

      default:
        $mappedType = 'STRING';
    }
    return $mappedType;
  }

  /**
   * Getter for name.
   *
   * @return string
   *   The name.
   */
  public function getName() {
    return $this->name;
  }

  /**
   * Setter for name.
   *
   * @param string $name
   *   The name.
   */
  public function setName($name) {
    $this->name = $name;
  }

  /**
   * Getter for Description.
   *
   * @return string
   *   The description.
   */
  public function getDescription() {
    return $this->description;
  }

  /**
   * Setter for Description.
   *
   * @param string $description
   *   The description.
   */
  public function setDescription($description) {
    $this->description = $description;
  }

  /**
   * Getter for Type.
   *
   * @return string
   *   The type.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Setter for Type.
   *
   * @param string $type
   *   The type.
   */
  public function setType($type) {
    $this->type = $this->mapCoveoType($type);
  }

}
