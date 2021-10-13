<?php

namespace Drupal\media_download_path\Plugin\Field\FieldWidget;

use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\EntityBrowserFormInterface;
use Drupal\media\MediaInterface;
use Drupal\media_download_path\MediaDownloadPathMediaHelperTrait;
use Drupal\media_download_path\Plugin\Field\FieldType\MediaDownloadPathItem;
use Drupal\media_library\Form\FileUploadForm;
use Drupal\path\Plugin\Field\FieldWidget\PathWidget;

/**
 * Plugin implementation of the 'path' widget.
 *
 * @FieldWidget(
 *   id = "media_download_path",
 *   label = @Translation("Download URL alias"),
 *   field_types = {
 *     "media_download_path"
 *   }
 * )
 */
class MediaDownloadPathWidget extends PathWidget {

  use MediaDownloadPathMediaHelperTrait;

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    $entity = $items->getEntity();
    $element = parent::formElement($items, $delta, $element,$form, $form_state);

    $element['alias']['#description'] = $this->t('Specify an alternative path by which this data can be accessed. For example, type "/my-document.pdf" for an PDF document.');
    $element['source']['#value'] = !$entity->isNew() ? '/' . MediaDownloadPathItem::getMediaDownloadPath($entity) : NULL;

    $element['#element_validate'][] = [self::class, 'validateFileExtension'];

    // If the advanced settings tabs-set is available (normally rendered in the
    // second column on wide-resolutions), place the field as a details element
    // in this tab-set.
    if (isset($form['advanced'])) {
      $element['#title'] = $this->t('Download URL alias');
      $element['#attributes']['class'] = ['media-download-path-form'];
      $element['#attached']['library'] = ['media_download_path/download_path.tabs'];
      $element['#weight'] = 31;
    }

    return $element;
  }

  /**
   * Form element validation handler for URL alias extension.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  public static function validateFileExtension(array &$element, FormStateInterface $form_state) {
    $entity = self::getEntityFromFormState($form_state);

    // Check that the entity not media.
    if (!($entity instanceof MediaInterface)) {
      return;
    }

    // Form object.
    $form_object = $form_state->getFormObject();

    // Prepare alias.
    $alias = NULL;

    // Entity form.
    if ($form_object instanceof EntityFormInterface) {
      $alias = $form_state->getValue([MediaDownloadPathItem::FIELD_NAME, 0, 'alias']);
    }
    // Entity browser form.
    elseif ($form_object instanceof EntityBrowserFormInterface) {
      $alias = $form_state->getValue(['entity', MediaDownloadPathItem::FIELD_NAME, 0, 'alias']);
    }
    // File upload form.
    elseif ($form_object instanceof FileUploadForm) {
      $alias = $form_state->getValue(['media', 0, 'fields', MediaDownloadPathItem::FIELD_NAME, 0, 'alias']);
    }

    // Check that no alias.
    if (!$alias) {
      return;
    }

    $extensions = self::getAllowedExtensions($entity);

    // If validate_exts count zero, means the alias
    // did not match any extension.
    $validate_exts = array_filter(
      array_map(function ($ext) use ($alias) {
        // Match is true, no match is false.
        return substr($alias, -strlen(".$ext")) === ".$ext";
      }, $extensions)
    );

    if (count($validate_exts) < 1) {
      $form_state->setErrorByName(
        MediaDownloadPathItem::FIELD_NAME,
        t('Only the alias with the following extensions are allowed: %files-allowed.', ['%files-allowed' => implode(' ', $extensions)])
      );
    }
  }

}
