<?php
/**
 * @file
 * Drush command to perform OAI import from CLI.
 */

/**
 * Implements hook_drush_command().
 */
function oai_import_drush_command() {
  $items['oai-import'] = [
    'description' => 'Imports data from an OAI URL.',
    'aliases'     => ['oai'],
    'options'     => [
      'url' => 'URL for OAI XML data'
    ]
  ];
  return $items;
}

/**
 * Perform OAI data import.
 */
function drush_oai_import() {
  $url = drush_get_option('url');
  if (empty($url)) {
    return drush_set_error(dt('Error: Please specify URL.'));
  }
  else {
    // Filter the URL value.
    $url = filter_var($url, FILTER_VALIDATE_URL);
    if ($url === FALSE) {
      return drush_set_error(dt('Error: URL is invalid.'));
    }
    $importer = \Drupal::service('oai_import.importer');
    try {
      $imported = $importer->importDataFromUrl($url);
      drush_print(sprintf('Imported %s references', $imported));
    }
    catch (\Exception $e) {
      return drush_set_error(dt($e->getMessage()));
    }
  }
}

