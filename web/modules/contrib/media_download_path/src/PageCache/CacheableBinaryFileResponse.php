<?php

namespace Drupal\media_download_path\PageCache;

use Drupal\Core\Cache\CacheableResponseInterface;
use Drupal\Core\Cache\CacheableResponseTrait;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Class CacheableBinaryFileResponse.
 *
 * @package Drupal\media_download_path
 */
class CacheableBinaryFileResponse extends BinaryFileResponse implements CacheableResponseInterface {
  use CacheableResponseTrait;
}
