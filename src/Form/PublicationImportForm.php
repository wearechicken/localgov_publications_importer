<?php

namespace Drupal\localgov_publications_importer\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\localgov_publications_importer\Service\Importer as PublicationImporter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\file\Entity\File;
/**
 * Publication import form.
 */
class PublicationImportForm extends FormBase {

  /**
   * Publication importer service
   *
   * @var \Drupal\localgov_publications_importer\Service\Importer
   */
  protected $publicationImporter;

  /**
   * Constructor.
   *
   * @param \Drupal\localgov_publications_importer\Service\Importer $publicationImporter
   *   Publication importer.
   */
  public function __construct(PublicationImporter $publicationImporter) {
    $this->publicationImporter = $publicationImporter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('localgov_publications_importer.importer')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'publication_import_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $form['#attributes'] = ['enctype' => 'multipart/form-data'];

    $form['my_file'] = array(
      '#type' => 'managed_file',
      '#name' => 'my_file',
      '#title' => t('File *'),
      '#size' => 20,
      '#description' => t('PDF format only'),
      '#upload_validators' => [
        'file_validate_extensions' => ['pdf'],
      ],
      // @todo: Upload to private.
      '#upload_location' => 'public://my_files/',
    );

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    // Need to get file details i.e upload file name, size etc.

    [$fid] = $form_state->getValue('my_file');
    $file = File::load($fid);

    $node = $this->publicationImporter->importPdf($file->uri->value);

    if ($node) {
      // redirect to the node...
      $form_state->setRedirect('entity.node.canonical', ['node' => $node->id()]);
    }

    // Else set a fail message
  }

}
