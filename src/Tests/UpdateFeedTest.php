<?php

namespace Drupal\zhilagg\Tests;

/**
 * Update feed test.
 *
 * @group zhilagg
 */
class UpdateFeedTest extends ZhilaggTestBase {
  /**
   * Creates a feed and attempts to update it.
   */
  public function testUpdateFeed() {
    $remaining_fields = array('title[0][value]', 'url[0][value]', '');
    foreach ($remaining_fields as $same_field) {
      $feed = $this->createFeed();

      // Get new feed data array and modify newly created feed.
      $edit = $this->getFeedEditArray();
      // Change refresh value.
      $edit['refresh'] = 1800;
      if (isset($feed->{$same_field}->value)) {
        $edit[$same_field] = $feed->{$same_field}->value;
      }
      $this->drupalPostForm('zhilagg/sources/' . $feed->id() . '/configure', $edit, t('Save'));
      $this->assertText(t('The feed @name has been updated.', array('@name' => $edit['title[0][value]'])), format_string('The feed %name has been updated.', array('%name' => $edit['title[0][value]'])));

      // Verify that the creation message contains a link to a feed.
      $view_link = $this->xpath('//div[@class="messages"]//a[contains(@href, :href)]', array(':href' => 'zhilagg/sources/'));
      $this->assert(isset($view_link), 'The message area contains a link to a feed');

      // Check feed data.
      $this->assertUrl($feed->url('canonical', ['absolute' => TRUE]));
      $this->assertTrue($this->uniqueFeed($edit['title[0][value]'], $edit['url[0][value]']), 'The feed is unique.');

      // Check feed source.
      $this->drupalGet('zhilagg/sources/' . $feed->id());
      $this->assertResponse(200, 'Feed source exists.');
      $this->assertText($edit['title[0][value]'], 'Page title');

      // Set correct title so deleteFeed() will work.
      $feed->title = $edit['title[0][value]'];

      // Delete feed.
      $this->deleteFeed($feed);
    }
  }

}
