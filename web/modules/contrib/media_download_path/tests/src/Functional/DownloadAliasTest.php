<?php

namespace Drupal\Tests\media_download_path\Functional;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\field\Entity\FieldConfig;
use Drupal\file\Plugin\Field\FieldType\FileItem;
use Drupal\media\Entity\Media;
use Drupal\media_download_path\MediaDownloadPathMediaHelperTrait;
use Drupal\media_download_path\Plugin\Field\FieldType\MediaDownloadPathItem;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\media\Traits\MediaTypeCreationTrait;

/**
 * Simple test to ensure that main page loads with module enabled.
 *
 * @group media_download_alias
 */
class DownloadAliasTest extends BrowserTestBase {

  use MediaTypeCreationTrait;
  use StringTranslationTrait;

  /**
   * The media type.
   *
   * @var \Drupal\media\MediaTypeInterface
   */
  protected $mediaType;

  /**
   * The alias storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $aliasStorage;

  /**
   * The file extensions.
   *
   * @var string
   */
  protected $fileExtensions = 'txt pdf doc docx xls xlsx zip';

  /**
   * Modules to enable.
   * @var string[]
   */
  public static $modules = [
    'system', 'node', 'media', 'file', 'path', 'media_entity_download', 'media_download_path',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create test user and log in.
    $web_user = $this->drupalCreateUser(['create media', 'update any media', 'create url aliases', 'download media']);
    $this->drupalLogin($web_user);

    // Create media type.
    $this->mediaType = $this->createMediaType('file', [
      'queue_thumbnail_downloads' => FALSE,
    ]);
    $field_config = FieldConfig::loadByName('media', $this->mediaType->id(), 'field_media_file');
    $field_config->setRequired(FALSE);
    $field_config->setSetting('file_extensions', $this->fileExtensions);
    $field_config->save();

    // The alias storage.
    $this->aliasStorage = \Drupal::entityTypeManager()->getStorage('path_alias');
  }

  /**
  * Tests the media form UI.
  */
  public function testMediaForm() {
    $assert_session = $this->assertSession();

    $media_type_id = $this->mediaType->id();

    $this->drupalGet('media/add/' . $media_type_id);

    // Make sure we have a vertical tab fieldset and 'Download Path' field.
    $assert_session->elementContains('css', '.form-type-vertical-tabs #edit-media-download-path-0 summary', 'Download URL alias');
    $assert_session->fieldExists('media_download_path[0][alias]');

    // Disable the 'Download Path' field for this media type.
    \Drupal::service('entity_display.repository')
      ->getFormDisplay('media', $media_type_id, 'default')
      ->removeComponent('media_download_path')
      ->save();

    $this->drupalGet('media/add/' . $media_type_id);

    // See if the whole fieldset is gone now.
    $assert_session->elementNotExists('css', '.form-type-vertical-tabs #edit-media-download-path-0');
    $assert_session->fieldNotExists('media_download_path[0][alias]');
  }

