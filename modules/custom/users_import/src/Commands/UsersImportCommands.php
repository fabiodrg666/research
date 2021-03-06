<?php
namespace Drupal\users_import\Commands;

use Drush\Commands\DrushCommands;
use Drupal\bibcite_entity\Entity\Contributor;

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
class UsersImportCommands extends DrushCommands {

  /**
   * Perform users import.
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
      $fields = [
        'email',
        'name',
        'institution',
        'picture',
        'member_level',
        'position',
        'author_first_name',
        'author_last_name',
        'bio',
        'phone',
        'website',
        'orcid_id',
        'researchgate_id',
        'degois_id',
        'researcher_id',
        'scopus_id',
        'google_scholar_user',
        'roles',
      ];
      $is_header = TRUE;
      foreach ($users as $user_row) {
        if ($is_header) {
          $is_header = FALSE;
          continue;
        }
        $user = array_combine($fields, explode(chr(9), $user_row));

        $position = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->loadByProperties(['name' => $user['position']]);
        $member_level = \Drupal::entityTypeManager()
          ->getStorage('taxonomy_term')
          ->loadByProperties(['name' => $user['member_level']]);

          //upload user picture
          $userimgname = trim($user['picture']);

          $path = "./" . drupal_get_path('module', 'users_import') . "/users_img/";

          $contents = file_get_contents($path . $userimgname, FILE_USE_INCLUDE_PATH);

          $file = "";
          
          if(!empty($contents)){
              $uri = 'public://pictures/' . $userimgname;
              $file = file_save_data($contents, $uri, FILE_EXISTS_RENAME);
              $file->setOwnerId(1);
              $file->save();
          }

          //get authors
          $author_first_name = explode(",", $user['author_first_name']);
          $author_last_name = explode(",", $user['author_last_name']);
          $authors = array();
          for($i = 0; $i <= sizeof($user['author_first_name']); $i++){
              $query = \Drupal::entityQuery('bibcite_contributor');
              $query->condition('first_name', $author_first_name[$i]);
              $query->condition('last_name', $author_last_name[$i]);
              $id = current($query->execute());

              if(!empty($id)){
                  $authors[] = $id;
              }
          }

          //get roles
          $user_roles = trim($user['roles']);
          $roles = explode(',', $user_roles);

          //lower case & underscore
          foreach ($roles as $key => $value){
              $roles[$key] = strtolower(str_replace(' ', '_', $value));
          }

          // Remove role "Administrator" if exists
          $admin = array_search('administrator', $roles);
          if(!empty($admin)){
              unset($roles[$admin]);
          }

        $account = \Drupal\user\Entity\User::create();
        $account->setUsername($user['email']);
        $account->setPassword(user_password());
        $account->setEmail($user['email']);
        $account->enforceIsNew();
        $account->set('field_author', $authors);
        $account->set('field_bio', $user['bio']);
        $account->set('field_degois_id', $user['degois_id']);
        $account->set('field_google_scholar_user', $user['google_scholar_user']);
        $account->set('field_institu', $user['institution']);
        $account->set('field_member_level', $member_level);
        $account->set('field_name', $user['name']);
        $account->set('field_orcid', $user['orcid_id']);
        $account->set('field_phone', $user['phone']);
        $account->set('field_position', $position);
        $account->set('field_research_id', $user['researcher_id']);
        $account->set('field_research_gate_id', $user['researchgate_id']);
        $account->set('field_scopus_id', $user['scopus_id']);
        $account->set('field_website', $user['website']);
        $account->set('user_picture', $file);
          foreach ($roles as $role){
              $account->addRole($role);
          }
        $account->activate();
        $account->save();
      }
    }
  }
}
