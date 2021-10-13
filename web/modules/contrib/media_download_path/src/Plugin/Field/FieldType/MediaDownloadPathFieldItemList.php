<?php

namespace Drupal\media_download_path\Plugin\Field\FieldType;

use Drupal\path\Plugin\Field\FieldType\PathFieldItemList;

/**
 * Represents a configurable media entity download_path field.
 */
class MediaDownloadPathFieldItemList extends PathFieldItemList {

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    // Default the langcode to the current language if this is a new entity or
    // there is no alias for an existent entity.
    // @todo Set the langcode to not specified for untranslatable fields
    //   in https://www.drupal.org/node/2689459.
    $value = ['langcode' => $this->getLangcode()];

    $entity = $this->getEntity();
    if (!$entity->isNew()) {
      /** @var \Drupal\path_alias\AliasRepositoryInterface $path_alias_repository */
      $path_alias_repository = \Drupal::service('path_alias.repository');

      if ($path_alias = $path_alias_repository->lookupBySystemPath('/' . MediaDownloadPathItem::getMediaDownloadPath($entity), $this->getLangcode())) {
        $value = [
          'alias' => $path_alias['alias'],
          'pid' => $path_alias['id'],
          'langcode' => $path_alias['langcode'],
        ];
      }
    }

    $this->list[0] = $this->createItem(0, $value);
  }

  /**
   * {@inheritdoc}
   */
  public function delete() {
    // Delete all aliases associated with this entity in the current language.
    $entity = $this->getEntity();
    $path_alias_storage = \Drupal::entityTypeManager()->getStorage('path_alias');
    $entitites = $path_alias_storage->loadByProperties([
      'path' => '/' . MediaDownloadPathItem::getMediaDownloadPath($entity),
      'langcode' => $entity->language()->getId(),
    ]);
    $path_alias_storage->delete($entitites);
  }

}