  /**
   * Tests download alias extensions.
   */
  public function testAliasExtension() {
    $media_type_id = $this->mediaType->id();

    $src_field_definition = $this->mediaType->getSource()->getSourceFieldDefinition($this->mediaType);
    $src_field_name = $src_field_definition->getName();
    $src_field_value = FileItem::generateSampleValue($src_field_definition);

    $media_name = 'test media';
    $media = Media::create([
      'name' => $media_name,
      'bundle' => $media_type_id,
      $src_field_name => $src_field_value['target_id'],
    ]);
    $media->save();

    $extensions = MediaDownloadPathMediaHelperTrait::getAllowedExtensions($media);
    $extensions = implode(' ', $extensions);
    $this->assertEquals('txt', $extensions, 'Media Extensions correct');

    // Alias1.
    $alias1 = '/' . $this->randomMachineName();
    $this->drupalPostForm('media/' . $media->id() . '/edit', [
      'media_download_path[0][alias]' => $alias1,
    ], $this->t('Save'));
    $this->assertSession()->pageTextContains("Only the alias with the following extensions are allowed: {$extensions}.");
    $this->assertSession()->pageTextContains('Only the alias with the following extensions are allowed: txt.');

    // Alias2.
    $alias2 = $alias1 . '.png';
    $this->drupalPostForm('media/' . $media->id() . '/edit', [
      'media_download_path[0][alias]' => $alias2,
    ], $this->t('Save'));
    $this->assertSession()->pageTextContains("Only the alias with the following extensions are allowed: {$extensions}.");
    $this->assertSession()->pageTextContains('Only the alias with the following extensions are allowed: txt.');

    // Alias3.
    $alias3 = $alias1 . '.txt';
    $this->drupalPostForm('media/' . $media->id() . '/edit', [
      'media_download_path[0][alias]' => $alias3,
    ], $this->t('Save'));
    $this->assertSession()->pageTextNotContains("Only the alias with the following extensions are allowed: {$extensions}.");
    $this->assertSession()->pageTextNotContains('Only the alias with the following extensions are allowed: txt.');

    // Confirm that the alias created.
    $aliases = $this->aliasStorage->loadByProperties([
      'path' => '/' . MediaDownloadPathItem::getMediaDownloadPath($media),
      'langcode' => $media->language()->getId(),
    ]);
    $this->assertTrue(1 === count($aliases));
    $this->drupalGet(trim($alias3, '/'));
    $this->assertResponse(200);
  }

  /**
   * Tests download alias extensions without file.
   */
  public function testAliasExtensionWithoutFile() {
    $title = $this->randomMachineName();

    // @scenario 1:
    $this->drupalGet('media/add/' . $this->mediaType->id());

    $extensions = MediaDownloadPathMediaHelperTrait::getAllowedExtensionsByMediaType($this->mediaType->id());
    $extensions_imploder = implode(' ', $extensions);
    $this->assertEquals($this->fileExtensions, $extensions_imploder, 'Media Extensions correct');

    $alias1 = '/' . $this->randomMachineName();
    $this->drupalPostForm(NULL, [
      'name[0][value]' => $title,
      'media_download_path[0][alias]' => $alias1,
    ], $this->t('Save'));
    $this->assertSession()->pageTextContains("Only the alias with the following extensions are allowed: {$extensions_imploder}.");

    // @scenario 2:
    $extensions = MediaDownloadPathMediaHelperTrait::getAllowedExtensionsByMediaType($this->mediaType->id());
    $extensions_imploder = implode(' ', $extensions);
    $this->assertEquals($this->fileExtensions, $extensions_imploder, 'Media Extensions correct');

    $alias2 = $alias1 . '.pdf';
    $this->drupalPostForm(NULL, [
      'name[0][value]' => $title,
      'media_download_path[0][alias]' => $alias2,
    ], $this->t('Save'));
    $this->assertSession()->pageTextNotContains("Only the alias with the following extensions are allowed: {$extensions_imploder}.");

    $media_ids = $this->container->get('entity_type.manager')->getStorage('media')->getQuery()->execute();
    $this->assertTrue(count($media_ids) > 0);
    $media = Media::load(reset($media_ids));

    $extensions = MediaDownloadPathMediaHelperTrait::getAllowedExtensions($media);
    $extensions_imploder = implode(' ', $extensions);
    $this->assertEquals($this->fileExtensions, $extensions_imploder, 'Media Extensions correct');

    // @scenario 3:
    $src_field_definition = $this->mediaType->getSource()->getSourceFieldDefinition($this->mediaType);
    $src_field_name = $src_field_definition->getName();
    $src_field_value = FileItem::generateSampleValue($src_field_definition);

    $media->$src_field_name->setValue($src_field_value);
    $media->save();

    $this->drupalGet('media/' . $media->id() . '/edit');

    $extensions = MediaDownloadPathMediaHelperTrait::getAllowedExtensions($media);
    $extensions_imploder = implode(' ', $extensions);
    $this->assertEquals('txt', $extensions_imploder, 'Media Extensions correct');

    $alias3 = $alias1 . '.pdf';
    $this->drupalPostForm(NULL, [
      'name[0][value]' => $title,
      'media_download_path[0][alias]' => $alias3,
    ], $this->t('Save'));
    $this->assertSession()->pageTextContains("Only the alias with the following extensions are allowed: {$extensions_imploder}.");

    $alias4 = $alias1 . '.txt';
    $this->drupalPostForm(NULL, [
      'name[0][value]' => $title,
      'media_download_path[0][alias]' => $alias4,
    ], $this->t('Save'));
    $this->assertSession()->pageTextNotContains("Only the alias with the following extensions are allowed: {$extensions_imploder}.");

    // Confirm that the alias created.
    $aliases = $this->aliasStorage->loadByProperties([
      'path' => '/' . MediaDownloadPathItem::getMediaDownloadPath($media),
      'langcode' => $media->language()->getId(),
    ]);
    $this->assertTrue(1 === count($aliases));
    $alias_obj = array_shift($aliases);
    $this->assertEquals($alias4, $alias_obj->getAlias(), 'Media download path correct');
    $this->drupalGet(trim($alias4, '/'));
    $this->assertResponse(200);
  }

