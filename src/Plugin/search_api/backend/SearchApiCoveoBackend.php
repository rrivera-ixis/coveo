<?php

namespace Drupal\coveo\Plugin\search_api\backend;

use Drupal\search_api\Entity\Index;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api_autocomplete\SearchInterface;
use Drupal\search_api_autocomplete\Suggestion\SuggestionFactory;
use Drupal\coveo\Coveo\Client;
use Drupal\coveo\Coveo\Field;
use Drupal\coveo\Coveo\Search;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\search_api\Backend\BackendPluginBase;
use Drupal\search_api\Item\ItemInterface;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Plugin\PluginFormTrait;
use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Coveo search API backend plugin.
 *
 * @SearchApiBackend(
 *   id = "coveo",
 *   label = @Translation("Coveo"),
 *   description = @Translation("Index items using a Coveo Search.")
 * )
 */
class SearchApiCoveoBackend extends BackendPluginBase implements PluginFormInterface {

  use PluginFormTrait;

  /**
   * The current Index.
   *
   * @var \Drupal\coveo\Coveo\Index|null
   */
  protected $coveoIndex = NULL;

  /**
   * A connection to the Coveo server.
   *
   * @var \Drupal\coveo\Coveo\Client
   */
  protected $coveoClient;

  /**
   * An instance of Coveo Search.
   *
   * @var \Drupal\coveo\Coveo\Search
   */
  protected $coveoSearch;

  /**
   * The logger to use for logging messages.
   *
   * @var \Psr\Log\LoggerInterface|null
   */
  protected $logger;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $backend = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\Core\Extension\ModuleHandlerInterface $module_handler */
    $module_handler = $container->get('module_handler');
    $backend->setModuleHandler($module_handler);

    /** @var \Psr\Log\LoggerInterface $logger */
    $logger = $container->get('logger.channel.coveo');
    $backend->setLogger($logger);

