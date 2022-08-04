<?php

namespace Drupal\coveo\Coveo;

/**
 * Contains all the functions related to a Search Query.
 */
class Search {

  /**
   * The Coveo Search base.
   */
  const BASE_URL = 'https://platform.cloud.coveo.com/rest/search/v2';

  /**
   * The coveo client.
   *
   * @var \Drupal\coveo\Coveo\Client
   */
  private $client;

  /**
   * The context of this search.
   *
   * @var \Drupal\coveo\Coveo\ClientContext
   */
  private $context;

  /**
   * The query to send to Coveo.
   *
   * @var array
   */
  protected $query = [];

  /**
   * The index.
   *
   * @var \Drupal\coveo\Coveo\Index
   */
  private $index;

  /**
   * Search constructor.
   *
   * @param \Drupal\coveo\Coveo\Client $client
   *   The client.
   * @param \Drupal\coveo\Coveo\Index $index
   *   The index.
   */
  public function __construct(Client $client, Index $index) {
    $this->client = $client;
    $this->index = $index;
  }

  /**
   * Executes a search and returns the results.
   *
   * @return array
   *   The search results as an array.
   */
  public function execute() {
    try {
      $results = $this->client->request(
        $this->getContext(),
        'POST',
        '',
        $this->getParams(),
        $this->getQuery(),
        self::BASE_URL
      );
    }
    catch (\Exception $e) {
      $results = ['results' => []];
      // @todo Fix handling of a search error.
    }
    return $results;
  }

  /**
   * Get the current context.
   *
   * @return \Drupal\coveo\Coveo\ClientContext
   *   The context.
   */
  protected function getContext() {
    if (!$this->context) {
      $this->context = $this->index->getContext();
    }
    return $this->context;
  }

  /**
   * Get the url parameters.
   *
   * @return array
   *   Current query params.
   */
  public function getParams() {
    return ['organizationId' => $this->getContext()->applicationID];
  }

  /**
   * Getter for the query.
   *
   * @return array
   *   The search query.
   */
  public function getQuery() {
    return $this->query;
  }

  /**
   * Setter for the keywords.
   *
   * @param string $keywords
   *   The keyword.
   */
  public function setKeywords($keywords) {
    if (!empty($keywords)) {
      $this->query['q'] = $keywords;
    }
  }

  /**
   * Setter for sorts.
   *
   * @param string $sorts
   *   The sort params.
   */
  public function setSorts($sorts) {
    if (!empty($sorts)) {
      $this->query['sortCriteria'] = $sorts;
    }
  }

  /**
   * Sets an advanced query parameter.
   *
   * @param mixed $aq
   *   The advanced query.
   * @param bool $override
   *   Whether to override current query.
   */
  public function setAdvancedQuery($aq, $override = FALSE) {
    if (!empty($aq)) {
      if (!isset($this->query['aq']) || $override) {
        $this->query['aq'] = $aq;
      }
      else {
        $this->query['aq'] .= ' AND ' . $aq;
      }
    }
  }

  /**
   * Set facets to show.
   *
   * @param array $groupBy
   *   The groupBy array.
   */
  public function setFacets(array $groupBy) {
    $this->query['groupBy'] = $groupBy;
  }

}