  /**
   * Tests if download alias get saved via media edit form.
   */
  public function testDownloadAlias() {
    $src_field_definition = $this->mediaType->getSource()->getSourceFieldDefinition($this->mediaType);
    $src_field_name = $src_field_definition->getName();
    $src_field_value = FileItem::generateSampleValue($src_field_definition);

    $media_name = 'test media';
    $media = Media::create([
        'name' => $media_name,
        'bundle' => $this->mediaType->id(),
        $src_field_name => $src_field_value['target_id'],
      ]);
    $media->save();

    // @scenario 1: Alias creation.
    $test_alias = '/' . $this->randomMachineName() . '.txt';
    $this->drupalPostForm('media/' . $media->id() . '/edit', [
      'media_download_path[0][alias]' => $test_alias,
    ], $this->t('Save'));

    // Confirm that the alias created.
    $aliases = $this->aliasStorage->loadByProperties([
      'path' => '/' . MediaDownloadPathItem::getMediaDownloadPath($media),
      'langcode' => $media->language()->getId(),
    ]);
    $this->assertTrue(1 === count($aliases));

    /** @var \Drupal\path_alias\Entity\PathAlias $alias */
    $alias = array_shift($aliases);
    $this->assertEquals('/' . MediaDownloadPathItem::getMediaDownloadPath($media), $alias->getPath(), 'Download alias saved.');
    $this->assertEquals($test_alias, $alias->getAlias(), 'Download alias saved.');

    // Confirm that the alias works.
    $this->drupalGet(trim($test_alias, '/'));
    $this->assertResponse(200);

    // @scenario 2: Alias update.
    $test_alias_2 = '/' . $this->randomMachineName() . '.txt';
    $this->drupalPostForm('media/' . $media->id() . '/edit', [
      'media_download_path[0][alias]' => $test_alias_2
    ], $this->t('Save'));

    // Confirm that the alias updated.
    $this->aliasStorage->resetCache();

    $aliases = $this->aliasStorage->loadByProperties([
      'path' => '/' . MediaDownloadPathItem::getMediaDownloadPath($media),
      'langcode' => $media->language()->getId(),
    ]);
    $this->assertTrue(1 === count($aliases));

    $alias = $this->aliasStorage->load($alias->id());
    $this->assertEquals('/' . MediaDownloadPathItem::getMediaDownloadPath($media), $alias->getPath(), 'Download alias updated.');
    $this->assertEquals($test_alias_2, $alias->getAlias(), 'Download alias updated.');

    // Confirm that the alias works.
    $this->drupalGet(trim($test_alias, '/'));
    $this->assertResponse(404);
    $this->drupalGet(trim($test_alias_2, '/'));
    $this->assertResponse(200);

    // @scenario 3: Alias deletion.
    $test_alias_3 = '';
    $this->drupalPostForm('media/' . $media->id() . '/edit', [
      'media_download_path[0][alias]' => $test_alias_3,
    ], $this->t('Save'));

    // Confirm that the alias deleted.
    $this->aliasStorage->resetCache();

    $aliases = $this->aliasStorage->loadByProperties([
      'path' => '/' . MediaDownloadPathItem::getMediaDownloadPath($media),
      'langcode' => $media->language()->getId(),
    ]);
    $this->assertTrue(0 === count($aliases));

    // Confirm that the alias works.
    $this->drupalGet(trim($test_alias, '/'));
    $this->assertResponse(404);
    $this->drupalGet(trim($test_alias_2, '/'));
    $this->assertResponse(404);
    $this->drupalGet(trim(MediaDownloadPathItem::getMediaDownloadPath($media)));
    $this->assertResponse(200);
  }

