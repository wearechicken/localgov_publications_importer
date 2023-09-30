<?php

namespace Drupal\localgov_publications_importer\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\node\NodeInterface;
use Drupal\paragraphs\Entity\Paragraph;
use Smalot\PdfParser\Config as PdfParserConfig;
use Smalot\PdfParser\Parser as PdfParser;

class Importer {

  /**
   * Entity type manager.
   *
   * @var EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
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

    $publication = $nodeStorage->create([
      'type' => 'localgov_publication_page',
      'title' => $title,
    ]);

    $content = '';

    foreach ($pdf->getPages() as $key => $page) {
      $content .= $page->getText();
    }

    // One of the example PDFs I tried came out wth \t\n after every single
    // word, which rendered as line breaks and made the output a single column
    // of words. Swop these for spaces.
    $content = str_replace("\t\n", ' ', $content);

    $this->addBodyAsParagraph($publication, $content);

    $publication->save();

    return $publication;
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

    $node->get('localgov_page_content')->setValue($paragraphList);
  }

}
