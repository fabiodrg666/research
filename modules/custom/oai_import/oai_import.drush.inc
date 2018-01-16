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
  $items['users-import'] = [
    'description' => 'Import users.',
    'aliases'     => ['usi'],
    'options'     => [
      'file' => 'tsv users file'
    ],
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

function drush_oai_import_users_import() {
  $users = file(drush_get_option('file'));
  if (!empty($users)) {
    $member_level = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties(['name' => 'Integrated member']);
    $fields = [
      'email',
      'bio',
      'name',
      'phone',
      'position',
      'website',
      'degois_id'
    ];
    foreach ($users as $user_row) {
      $user = array_combine($fields, explode(chr(9), $user_row));

      $position = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_term')
        ->loadByProperties(['name' => $user['position']]);

      $account = \Drupal\user\Entity\User::create();
      $account->setUsername($user['email']);
      $account->setPassword(user_password());
      $account->setEmail($user['email']);
      $account->enforceIsNew();
      $account->set('field_bio', $user['bio']);
      $account->set('field_name', $user['name']);
      $account->set('field_phone', $user['phone']);
      $account->set('field_website', $user['website']);
      $account->set('field_degois_id', $user['degois_id']);
      $account->set('field_position', $position);
      $account->set('field_member_level', $member_level);
      $account->activate();
      $account->save();
    }
  }
}

