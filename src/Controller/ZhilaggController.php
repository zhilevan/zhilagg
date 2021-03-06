<?php

namespace Drupal\zhilagg\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\zhilagg\FeedInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for zhilagg module routes.
 */
class ZhilaggController extends ControllerBase {

  /**
   * The date formatter service.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * Constructs a \Drupal\zhilagg\Controller\ZhilaggController object.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *    The date formatter service.
   */
  public function __construct(DateFormatterInterface $date_formatter) {
    $this->dateFormatter = $date_formatter;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('date.formatter')
    );
  }

  /**
   * Presents the zhilagg feed creation form.
   *
   * @return array
   *   A form array as expected by drupal_render().
   */
  public function feedAdd() {
    $feed = $this->entityManager()->getStorage('zhilagg_feed')
      ->create(array(
        'refresh' => 3600,
      ));
    return $this->entityFormBuilder()->getForm($feed);
  }

  /**
   * Builds a listing of zhilagg feed items.
   *
   * @param \Drupal\zhilagg\ItemInterface[] $items
   *   The items to be listed.
   * @param array|string $feed_source
   *   The feed source URL.
   *
   * @return array
   *   The rendered list of items for the feed.
   */
  protected function buildPageList(array $items, $feed_source = '') {
    // Assemble output.
    $build = array(
      '#type' => 'container',
      '#attributes' => array('class' => array('zhilagg-wrapper')),
    );
    $build['feed_source'] = is_array($feed_source) ? $feed_source : array('#markup' => $feed_source);
    if ($items) {
      $build['items'] = $this->entityManager()->getViewBuilder('zhilagg_item')
        ->viewMultiple($items, 'default');
      $build['pager'] = array('#type' => 'pager');
    }
    return $build;
  }

  /**
   * Refreshes a feed, then redirects to the overview page.
   *
   * @param \Drupal\zhilagg\FeedInterface $zhilagg_feed
   *   An object describing the feed to be refreshed.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirection to the admin overview page.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   If the query token is missing or invalid.
   */
  public function feedRefresh(FeedInterface $zhilagg_feed) {
    $message = $zhilagg_feed->refreshItems()
      ? $this->t('There is new syndicated content from %site.', array('%site' => $zhilagg_feed->label()))
      : $this->t('There is no new syndicated content from %site.', array('%site' => $zhilagg_feed->label()));
    drupal_set_message($message);
    return $this->redirect('zhilagg.admin_overview');
  }

  /**
   * Displays the zhilagg administration page.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function adminOverview() {
    $entity_manager = $this->entityManager();
    $feeds = $entity_manager->getStorage('zhilagg_feed')
      ->loadMultiple();

    $header = array($this->t('Title'), $this->t('Items'), $this->t('Last update'), $this->t('Next update'), $this->t('Operations'));
    $rows = array();
    /** @var \Drupal\zhilagg\FeedInterface[] $feeds */
    foreach ($feeds as $feed) {
      $row = array();
      $row[] = $feed->link();
      $row[] = $this->formatPlural($entity_manager->getStorage('zhilagg_item')->getItemCount($feed), '1 item', '@count items');
      $last_checked = $feed->getLastCheckedTime();
      $refresh_rate = $feed->getRefreshRate();

      $row[] = ($last_checked ? $this->t('@time ago', array('@time' => $this->dateFormatter->formatInterval(REQUEST_TIME - $last_checked))) : $this->t('never'));
      if (!$last_checked && $refresh_rate) {
        $next_update = $this->t('imminently');
      }
      elseif ($last_checked && $refresh_rate) {
        $next_update = $next = $this->t('%time left', array('%time' => $this->dateFormatter->formatInterval($last_checked + $refresh_rate - REQUEST_TIME)));
      }
      else {
        $next_update = $this->t('never');
      }
      $row[] = $next_update;
      $links['edit'] = [
        'title' => $this->t('Edit'),
        'url' => Url::fromRoute('entity.zhilagg_feed.edit_form', ['zhilagg_feed' => $feed->id()]),
      ];
      $links['delete'] = array(
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute('entity.zhilagg_feed.delete_form', ['zhilagg_feed' => $feed->id()]),
      );
      $links['delete_items'] = array(
        'title' => $this->t('Delete items'),
        'url' => Url::fromRoute('zhilagg.feed_items_delete', ['zhilagg_feed' => $feed->id()]),
      );
      $links['update'] = array(
        'title' => $this->t('Update items'),
        'url' => Url::fromRoute('zhilagg.feed_refresh', ['zhilagg_feed' => $feed->id()]),
      );
      $row[] = array(
        'data' => array(
          '#type' => 'operations',
          '#links' => $links,
        ),
      );
      $rows[] = $row;
    }
    $build['feeds'] = array(
      '#prefix' => '<h3>' . $this->t('Feed overview') . '</h3>',
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No feeds available. <a href=":link">Add feed</a>.', array(':link' => $this->url('zhilagg.feed_add'))),
    );

    return $build;
  }

  /**
   * Displays the most recent items gathered from any feed.
   *
   * @return string
   *   The rendered list of items for the feed.
   */
  public function pageLast() {
    $items = $this->entityManager()->getStorage('zhilagg_item')->loadAll(20);
    $build = $this->buildPageList($items);
    $build['#attached']['feed'][] = array('zhilagg/rss', $this->config('system.site')->get('name') . ' ' . $this->t('zhilagg'));
    return $build;
  }

  /**
   * Route title callback.
   *
   * @param \Drupal\zhilagg\FeedInterface $zhilagg_feed
   *   The zhilagg feed.
   *
   * @return array
   *   The feed label as a render array.
   */
  public function feedTitle(FeedInterface $zhilagg_feed) {
    return ['#markup' => $zhilagg_feed->label(), '#allowed_tags' => Xss::getHtmlTagList()];
  }

}
