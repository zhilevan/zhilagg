<?php

namespace Drupal\zhilagg\Tests;

use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Tests zhilagg feeds in multiple languages.
 *
 * @group zhilagg
 */
class FeedLanguageTest extends ZhilaggTestBase {

  /**
   * Modules to install.
   *
   * @var array
   */
  public static $modules = array('language');

  /**
   * List of langcodes.
   *
   * @var string[]
   */
  protected $langcodes = array();

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Create test languages.
    $this->langcodes = array(ConfigurableLanguage::load('en'));
    for ($i = 1; $i < 3; ++$i) {
      $language = ConfigurableLanguage::create(array(
        'id' => 'l' . $i,
        'label' => $this->randomString(),
      ));
      $language->save();
      $this->langcodes[$i] = $language->id();
    }
  }

  /**
   * Tests creation of feeds with a language.
   */
  public function testFeedLanguage() {
    $admin_user = $this->drupalCreateUser(['administer languages', 'access administration pages', 'administer news feeds', 'access news feeds', 'create article content']);
    $this->drupalLogin($admin_user);

    // Enable language selection for feeds.
    $edit['entity_types[zhilagg_feed]'] = TRUE;
    $edit['settings[zhilagg_feed][zhilagg_feed][settings][language][language_alterable]'] = TRUE;

    $this->drupalPostForm('admin/config/regional/content-language', $edit, t('Save configuration'));

    /** @var \Drupal\zhilagg\FeedInterface[] $feeds */
    $feeds = array();
    // Create feeds.
    $feeds[1] = $this->createFeed(NULL, array('langcode[0][value]' => $this->langcodes[1]));
    $feeds[2] = $this->createFeed(NULL, array('langcode[0][value]' => $this->langcodes[2]));

    // Make sure that the language has been assigned.
    $this->assertEqual($feeds[1]->language()->getId(), $this->langcodes[1]);
    $this->assertEqual($feeds[2]->language()->getId(), $this->langcodes[2]);

    // Create example nodes to create feed items from and then update the feeds.
    $this->createSampleNodes();
    $this->cronRun();

    // Loop over the created feed items and verify that their language matches
    // the one from the feed.
    foreach ($feeds as $feed) {
      /** @var \Drupal\zhilagg\ItemInterface[] $items */
      $items = entity_load_multiple_by_properties('zhilagg_item', array('fid' => $feed->id()));
      $this->assertTrue(count($items) > 0, 'Feed items were created.');
      foreach ($items as $item) {
        $this->assertEqual($item->language()->getId(), $feed->language()->getId());
      }
    }
  }

}
