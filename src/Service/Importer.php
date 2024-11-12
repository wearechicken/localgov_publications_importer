<?php

namespace Drupal\localgov_publications_importer\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser as PdfParser;

class Importer {

  /**
   * Constructor.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager
  ) {
  }

  /**
   * Imports the given file as a new Localgov Publication page.
   */
  function importPdf($pathToFile) {

    $nodeStorage = $this->entityTypeManager->getStorage('node');

    $config = new PdfParserConfig();
    // An empty string can prevent words from breaking up
    $config->setHorizontalOffset('');

    // Parse PDF file and build necessary objects.
    $parser = new PdfParser([], $config);
    $pdf = $parser->parseFile($pathToFile);

    $title = 'Publication';

    $details = $pdf->getDetails();

    if (isset($details['Title'])) {
      $title = $details['Title'];
    }

    $rootPage = NULL;

    // Get the pages and sort them. They don't come back in order by default.
    $pages = $pdf->getPages();
    usort($pages, function ($a, $b) { return intval($a->getPageNumber()) <=> intval($b->getPageNumber()); });

    $weight = 0;

    foreach ($pages as $page) {

      if ($rootPage === NULL) {
        $book = [
          'bid' => 'new',
        ];
      }
      else {
        $book = [
          'bid' => $rootPage->id(),
          'pid' => $rootPage->id(),
          'weight' => $weight++,
        ];
        $title = 'Page ' . $page->getPageNumber();
      }

      $publicationPage = $nodeStorage->create([
        'type' => 'localgov_publication_page',
        'title' => $title,
        'book' => $book,
      ]);

      // One of the example PDFs I tried came out wth \t\n after every single
      // word, which rendered as line breaks and made the output a single column
      // of words. Swop these for spaces.
      $content = str_replace("\t\n", ' ', $page->getText());

      $client = \OpenAI::client('');

      $result = $client->chat()->create([
        'model' => 'gpt-3.5-turbo', // Was gpt-4
        'messages' => [
          [
            'role' => 'user',
            'content' => $content,
          ],
          [
            'role' => 'system',
            'content' => 'This plain text document has been stripped of its formatting. Please add the formatting back in, and give me the whole document back as valid HTML.'
          ],
        ],
      ]);

      $content = $result->choices[0]->message->content;

      $this->addBodyAsParagraph($publicationPage, $content);

      $publicationPage->save();

      if ($rootPage === NULL) {
        $rootPage = $publicationPage;
      }
    }

    return $rootPage;
  }

  /**
   * Add field_section_body as a paragraph.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The destination node.
   * @param array $source
   *   Source data.
   * @param string $sourceField
   *   Source field to read.
   */
  public function addBodyAsParagraph(NodeInterface $node, string $text): void {

    // Create the paragraph that holds the text. NB that both the paragraph
    // and the field on it are called 'localgov_text'.
    $paragraph = Paragraph::create([
      'type' => 'localgov_text',
      'localgov_text' => [
        'value' => $text,
        'format' => 'wysiwyg',
      ],
    ]);
    $paragraph->save();

    $paragraphList[] = [
      'target_id' => $paragraph->id(),
      'target_revision_id' => $paragraph->getRevisionId(),
    ];

    $node->get('localgov_publication_content')->setValue($paragraphList);
  }

}
