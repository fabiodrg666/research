<?php

namespace Drupal\oai_import\Model;

/**
 * Parses and translates OAI imported data.
 */
class OaiParser {

  /**
   * Fields from OAI XML data that we extract.
   */
  const OAI_FIELDS = [
    'title',
    'creator',
    'date',
    'subject',
    'description',
    'type',
    'identifier',
    'publisher',
  ];

  /**
   * Minimum length of an ISBN.
   */
  const ISBN_MINIMUM_SIZE = 10;

  /**
   * Maximum length of an ISBN.
   */
  const ISBN_MAXIMUM_SIZE = 50;

  /**
   * XML Document from OAI.
   *
   * @var \DOMDocument
   */
  protected $xmlDocument;

  /**
   * Xpath parser.
   *
   * @var \DOMXpath
   */
  protected $xpath;

  /**
   * Creates a new parser instance from XML Document.
   *
   * @param \DOMDocument $xml_document
   *   Document to parse for OAI data.
   */
  public function __construct(\DOMDocument $xml_document) {
    $this->xmlDocument = $xml_document;
    $this->xpath = new \DOMXpath($this->xmlDocument);
    $this->xpath->registerNamespace('oai', 'http://www.openarchives.org/OAI/2.0/');
    $this->xpath->registerNamespace('oai-dc', 'http://www.openarchives.org/OAI/2.0/oai_dc/');
    $this->xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');
  }

  /**
   * Extract the fields and values from an OAI record.
   *
   * @param \DOMElement $node
   *   Element to be evaluated (record).
   *
   * @return array
   *   Extracted record information.
   */
  protected function getFieldsFromOai(\DOMElement $node): array {
    $result = [];
    foreach (self::OAI_FIELDS as $field) {
      $selector = sprintf('dc:%s', $field);
      $items = $this->xpath->evaluate($selector, $node, FALSE);
      foreach ($items as $i) {
        $result[$field][] = $i->nodeValue;
      }
    }
    return $result;
  }

  /**
   * Extracts the year from the several date formats found in OAI data.
   *
   * @param string $date
   *   OAI date string.
   *
   * @return string
   *   Parsed year.
   */
  protected function parseOaiDate(string $date): string {
    return substr($date, 0, 4);
  }

  /**
   * Translates the reference type from OAI to bicite.
   *
   * @param string $type
   *   OAI type.
   *
   * @return string
   *   Bibcite type.
   */
  protected function translateOaiType(string $type): string {
    $types = [
      'info:eu-repo/semantics/article'          => 'journal_article',
      'info:eu-repo/semantics/conferenceObject' => 'conference_paper',
      'info:eu-repo/semantics/doctoralThesis'   => 'thesis',
      'info:eu-repo/semantics/book'             => 'book',
    ];
    return $types[$type] ?? 'journal';
  }

  /**
   * Searches the OAI identifier for ISSN, ISBN,...
   *
   * @param array $values
   *   Identifier values from OAI.
   * @param string $field
   *   Field to search for (ISSN, ISBN,...)
   *
   * @return string
   *   Found identifier value.
   */
  protected function parseIdentifier(array $values, string $field) {
    $parsed = NULL;
    if (!empty($values)) {
      foreach ($values as $value) {
        // Search for the field text.
        $field_pos = strpos($value, $field);
        if ($field_pos !== FALSE) {
          // Value is expected to be in the text imediately after the field.
          // terminated by ".".
          $remaining_string = substr($value, $field_pos + strlen($field) + 1);
          // Trim the string after the period (if one is found).
          $period_pos = strpos($remaining_string, '.');
          if ($period_pos !== FALSE) {
            $parsed = substr($remaining_string, 0, $period_pos);
          }
          else {
            $parsed = $remaining_string;
          }
          break;
        }
      }
    }
    return $parsed;
  }

  /**
   * Try to guess if value is an URL.
   *
   * @param string $value
   *   String to parse.
   *
   * @return bool
   *   Is URL or not.
   */
  protected function isUrl(string $value): bool {
    return ((substr($value, 0, 7) == 'http://') || (substr($value, 0, 8) == 'https://'));
  }

