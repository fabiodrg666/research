<?php

namespace Drupal\oai_import\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\oai_import\Model\Import;

/**
 * Controller class for OAI import.
 */
class ImportController extends ControllerBase {

  /**
   * Import model.
   *
   * @var Drupal\oai_import\Model\Import
   */
  protected $model;

  /**
   * Class constructor.
   */
  public function __construct() {
    $this->model = new Import();
  }

  /**
   * Action to display the import form.
   *
   * @return array
   *   Render array.
   */
  public function index() {
    return [
      '#theme'    => 'index',
      '#attached' => [
        'library' => [
          'oai_import/custom.oai_import',
        ],
      ],
    ];
  }

  /**
   * Action to process the import (POST).
   */
  public function import() {
    // Wait up to 5 mins.
    set_time_limit(60 * 5);
    $url = filter_var($_POST['oai_url'], FILTER_SANITIZE_URL);
    try {
      $this->model->importDataFromUrl($url);
    }
    catch (\Exception $e) {
      die('Error: ' . $e->getMessage());
    }
  }

}
