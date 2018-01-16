<?php
namespace Drupal\oai_import\Commands;

use Drush\Commands\DrushCommands;

/**
 *
 * In addition to a commandfile like this one, you need a drush.services.yml
 * in root of your module, and a composer.json file that provides the name
 * of the services file to use.
 *
 * See these files for an example of injecting Drupal services:
 *   - http://cgit.drupalcode.org/devel/tree/src/Commands/DevelCommands.php
 *   - http://cgit.drupalcode.org/devel/tree/drush.services.yml
 */
class OaiImportCommands extends DrushCommands {

  /**
   * Imports data from an OAI URL.
   *
   * @command oai_import:import
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   * @option url URL for OAI XML data
   * @aliases oai,oai-import
   */
  public function import(array $options = ['url' => null])
  {
    $url = $options['url'];
    if (empty($url)) {
      throw new \Exception('Error: Please specify URL.');
    }
    else {
      // Filter the URL value.
      $url = filter_var($url, FILTER_VALIDATE_URL);
      if ($url === FALSE) {
        throw new \Exception('Error: URL is invalid.');
      }
      $importer = \Drupal::service('oai_import.importer');
      try {
        $imported = $importer->importDataFromUrl($url);
        $this->output->writeln(sprintf('Imported %s references', $imported));
      }
      catch (\Exception $e) {
        throw new \Exception($e->getMessage());
      }
    }
  }

  /**
   * Perform OAI data import.
   *
   * @command oai_import:users
   * @param array $options An associative array of options whose values come from cli, aliases, config, etc.
   * @option file tsv users file
   * @aliases users-import,usi
   */
  public function users(array $options = ['file' => null])
  {
    $users = file($options['file']);
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
}
