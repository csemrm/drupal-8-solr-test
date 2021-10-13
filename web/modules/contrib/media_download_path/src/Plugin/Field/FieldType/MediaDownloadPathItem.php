<?php

namespace Drupal\media_download_path\Plugin\Field\FieldType;

use Drupal\Component\Utility\Random;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\path\Plugin\Field\FieldType\PathItem;

/**
 * Defines the 'media_download_path' entity field type.
 *
 * @FieldType(
 *   id = "media_download_path",
 *   label = @Translation("Download path"),
 *   description = @Translation("An entity field containing a path alias and related data for downloading media entities."),
 *   no_ui = TRUE,
 *   default_widget = "media_download_path",
 *   list_class = "\Drupal\media_download_path\Plugin\Field\FieldType\MediaDownloadPathFieldItemList",
 *   constraints = {"PathAlias" = {}},
 * )
 */
class MediaDownloadPathItem extends PathItem {

  /**
   * Field name.
   */
  const FIELD_NAME = 'media_download_path';

  /**
   * Get media download path.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The media entity.
   *
   * @return string
   *   The media download path.
   */
  public static function getMediaDownloadPath(EntityInterface $entity) {
    return 'media/' . $entity->id() . '/download';
  }

  /**
   * {@inheritdoc}
   */
  public function postSave($update) {
    $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');

    // If specified, rely on the langcode property for the language, so that the
    // existing language of an alias can be kept. That could for example be
    // unspecified even if the field/entity has a specific langcode.
    $alias_langcode = ($this->langcode && $this->pid) ? $this->langcode : $this->getLangcode();

    // If we have an alias, we need to create or update a path alias entity.
    if ($this->alias) {
      // If no update (is create), or not have pid, should be create.
      if (!$update || !$this->pid) {
        $path_alias = $path_alias_storage->create([
          'path' => '/' . self::getMediaDownloadPath($this->getEntity()),
          'alias' => $this->alias,
          'langcode' => $alias_langcode
        ]);
        $path_alias->save();
        $this->pid = $path_alias->id();
        $update = TRUE;
      }
    }

    // Handle parent post save.
    parent::postSave($update);
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    $random = new Random();
    $values['alias'] = '/download/' . str_replace(' ', '-', strtolower($random->sentences(3)));
    return $values;
  }

}
