<?php

namespace Drupal\zhilagg;

use Drupal\Core\Entity\ContentEntityForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Form handler for the zhilagg feed edit forms.
 */
class FeedForm extends ContentEntityForm {

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    $feed = $this->entity;
    $status = $feed->save();
    $label = $feed->label();
    $view_link = $feed->link($label, 'canonical');
    if ($status == SAVED_UPDATED) {
      drupal_set_message($this->t('The feed %feed has been updated.', array('%feed' => $view_link)));
      $form_state->setRedirectUrl($feed->urlInfo('canonical'));
    }
    else {
      $this->logger('zhilagg')->notice('Feed %feed added.', array('%feed' => $feed->label(), 'link' => $this->l($this->t('View'), new Url('zhilagg.admin_overview'))));
      drupal_set_message($this->t('The feed %feed has been added.', array('%feed' => $view_link)));
    }
  }

}
