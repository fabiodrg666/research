<?php

namespace Drupal\oai_import\Model;

/**
 * Reference Data as Imported from OAI. To be inserted as Bibcite reference.
 */
class OaiReference {

  /**
   * Document title.
   *
   * @var string
   */
  public $title;

  /**
   * Document authors.
   *
   * @var array
   */
  public $authors;

  /**
   * Document publication year.
   *
   * @var string
   */
  public $year;

  /**
   * Document abstract.
   *
   * @var string
   */
  public $abstract;

  /**
   * Document keywords.
   *
   * @var array
   */
  public $keywords;

  /**
   * Document type.
   *
   * @var string
   */
  public $type;

  /**
   * Document ISSN number.
   *
   * @var string
   */
  public $issn;

  /**
   * Document ISBN number.
   *
   * @var string
   */
  public $isbn;

  /**
   * Locators URL.
   *
   * @var string
   */
  public $url;

  /**
   * Document publisher.
   *
   * @var string
   */
  public $publisher;

}
