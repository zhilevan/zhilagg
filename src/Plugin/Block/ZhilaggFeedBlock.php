<?php

namespace Drupal\zhilagg\Plugin\Block;

use Drupal\zhilagg\FeedStorageInterface;
use Drupal\zhilagg\ItemStorageInterface;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\Query\QueryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides an 'Zhilagg feed' block with the latest items from the feed.
 *
 * @Block(
 *   id = "zhilagg_feed_block",
 *   admin_label = @Translation("Zhilagg feed"),
 *   category = @Translation("Lists (Views)")
 * )
 */
class ZhilaggFeedBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The entity storage for feeds.
   *
   * @var \Drupal\zhilagg\FeedStorageInterface
   */
  protected $feedStorage;

  /**
   * The entity storage for items.
   *
   * @var \Drupal\zhilagg\ItemStorageInterface
   */
  protected $itemStorage;

  /**
   * The entity query object for feed items.
   *
   * @var \Drupal\Core\Entity\Query\QueryInterface
   */
  protected $itemQuery;

  /**
   * Constructs an ZhilaggFeedBlock object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\zhilagg\FeedStorageInterface $feed_storage
   *   The entity storage for feeds.
   * @param \Drupal\zhilagg\ItemStorageInterface $item_storage
   *   The entity storage for feed items.
   * @param \Drupal\Core\Entity\Query\QueryInterface $item_query
   *   The entity query object for feed items.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FeedStorageInterface $feed_storage, ItemStorageInterface $item_storage, QueryInterface $item_query) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->feedStorage = $feed_storage;
    $this->itemStorage = $item_storage;
    $this->itemQuery = $item_query;
  }


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity.manager')->getStorage('zhilagg_feed'),
      $container->get('entity.manager')->getStorage('zhilagg_item'),
      $container->get('entity.query')->get('zhilagg_item')
    );
  }


  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    // By default, the block will contain 10 feed items.
    return array(
      'block_count' => 10,
      'feed' => NULL,
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    // Only grant access to users with the 'access news feeds' permission.
    return AccessResult::allowedIfHasPermission($account, 'access news feeds');
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $feeds = $this->feedStorage->loadMultiple();
    $options = array();
    foreach ($feeds as $feed) {
      $options[$feed->id()] = $feed->label();
    }
    $form['feed'] = array(
      '#type' => 'select',
      '#title' => $this->t('Select the feed that should be displayed'),
      '#default_value' => $this->configuration['feed'],
      '#options' => $options,
    );
    $range = range(2, 20);
    $form['block_count'] = array(
      '#type' => 'select',
      '#title' => $this->t('Number of news items in block'),
      '#default_value' => $this->configuration['block_count'],
      '#options' => array_combine($range, $range),
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['block_count'] = $form_state->getValue('block_count');
    $this->configuration['feed'] = $form_state->getValue('feed');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    // Load the selected feed.
    if ($feed = $this->feedStorage->load($this->configuration['feed'])) {
      $result = $this->itemQuery
        ->condition('fid', $feed->id())
        ->range(0, $this->configuration['block_count'])
        ->sort('timestamp', 'DESC')
        ->sort('iid', 'DESC')
        ->execute();

      if ($result) {
        // Only display the block if there are items to show.
        $items = $this->itemStorage->loadMultiple($result);

        $build['list'] = [
          '#theme' => 'item_list',
          '#items' => [],
        ];
        foreach ($items as $item) {
          $build['list']['#items'][$item->id()] = [
            '#type' => 'link',
            '#url' => $item->urlInfo(),
            '#title' => $item->label(),
          ];
        }
        $build['more_link'] = [
          '#type' => 'more_link',
          '#url' => $feed->urlInfo(),
          '#attributes' => ['title' => $this->t("View this feed's recent news.")],
        ];
        return $build;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    $cache_tags = parent::getCacheTags();
    $feed = $this->feedStorage->load($this->configuration['feed']);
    return Cache::mergeTags($cache_tags, $feed->getCacheTags());
  }

}
