<?php

namespace Drupal\media_download_path;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_browser\EntityBrowserFormInterface;
use Drupal\file\Entity\File as EntityFile;
use Drupal\media\MediaInterface;
use Drupal\media_library\Form\FileUploadForm;
use Drupal\media\Plugin\media\Source\File as SourceFile;

/**
 * Trait MediaDownloadPathFormHelperTrait.
 *
 * @package Drupal\media_download_path
 */
trait MediaDownloadPathMediaHelperTrait {

  /**
   * Get entity from form state.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *   The entity.
   */
  public static function getEntityFromFormState(FormStateInterface $form_state) {
    // Form object.
    $form_object = $form_state->getFormObject();

    // Prepare entity.
    $entity = NULL;
    // Entity form.
    if ($form_object instanceof EntityFormInterface) {
      $entity = $form_object->getEntity();
    }
    // Entity browser form.
    elseif ($form_object instanceof EntityBrowserFormInterface) {
      $entity_form = $form_state->getCompleteForm()['widget']['entity'] ?? [];
      if (isset($entity_form['#entity'])) {
        $entity = $entity_form['#entity'];
      }
      elseif (isset($entity_form['#default_value'])) {
        $entity = $entity_form['#default_value'];
      }
    }
    // File upload form.
    elseif ($form_object instanceof FileUploadForm) {
      $entities = $form_state->get('media') ?: [];
      $entity = array_shift($entities) ?: NULL;
    }

    return $entity;
  }

  /**
   * Get allowed extensions

   * @param MediaInterface $media
   *   The media.
   *
   * @return array
   *   The extensions.
   */
  public static function getAllowedExtensions(MediaInterface $media) {
    return array_filter([self::getAllowedExtensionByMediaEntity($media)]) ?: self::getAllowedExtensionsByMediaType($media->bundle());
  }

  /**
   * Get allowed extension by media entity.
   *
   * @param \Drupal\media\MediaInterface $media
   *   The media entity.
   *
   * @return string|null
   *   The extension.
   */
  public static function getAllowedExtensionByMediaEntity(MediaInterface $media) {
    $media_source = $media->getSource();
    $filename = $media_source->getMetadata($media, SourceFile::METADATA_ATTRIBUTE_NAME);
    if (!$filename) {
      $file_id = $media->get($media_source->getConfiguration()['source_field'])->getValue()[0]['fids'][0] ?? NULL;
      if ($file_id && ($file = EntityFile::load($file_id))) {
        $filename = $file->getFilename();
      }
    }
    if ($filename && strstr($filename, '.') !== FALSE) {
      $explode = explode('.', $filename);
      return array_pop($explode);
    }
    return NULL;
  }

  /**
   * Get allowed extensions by media type.
   *
   * @param string $media_type
   *   The media type.
   *
   * @return array
   *   The extensions.
   */
  public static function getAllowedExtensionsByMediaType(string $media_type) {
    $extensions = &drupal_static(__FUNCTION__, []);

    if (!isset($extensions[$media_type])) {
      $extensions[$media_type] = [];

      try {
        $media_type_obj = \Drupal::entityTypeManager()->getStorage('media_type')->load($media_type);
        if ($media_type_obj) {
          $file_extensions = $media_type_obj->getSource()->getSourceFieldDefinition($media_type_obj)->getSetting('file_extensions');
          $file_extensions = preg_split('/,?\s+/', rtrim($file_extensions));
          $extensions[$media_type] = array_unique($file_extensions);
        }
      }
      catch (InvalidPluginDefinitionException $e) {
      }
      catch (PluginNotFoundException $e) {
      }
    }

    return $extensions[$media_type];
  }

}
