<?php

namespace Drupal\media_download_path;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;
use Drupal\media\MediaInterface;

/**
 * Class MediaDownloadPathTwigExtension.
 *
 * @package Drupal\media_download_path
 */
class MediaDownloadPathTwigExtension extends \Twig_Extension {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * MediaDownloadPathTwigExtension constructor.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(LanguageManagerInterface $language_manager) {
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'media_download_path';
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    $filters = parent::getFilters();
    $filters[] = new \Twig_SimpleFilter('media_download_path', [$this, 'getMediaDownloadPath']);
    return $filters;
  }

  /**
   * Get media download path.
   *
   * @param \Drupal\Core\Entity\EntityInterface|string|int|null $entity
   *   The entity.
   * @param string|null $langcode
   *   The langcode.
   *
   * @return string|null
   *   The media download path.
   */
  public function getMediaDownloadPath($entity = NULL, $langcode = NULL) {
    if (!($entity instanceof MediaInterface)) {
      $entity = Media::load((int) $entity);
    }

    if (!($entity instanceof MediaInterface)) {
      return NULL;
    }

    if (isset($langcode)) {
      $langcode = (string) $langcode;
      $entity = $entity->hasTranslation($langcode) ? $entity->getTranslation($langcode) : $entity;
    }

    $url = Url::fromRoute('media_entity_download.download', [
      'media' => $entity->id(),
    ], [
      'language' => $entity->language(),
    ]);

    return $url->toString();
  }

}