  /**
   * Try to guess the identifier ISSN or ISBN from the type.
   *
   * @param string $identifier
   *   Unindentified identifier value.
   * @param string $type
   *   Document type.
   *
   * @return string
   *   Guessed Identifier.
   */
  protected function guessIdentifierFromTypeAndSize(string $identifier, string $type) {
    $guessed = NULL;
    // Ignore URLs or other too large text, here we're looking for ISBN or ISSN.
    if ((!$this->isUrl($identifier)) && (strlen($identifier) <= self::ISBN_MAXIMUM_SIZE)) {
      $identifiers = [
        'info:eu-repo/semantics/conferenceObject' => 'isbn',
        'info:eu-repo/semantics/doctoralThesis'   => 'isbn',
        'info:eu-repo/semantics/book'             => 'isbn',
      ];
      if (!empty($identifiers[$type])) {
        $guessed = [$identifiers[$type] => $identifier];
      }
      else {
        // Could not determine by type, use string size.
        if (strlen($identifier) >= self::ISBN_MINIMUM_SIZE) {
          $guessed['isbn'] = $identifier;
        }
        else {
          $guessed['issn'] = $identifier;
        }
      }
    }
    return $guessed;
  }

  /**
   * Parses the URL in the identifier.
   *
   * @param array $identifier
   *   OAI Identifier.
   *
   * @return string
   *   Parsed URL.
   */
  protected function parseLocatorUrl(array $identifier): string {
    $url = NULL;
    if (!empty($identifier)) {
      foreach ($identifier as $identifier_row) {
        if ($this->isUrl($identifier_row)) {
          $url = $identifier_row;
          break;
        }
      }
    }
    return $url;
  }

  /**
   * Creates a new OaiReference object from OAI parsed data.
   *
   * @param array $oai_data
   *   Data parsed from OAI.
   *
   * @return OaiReference
   *   Created object.
   */
  protected function createReferenceFromData(array $oai_data): OaiReference {
    // Perform basic validation on mandatory fields: Title and author.
    if (!isset($oai_data['title'])) {
      throw new \Exception('Document has no title.');
    }
    if (!isset($oai_data['creator'])) {
      throw new \Exception('Document has no author information.');
    }
    $title = current($oai_data['title']);
    $url = $this->parseLocatorUrl($oai_data['identifier']);
    if (empty($url)) {
      throw new \Exception('Handle URL cannot be empty');
    }
    if (empty($title)) {
      throw new \Exception('Document title cannot be empty.');
    }
    if (empty($oai_data['creator'])) {
      throw new \Exception('Document author information cannot be empty.');
    }
    $reference = new OaiReference();
    $reference->title = $title;
    $reference->authors = $oai_data['creator'];
    if (isset($oai_data['date'])) {
      $reference->year = $this->parseOaiDate(current($oai_data['date']));
    }
    // Merge all description fields as abstract.
    if (isset($oai_data['description'])) {
      $reference->abstract = implode("\n", $oai_data['description']);
    }
    $reference->keywords = $oai_data['subject'] ?? [];
    if (isset($oai_data['type'])) {
      $reference->type = $this->translateOaiType(current($oai_data['type']));
    }
    if (isset($oai_data['publisher'])) {
      $reference->publisher = current($oai_data['publisher']);
    }
    if (isset($oai_data['identifier'])) {
      $reference->issn = $this->parseIdentifier($oai_data['identifier'], 'ISSN');
      $reference->isbn = $this->parseIdentifier($oai_data['identifier'], 'ISBN');
      // If identifier could not be parsed, try to guess from type and size.
      if (empty($reference->issn) && empty($reference->isbn)) {
        if (!empty($oai_data['identifier'])) {
          foreach ($oai_data['identifier'] as $identifier_row) {
            $guessed = $this->guessIdentifierFromTypeAndSize(
              $identifier_row,
              current($oai_data['type'])
            );
            if (!empty($guessed['isbn'])) {
              $reference->isbn = $guessed['isbn'];
              break;
            }
            if (!empty($guessed['issn'])) {
              $reference->issn = $guessed['issn'];
              break;
            }
          }
        }
      }
      $reference->url = $url;
    }
    return $reference;
  }

  /**
   * Parses XML data from the document.
   *
   * @return array
   *   Extracted data from XML as array.
   */
  public function parseOaiData(): array {
    $records = $this->xpath->evaluate('/oai:OAI-PMH/oai:ListRecords/oai:record', NULL, FALSE);
    if ($records->length < 1) {
      throw new \Exception('Document has no records.');
    }
    $result = [];
    foreach ($records as $record) {
      $oai = $this->xpath->evaluate('oai:metadata/oai-dc:dc', $record, FALSE);
      foreach ($oai as $item) {
        $result[] = $this->createReferenceFromData($this->getFieldsFromOai($item));
      }
    }
    return $result;
  }

  /**
   * Extract XML pagination data.
   *
   * @return string
   *   Pagination URL suffix.
   */
  public function getPaginationData() {
    $pagination_tag = $this->xmlDocument->getElementsByTagName('resumptionToken');
    if ($pagination_tag->length > 0) {
      return $pagination_tag->item(0)->nodeValue;
    }
    else {
      return NULL;
    }
  }

}