    return $backend;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'application_id' => '',
      'source_id' => '',
      'source_name' => '',
      'api_key' => '',
      'search_page' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['application_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Application ID'),
      '#description' => $this->t('The application ID from your Coveo subscription.'),
      '#default_value' => $this->getApplicationId(),
      '#required' => TRUE,
      '#size' => 60,
      '#maxlength' => 128,
    ];
    $form['source_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source ID'),
      '#description' => $this->t('The Push Source ID.'),
      '#default_value' => $this->getSourceId(),
      '#required' => TRUE,
      '#size' => 60,
      '#maxlength' => 128,
    ];
    $form['source_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Source Name'),
      '#description' => $this->t('The Push Source Name.'),
      '#default_value' => $this->getSourceName(),
      '#required' => TRUE,
      '#size' => 60,
      '#maxlength' => 128,
    ];
    $current_key = $this->getApiKey();

    if (!empty($current_key)) {
      $form['existing_key'] = [
        '#type' => 'value',
        '#value' => $current_key,
      ];
    }

    $form['api_key'] = [
      '#type' => 'password',
      '#title' => $this->t('API Key'),
      '#description' => $this->t('The API key from your Coveo subscription (Only enter when creating/changing).'),
      '#default_value' => '',
      '#required' => empty($current_key),
      '#size' => 60,
      '#maxlength' => 128,
    ];

    $form['search_page'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Search page path'),
      '#description' => $this->t('The search page path.'),
      '#default_value' => $this->getSearchPage(),
      '#required' => TRUE,
      '#size' => 60,
      '#maxlength' => 128,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();

    if (!empty($values['existing_key'])) {
      if (empty($values['api_key'])) {
        $values['api_key'] = $values['existing_key'];
        $form_state->setValue('api_key', $values['existing_key']);
      }
      $form_state->unsetValue('existing_key');
    }

    $client = new Client($values['application_id'], $values['source_id'], $values['api_key']);

    // Validate search page path.
    $path = $values['search_page'];
    if (!empty($path)) {
      // Validate that the submitted alias does not exist yet.
      $is_exists = \Drupal::service('path.alias_storage')
        ->aliasExists($path, 'en');
      if ($is_exists) {
        $form_state->setError($form['search_page'], $this->t('The url is already in use.'));
      }
    }
    if ($path && $path[0] !== '/') {
      $form_state->setError($form['search_page'], $this->t('The url needs to start with a slash.'));
    }

    $field = new Field($client);
    $field->setData([
      'name' => coveo_ID_FIELD,
      'description' => 'Search API ID field for Drupal',
      'type' => 'STRING',
    ]);

    try {
      $field->create();
    }
    catch (\Exception $e) {
      // Field already exists.
      if ($e->getCode() !== 412) {
        $form_state->setError($form, $this->t('There was a problem adding the necessary fields to your Coveo source, please check permissions.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function viewSettings() {
    try {
      $this->connect();
    }
    catch (\Exception $e) {
      $this->getLogger()->warning('Could not connect to Coveo backend.');
    }
    $info = [];

    // Application ID.
    $info[] = [
      'label' => $this->t('Application ID'),
      'info' => $this->getApplicationId(),
    ];

    // API Key.
    $info[] = [
      'label' => $this->t('API Key'),
      'info' => $this->t('--- Hidden ---'),
    ];

    return $info;
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedFeatures() {
    return [
      'search_api_autocomplete',
      'search_api_facets',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function indexItems(IndexInterface $index, array $items) {
    $this->connect($index);

    $objects = [];
    /** @var \Drupal\search_api\Item\ItemInterface[] $items */
    foreach ($items as $id => $item) {
      $objects[$id] = $this->prepareItem($index, $item);
    }
    // Let other modules alter objects before sending them to Coveo.
    \Drupal::moduleHandler()
      ->alter('coveo_objects', $objects, $index, $items);
    $this->alterCoveoObjects($objects, $index, $items);

    if (count($objects) > 0) {
      $this->getCoveoIndex()->saveObjects($objects);
    }

    return array_keys($objects);
  }

  /**
   * Prepare a single item for indexing.
   *
   * Used as a helper method in indexItem()/indexItems().
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index for which the item is being indexed.
   * @param \Drupal\search_api\Item\ItemInterface $item
   *   The item to index.
   */
  protected function prepareItem(IndexInterface $index, ItemInterface $item) {
    $itemId = $item->getId();
    $entity = $item->getOriginalObject()->toArray();
    $host = \Drupal::request()->getSchemeAndHttpHost();
    $documentID = !empty($entity['path'][0]['alias']) ? $entity['path'][0]['alias'] : '/node/' . $entity['nid'][0]['value'];
    $item_to_index = [
      'documentType' => 'WebPage',
      'sourceType' => 'Push',
      'documentId' => $host . $documentID,
      coveo_ID_FIELD => $itemId,
    ];

    /** @var \Drupal\search_api\Item\FieldInterface $field */
    $item_fields = $item->getFields();

    foreach ($item_fields as $field) {
      $type = $field->getType();
      $values = NULL;

      foreach ($field->getValues() as $field_value) {
        if (!$field_value) {
          continue;
        }

        switch ($type) {
          case 'text':
          case 'string':
          case 'uri':
            $field_value .= '';
            // @todo This should be more defined.
            if (mb_strlen($field_value) > 10000) {
              $field_value = mb_substr(trim($field_value), 0, 10000);
            }
            $values[] = $field_value;
            break;

          case 'integer':
          case 'duration':
          case 'decimal':
            $values[] = 0 + $field_value;
            break;

          case 'boolean':
            $values[] = $field_value ? TRUE : FALSE;
            break;

          case 'date':
            if (is_numeric($field_value) || !$field_value) {
              $values[] = 0 + $field_value;
              break;
            }
            $values[] = strtotime($field_value);
            break;

          default:
            $values[] = $field_value;
        }
      }
      if (!empty($values) && count($values) <= 1) {
        $values = reset($values);
      }
      $item_to_index[coveo_PREFIX . $field->getFieldIdentifier()] = $values;
    }

    return $item_to_index;
  }

  /**
   * Applies custom modifications to indexed Coveo objects.
   *
   * This method allows subclasses to easily apply custom changes before the
   * objects are sent to Coveo. The method is empty by default.
   *
   * @param array $objects
   *   An array of objects ready to be indexed, generated from $items array.
   * @param \Drupal\search_api\IndexInterface $index
   *   The search index for which items are being indexed.
   * @param array $items
   *   An array of items being indexed.
   *
   * @see hook_coveo_objects_alter()
   */
  protected function alterCoveoObjects(array &$objects, IndexInterface $index, array $items) {
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(IndexInterface $index, array $ids) {
    // @todo Fix no Single deletion.
    $this->connect($index);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteAllIndexItems(IndexInterface $index = NULL, $datasource_id = NULL) {
    if ($index) {
      // Connect to the Coveo service.
      $this->connect($index);

      // Clearing the full index.
      $this->getCoveoIndex()->clearIndex();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function search(QueryInterface $query) {
    $index = $query->getIndex();

    /** @var \Drupal\coveo\Coveo\Search $search */
    $search = $this->getCoveoSearch($index);

    $results = $query->getResults();

    // Get the keywords.
    $keys = $query->getKeys();
    unset($keys['#conjunction']);

    // @todo This is assuming one filter for now for demo.
    $search->setKeywords($keys[0]);

    // @todo Does this need to account for views filters as well?
    $this->setFilters($query, $search);

    // Set Facets to group by.
    $this->setFacets($query, $search);

    // Set the sorting.
    $this->setSorts($query, $search);

    // Do the search.
    $search_result = $search->execute();

    if (!$query->getOption('skip result count') && !empty($search_result['totalCountFiltered'])) {
      $results->setResultCount($search_result['totalCountFiltered']);
    }

    foreach ($search_result['results'] as $result) {
      if (isset($result['raw'][coveo_ID_FIELD])) {
        $item_id = $result['raw'][coveo_ID_FIELD];
        $item = $this->getFieldsHelper()->createItem($index, $item_id);
        $item->setScore($result['score']);
        $results->addResultItem($item);
      }
    }

    if ($facet_results = $this->extractFacets($search_result)) {
      $results->setExtraData('search_api_facets', $facet_results);
    }

    return $results;
  }

  /**
   * Implements autocomplete compatible to AutocompleteBackendInterface.
   *
   * @todo This should call Coveo's suggestion API to return autocomplete
   * suggestions, see search/v2/querySuggest.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   A query representing the completed user input so far.
   * @param \Drupal\search_api_autocomplete\SearchInterface $search
   *   An object containing details about the search the user is on, and
   *   settings for the autocompletion. See the class documentation for details.
   *   Especially $search->options should be checked for settings, like whether
   *   to try and estimate result counts for returned suggestions.
   * @param string $incomplete_key
   *   The start of another fulltext keyword for the search, which should be
   *   completed. Might be empty, in which case all user input up to now was
   *   considered completed. Then, additional keywords for the search could be
   *   suggested.
   * @param string $user_input
   *   The complete user input for the fulltext search keywords so far.
   *
   * @return \Drupal\search_api_autocomplete\Suggestion\SuggestionInterface[]
   *   An array of suggestions.
   *
   * @see \Drupal\search_api_autocomplete\AutocompleteBackendInterface
   */
  public function getAutocompleteSuggestions(QueryInterface $query, SearchInterface $search, $incomplete_key, $user_input) {
    $suggestions = [];
    if (class_exists(SuggestionFactory::class)) {
      // $factory = new SuggestionFactory($user_input);
    }

    $incomplete_key = mb_strtolower($incomplete_key);
    $user_input = mb_strtolower($user_input);

    return $suggestions;
  }

  /**
   * Sets the current filters on the query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Query interface.
   * @param \Drupal\coveo\Coveo\Search $search
   *   The Coveo Search Class.
   */
  protected function setFilters(QueryInterface $query, Search $search) {
    $condition_group = $query->getConditionGroup();
    if ($aq = $this->createFilterQuery($condition_group)) {
      $search->setAdvancedQuery($aq);
    }
  }

  /**
   * Creates a query filter based off condition groups.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The Condition group to parse.
   *
   * @return string
   *   The query Filter.
   */
  protected function createFilterQuery(ConditionGroupInterface $condition_group) {
    $conditions = $condition_group->getConditions();

    if (!empty($conditions)) {
      $cg = '(';

      $count = 1;
      $total = count($conditions);
      foreach ($conditions as $condition) {
        // Nested Groups.
        if ($condition instanceof ConditionGroupInterface) {
          $cg .= $this->createFilterQuery($condition);
        }
        else {
          $field = $condition->getField();
          $coveo_field = '@' . coveo_PREFIX . $field;
          $operator = $condition->getOperator();
          $values = $condition->getValue();

          // @todo Add missing operators.
          switch ($operator) {
            case 'IN':
              $cg .= $coveo_field . '=(' . implode(',', $values) . ')';
              break;

            case 'NOT IN':
              $cg .= '(';
              $i = 0;
              foreach ($values as $value) {
                if ($i) {
                  $cg .= ' AND ';
                }
                $cg .= $coveo_field . '<>' . $value;
                $i++;
              }
              $cg .= ')';

              break;

            case '=':
              $cg .= $coveo_field . '==' . $values;
              break;

            case '<>':
              $cg .= $coveo_field . '<>' . $values;
              break;

          }
        }

        if ($count < $total) {
          $cg .= ' ' . $condition_group->getConjunction() . ' ';
        }
        $count++;
      }

      $cg .= ')';
    }

    return !empty($cg) ? $cg : '';
  }

  /**
   * Sets the current facets to group by.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param \Drupal\coveo\Coveo\Search $search
   *   The Coveo Search class.
   */
  protected function setFacets(QueryInterface $query, Search $search) {
    $facets = $query->getOption('search_api_facets', []);
    $group_by = [];

    if (empty($facets)) {
      return;
    }

    // Load coveo fields to compare.
    $field = new Field($this->getCoveo());
    if ($coveo_fields = $field->loadUserFields()) {

      $facet_fields = [];
      foreach ($coveo_fields['items'] as $coveo_field) {
        if ($coveo_field['facet'] || $coveo_field['multiValueFacet']) {
          $facet_fields[$coveo_field['name']] = $coveo_field;
        }
      }

      foreach ($facets as $info) {
        $prefixed_field = coveo_PREFIX . $info['field'];
        if (isset($facet_fields[$prefixed_field])) {
          $group_by[] = [
            'field' => '@' . $prefixed_field,
            'maximumNumberOfValues' => empty($info['limit']) ? 100 : $info['limit'],
          ];
        }
      }
    }

    if (!empty($group_by)) {
      $search->setFacets($group_by);
    }
  }

  /**
   * Extracts facets from a Coveo result set.
   *
   * @todo This is only showing facets based on the results. In the case of an
   * 'OR' Facet, we would need to show alternatives or a user can't select a
   * different option.
   *
   * @return array
   *   An array describing facets that apply to the current results.
   */
  protected function extractFacets(array $results) {
    $facets = [];

    if (empty($results['groupByResults'])) {
      return [];
    }

    // @todo If this is an OR filter, we want non-scoped facets to show.
    foreach ($results['groupByResults'] as $facet) {
      // Strip prefix.
      $drupal_field = substr($facet['field'], strlen(coveo_PREFIX));

      if (!empty($facet['values'])) {
        foreach ($facet['values'] as $facet_result) {
          $value = $facet_result['value'];

          $facets[$drupal_field][] = [
            'filter' => "\"$value\"",
            'count' => $facet_result['numberOfResults'],
          ];
        }
      }
    }

    return $facets;
  }

  /**
   * Sets the sort criteria.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Query.
   * @param \Drupal\coveo\Coveo\Search $search
   *   The coveo search class.
   */
  protected function setSorts(QueryInterface $query, Search $search) {
    $sorts = $query->getSorts();

    // @todo This should be checking if a field in coveo is sortable or else
    // a false zeroo results could occur.
    $sc = '';
    foreach ($sorts as $field => $sort) {
      $order = $sort === 'DESC' ? 'descending' : 'ascending';

      // Coveo doesn't allow to search by relevency asc.
      if ($field === 'search_api_relevance') {
        $sc .= 'relevancy';
      }
      else {
        $coveo_field = '@' . coveo_PREFIX . $field;
        $sc .= $coveo_field . ' ' . $order;
      }
      $sc .= ',';
    }

    // Remove last comma.
    $sc = rtrim($sc, ',');

    $search->setSorts($sc);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDataType($type) {
    return in_array($type, [
      'coveo_enity_label',
    ]);
  }

  /**
   * Creates a connection to the Coveo Search server.
   */
  protected function connect($index = NULL) {
    if (!$this->getCoveo()) {
      $this->coveoClient = new Client($this->getApplicationId(), $this->getSourceId(), $this->getApiKey());
    }

    if ($index && $index instanceof IndexInterface) {
      $this->setCoveoIndex($this->coveoClient->initIndex($index->get('id')));
    }
  }

  /**
   * Loads a coveo Search instance.
   *
   * @param \Drupal\search_api\Entity\Index $index
   *   The index to search with.
   *
   * @return \Drupal\coveo\Coveo\Search
   *   A new Coveo Search object.
   */
  protected function getCoveoSearch(Index $index) {
    if (!$this->coveoSearch) {
      $this->connect($index);
      $this->coveoSearch = new Search($this->getCoveo(), $this->getCoveoIndex());
    }
    return $this->coveoSearch;
  }

  /**
   * Retrieves the logger to use.
   *
   * @return \Psr\Log\LoggerInterface
   *   The logger to use.
   */
  public function getLogger() {
    return $this->logger ?: \Drupal::service('logger.channel.coveo');
  }

  /**
   * Sets the logger to use.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger to use.
   *
   * @return $this
   */
  public function setLogger(LoggerInterface $logger) {
    $this->logger = $logger;
    return $this;
  }

  /**
   * Returns the module handler to use for this plugin.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  public function getModuleHandler() {
    return $this->moduleHandler ?: \Drupal::moduleHandler();
  }

  /**
   * Sets the module handler to use for this plugin.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to use for this plugin.
   *
   * @return $this
   */
  public function setModuleHandler(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
    return $this;
  }

  /**
   * Returns the CoveoSearch client.
   *
   * @return \Drupal\coveo\Coveo\Client
   *   The coveo instance object.
   */
  public function getCoveo() {
    return $this->coveoClient;
  }

  /**
   * Get the Coveo index.
   *
   * @return \Drupal\coveo\Coveo\Index
   *   The index.
   */
  protected function getCoveoIndex() {
    return $this->coveoIndex;
  }

  /**
   * Set the Coveo index.
   */
  protected function setCoveoIndex($index) {
    $this->coveoIndex = $index;
  }

  /**
   * Get the ApplicationID (provided by Coveo).
   */
  protected function getApplicationId() {
    return $this->configuration['application_id'];
  }

  /**
   * Get the API key (provided by Coveo).
   */
  protected function getApiKey() {
    return $this->configuration['api_key'];
  }

  /**
   * Get the Push Source ID (provided by Coveo).
   */
  protected function getSourceId() {
    return $this->configuration['source_id'];
  }

  /**
   * Get the Push Source Name (provided by Coveo).
   */
  protected function getSourceName() {
    return $this->configuration['source_name'];
  }

  /**
   * Get the Search page path.
   */
  protected function getSearchPage() {
    return $this->configuration['search_page'];
  }

}