  /**
   * Tests that duplicate aliases from PathItem auto create.
   *
   * E.g.
   * - alias: /common.jpg
   *   - path: /media/1/download (from MediaDownloadPathItem::postSave() creation)
   *   - path: /media/1 (from PathItem::postSave() creation)
   */
  public function testDuplicateAliases() {
    // @scenario 1: Alias creation.
    $test_alias = '/' . $this->randomMachineName() . '.txt';

    $this->drupalPostForm('media/add/' . $this->mediaType->id(), [
      'name[0][value]' => $this->randomString(),
      'media_download_path[0][alias]' => $test_alias,
    ], $this->t('Save'));

    $media_ids = $this->container->get('entity_type.manager')->getStorage('media')->getQuery()->execute();
    $this->assertTrue(count($media_ids) > 0);

    $media = Media::load(reset($media_ids));

    $aliases = $this->aliasStorage->loadByProperties(['alias' => $test_alias]);
    $this->assertTrue(1 === count($aliases));
    $alias = array_shift($aliases);
    $this->assertEquals('/' . MediaDownloadPathItem::getMediaDownloadPath($media), $alias->getPath(), 'Download alias saved.');
    $this->assertEquals($test_alias, $alias->getAlias(), 'Download alias saved.');

    // @scenario 2: Alias update.
    $this->aliasStorage->resetCache();

    $test_alias_2 = '/' . $this->randomMachineName() . '.txt';

    $this->drupalPostForm('media/' . $media->id() . '/edit', [
      'media_download_path[0][alias]' => $test_alias_2
    ], $this->t('Save'));

    $aliases = $this->aliasStorage->loadByProperties(['alias' => $test_alias_2]);
    $this->assertTrue(1 === count($aliases));
    $alias_2 = array_shift($aliases);
    $this->assertEquals('/' . MediaDownloadPathItem::getMediaDownloadPath($media), $alias_2->getPath(), 'Download alias saved.');
    $this->assertEquals($test_alias_2, $alias_2->getAlias(), 'Download alias saved.');

    $this->assertEquals($alias->id(), $alias_2->id());

    // @scenario 3: Alias deletion.
    $this->aliasStorage->resetCache();

    $test_alias_3 = '';

    $this->drupalPostForm('media/' . $media->id() . '/edit', [
      'media_download_path[0][alias]' => $test_alias_3
    ], $this->t('Save'));

    $aliases = $this->aliasStorage->loadByProperties(['alias' => $test_alias_3]);
    $this->assertTrue(0 === count($aliases));
    $aliases = $this->aliasStorage->loadMultiple();
    $this->assertTrue(0 === count($aliases));
  }

}
