<?php

namespace Drupal\media_alias_display\Controller;

use Drupal\Core\Entity\Controller\EntityViewController;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactory;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\file\Entity\File;
use Drupal\path_alias\AliasManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Drupal\Core\Url;
use Drupal\media\Entity\Media;

/**
 * Defines a controller to render a file with Media Alias being used.
 */
class DisplayController extends EntityViewController {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected $aliasManager;

  /**
   * The controller constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The renderer service.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Logger\LoggerChannelFactory $loggerFactory
   *   The logger factory.
   * @param \Symfony\Component\HttpFoundation\Request $request_stack
   *   Request stack.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path
   *   The current path.
   * @param \Drupal\path_alias\AliasManagerInterface $alias_manager
   *   The path alias manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RendererInterface $renderer, AccountInterface $current_user, LoggerChannelFactory $loggerFactory, Request $request_stack, CurrentPathStack $current_path, AliasManagerInterface $alias_manager) {
    parent::__construct($entity_type_manager, $renderer);
    $this->currentUser = $current_user;
    $this->loggerFactory = $loggerFactory;
    $this->request = $request_stack;
    $this->currentPath = $current_path;
    $this->aliasManager = $alias_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): DisplayController {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('current_user'),
      $container->get('logger.factory'),
      $container->get('request_stack')->getCurrentRequest(),
      $container->get('path.current'),
      $container->get('path_alias.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function view(EntityInterface $media, $view_mode = 'full', $langcode = NULL) {
    $current_path = $this->currentPath->getPath();
    $current_alias = $this->request->getRequestUri();

    $alias = $this->aliasManager->getPathByAlias($current_path);
    $params = Url::fromUri("internal:" . $alias)->getRouteParameters();
    $entity_type = key($params);
    $mid = $params[$entity_type];
    $media = Media::load($mid);

    if ($media == NULL) {
      $this->loggerFactory->get('media_alias_display')
        ->notice("Can't find media object for @path", [
          '@path' => $current_path,
        ]);
      throw new NotFoundHttpException("Can't find media object.");
    }

    $bundle = $media->bundle();
    $edit_own = 'edit own ' . $bundle . ' media';
    $edit_any = 'edit any ' . $bundle . ' media';

    // Skip redirect and go straight to media object.
    if (strpos($current_alias, "edit-media") !== FALSE && ($this->currentUser->hasPermission($edit_own) || $this->currentUser->hasPermission($edit_any))) {
      return new RedirectResponse('/media/' . $mid . '/edit');
    }

    $source = $media->getSource();
    $config = $source->getConfiguration();
    $field = $config['source_field'];

    $fid = $media->{$field}->target_id;

    // If media has no file item.
    if (!$fid) {
      $this->loggerFactory->get('media_alias_display')
        ->notice("The media item requested has no file referenced/uploaded for @path", [
          '@path' => $current_path,
        ]);
      return parent::view($media, $view_mode);
    }

    $file = File::load($fid);

    // Or file entity could not be loaded.
    if (!$file) {
      $this->loggerFactory->get('media_alias_display')
        ->notice("File id could not be loaded for " . $current_path);
      return parent::view($media, $view_mode);
    }

    $uri = $file->getFileUri();
    $filename = $file->getFilename();

    // Or item does not exist on disk.
    if (!file_exists($uri)) {
      $this->loggerFactory->get('media_alias_display')
        ->notice("File does not exist for @path", [
          '@path' => $current_path,
        ]);
      return parent::view($media, $view_mode);
    }

    $response = new BinaryFileResponse($uri);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_INLINE,
      $filename
    );

    return $response;
  }

}
