<?php

namespace Drupal\oai_import\Model;

use Drupal\bibcite_entity\Entity\Reference;
use Drupal\bibcite_entity\Entity\Contributor;
use Drupal\bibcite_entity\Entity\Keyword;

/**
 * Model for the import of OAI data.
 */
class Import {

  /**
   * Search an existing entity by name.
   *
   * @param mixed $name
   *   Entity name to search.
   * @param string $entity_type
   *   Author last name.
   *
   * @return mixed
   *   Referenced entity.
   */
  public function getReferencedEntity($name, string $entity_type) {
    $entity = NULL;
    switch ($entity_type) {
      case 'author':
        $query = \Drupal::entityQuery('bibcite_contributor');
        $query->condition('first_name', $name['first_name']);
        $query->condition('last_name', $name['last_name']);
        $id = current($query->execute());
        if (!empty($id)) {
          $entity = Contributor::load($id);
        }
        break;

      case 'keyword':
        $query = \Drupal::entityQuery('bibcite_keyword');
        $query->condition('name', $name);
        $id = current($query->execute());
        if (!empty($id)) {
          $entity = Keyword::load($id);
        }
        break;

      default:
        break;
    }
    return $entity;
  }

  /**
   * Creates new referenced entity, returning the created object.
   *
   * @param mixed $name
   *   Entity name.
   * @param string $entity_type
   *   Entity type.
   *
   * @return mixed
   *   Created entity
   */
  public function createReferencedEntity($name, string $entity_type) {
    switch ($entity_type) {
      case 'author':
        $entity = Contributor::create([
          'type' => 'entity_contributor',
          'first_name' => $name['first_name'],
          'last_name' => $name['last_name'],
        ]);
        break;

      case 'keyword':
        $entity = Keyword::create([
          'type' => 'entity_keyword',
          'name' => $name,
        ]);
        break;

      default:
        $entity = NULL;
        break;
    }
    if (!empty($entity)) {
      $entity->save();
    }
    return $entity;
  }

  /**
   * Perform necessary transformations on referenced item name.
   *
   * @param string $name
   *   Original name as obtained from Scopus.
   * @param string $entity_type
   *   Entity type to determine transformation needed.
   *
   * @return mixed
   *   Processed entity name.
   */
  protected function processReferenceName(string $name, string $entity_type) {
    switch ($entity_type) {
      case 'author':
        $name_parts = explode(', ', $name);
        $processed = [
          'first_name'  => $name_parts[1],
          'last_name'   => $name_parts[0],
        ];
        break;

      default:
        $processed = $name;
    }
    return $processed;
  }

  /**
   * Obtain referenced entities by name.
   *
   * @param array $names
   *   List of names to process.
   * @param string $entity_type
   *   Type of entity to process.
   *
   * @return array
   *   Array of entities.
   */
  protected function getReferencedItemsFromNames(array $names, string $entity_type): array {
    $entities = [];
    if (!empty($names)) {
      foreach ($names as $name) {
        // Some entities need the name to be processed.
        $name_processed = $this->processReferenceName($name, $entity_type);
        $entity = $this->getReferencedEntity($name_processed, $entity_type);
        if (empty($entity)) {
          $entity = $this->createReferencedEntity($name_processed, $entity_type);
        }
        $entities[] = $entity;
      }
    }
    return $entities;
  }

  /**
   * Obtains the XML Document to be parsed from an URL.
   *
   * @param string $url
   *   URL to download OAI XML data.
   *
   * @return \DOMDocument
   *   Document as DOMDocument object to be parsed.
   */
  protected function getDocumentFromUrl($url): \DOMDocument {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $xml_data = curl_exec($ch);
    $response = curl_getinfo($ch);
    if ($response['http_code'] != 200) {
      throw new \Exception(sprintf(
        'Invalid response code from server. Expected 200, got %s. ',
        $response['http_code']
      ));
    }
    $doc = new \DOMDocument();
    $doc->loadXML($xml_data);
    if ($doc === FALSE) {
      throw new \Exception('Response could not be parsed as XML.');
    }
    return $doc;
  }

  /**
   * Finds a bibcite reference from it's URl.
   *
   * @param string $url
   *   URL to search.
   *
   * @return Drupal\bibcite_entity\Entity\Reference
   *   Reference object requested.
   */
  public function getReferenceByUrl(string $url) {
    $query = \Drupal::entityQuery('bibcite_reference');
    $query->condition('bibcite_url', $url);
    $id = current($query->execute());
    return Reference::load($id);
  }

  /**
   * Saves reference entity.
   *
   * @param OaiReference $item
   *   Item parsed from OAI.
   */
  protected function saveReference(OaiReference $item) {
    $authors = $this->getReferencedItemsFromNames($item->authors, 'author');
    $keywords = $this->getReferencedItemsFromNames($item->keywords, 'keyword');
    // Verifies if reference already exists, and updates if so.
    $reference = $this->getReferenceByUrl($item->url);
    if (!empty($reference)) {
      $reference->set('type', $item->type);
      $reference->set('title', $item->title);
      $reference->set('author', $authors);
      $reference->set('bibcite_year', $item->year);
      $reference->set('bibcite_abst_e', $item->abstract);
      $reference->set('bibcite_issn', $item->issn);
      $reference->set('bibcite_isbn', $item->isbn);
      $reference->set('bibcite_publisher', $item->publisher);
      $reference->set('keywords', $keywords);
    }
    else {
      $reference = Reference::create([
        'type'              => $item->type,
        'title'             => $item->title,
        'author'            => $authors,
        'bibcite_year'      => $item->year,
        'bibcite_abst_e'    => $item->abstract,
        'bibcite_issn'      => $item->issn,
        'bibcite_isbn'      => $item->isbn,
        'bibcite_url'       => $item->url,
        'bibcite_publisher' => $item->publisher,
        'keywords'          => $keywords,
      ]);
    }
    $reference->save();
  }

  /**
   * Assembles the URL for fetching paginated data.
   *
   * @param string $url
   *   Original URL.
   * @param string $pagination
   *   resumptionToken sting.
   *
   * @return string
   *   URL for next page
   */
  protected function assemblePaginationUrl(string $url, string $pagination): string {
    $url_parts = parse_url($url);
    return sprintf(
      '%s://%s%s?verb=ListRecords&resumptionToken=%s',
      $url_parts['scheme'],
      $url_parts['host'],
      $url_parts['path'],
      $pagination
    );
  }

  /**
   * Imports OAI data from URL provided.
   *
   * @param string $url
   *   OAI URL to fetch data.
   *
   * @return int
   *   Number of inserted references.
   */
  public function importDataFromUrl($url): int {
    $data = [];
    $done_fetching = FALSE;
    $steps = 0;
    $max_steps = 100;
    do {
      $steps++;
      $parser = new OaiParser($this->getDocumentFromUrl($url));
      $data = array_merge($data, $parser->parseOaiData());
      $pagination = $parser->getPaginationData();
      if (empty($pagination)) {
        $done_fetching = TRUE;
      }
      else {
        $url = $this->assemblePaginationUrl($url, $pagination);
      }
    } while ((!$done_fetching) && ($steps < $max_steps));

    if (!empty($data)) {
      foreach ($data as $item) {
        $this->saveReference($item);
      }
    }
    return count($data);
  }

}
