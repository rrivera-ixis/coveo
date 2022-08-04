<?php

/**
 * @file
 * Hooks provided by the Search API Coveo search module.
 */

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Alter Coveo objects before they are sent to Coveo for indexing.
 *
 * @param array $objects
 *   An array of objects ready to be indexed, generated from $items array.
 * @param \Drupal\search_api\IndexInterface $index
 *   The search index for which items are being indexed.
 * @param \Drupal\search_api\Item\ItemInterface[] $items
 *   An array of items to be indexed, keyed by their item IDs.
 */
function hook_coveo_objects_alter(array &$objects, \Drupal\search_api\IndexInterface $index, array $items) {
  // Adds a "foo" field with value "bar" to all documents.
  foreach ($objects as $key => $object) {
    $objects[$key]['foo'] = 'bar';
  }
}

/**
 * @} End of "addtogroup hooks".
 */
