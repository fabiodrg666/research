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
  
}
