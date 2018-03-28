<?php

namespace Drupal\cleantaxonomy\Form;
use Drupal\Core\Url;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity;
/**
 * Implements an CleanTaxonomyReplaceForm form.
 */
class CleanTaxonomyReplaceForm extends FormBase {
  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'cleantaxonomy_replace';
  }
  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, $tid = NULL) {
    $currentUrl = Url::fromRoute('<current>');
    $path = $currentUrl->getInternalPath();
    $argument = explode('/', $path);
    $id = $argument[2];
    $taxonomy_term_object = \Drupal\taxonomy\Entity\Term::load($id);

    $taxonomy_term_name = $taxonomy_term_object->get('name')->value;
    $taxonomy_term_vid = $taxonomy_term_object->getVocabularyId();drupal_set_message($this->t('It will replace the nodes attached to the previous taxonomy term.'), 'warning');
    $form['cleantaxonomy']['Term1'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Taxonomy Term'),
      '#default_value' => $taxonomy_term_name,
     // 'type' => 'entity_reference',
      'attributes' => array('readonly' => 'readonly'),
      '#settings' => ['target_type' => 'taxonomy_term'],
      '#size' => 60,
      '#maxlength' => 128,
    ];
    $form['cleantaxonomy']['Term2'] = [
      '#type' => 'entity_autocomplete',
      '#title' => $this->t('New Taxonomy Term'),
      '#target_type' => 'taxonomy_term',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        'target_bundles' => array($taxonomy_term_vid)],
    ];
    
    $form['taxonomy_term_vid'] = [
      '#type' => 'hidden',
      '#value' => $taxonomy_term_vid,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Replace Term'),
    ];

    return $form;
  }
  /**
   * {@inheritdoc}.
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }
  /**
   * {@inheritdoc}.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $taxonomy_term_vid = $form_state->getValue('taxonomy_term_vid');
    $name1 = $form_state->getValue('Term1');
    $term_from = \Drupal::database()->select('taxonomy_term_field_data','x')
    ->fields('x', ['tid']);
    $term_from->condition('x.name',$name1);
    $data=$term_from->execute();
    $term_from= $data->fetchAll(\PDO::FETCH_ASSOC);
    $term_from= $term_from[0]['tid'];
    $term_to = $form_state->getValue('Term2'); 
    // retrieving nids having taxonomy term 'from'.
    $duplicate = \Drupal::database()->select('taxonomy_index','x')
    ->fields('x', ['nid']);
    $duplicate->condition('x.tid',$term_from);
    $data=$duplicate->execute();
    $nodes_with_term_from= $data->fetchAll(\PDO::FETCH_ASSOC);
    // retrieving nids having taxonomy term 'to'.    
    $duplicate = \Drupal::database()->select('taxonomy_index','x')
    ->fields('x', ['nid']);
    $duplicate->condition('x.tid',$term_to);
    $data=$duplicate->execute();
    $nodes_with_term_to = $data->fetchAll(\PDO::FETCH_ASSOC);
    // finding all nids which already have term 'from'.
    $fieldname="";
    $tablename="";
    foreach ($nodes_with_term_from as $nids => $value) {
      $node = \Drupal\node\Entity\Node::load($value['nid']);
      foreach ($node as $key => $values) {
        if(substr($key,0,5)=='field'){
          foreach ($node->get($key)->getValue() as $field => $target_id) {
            if($target_id['target_id'] == $term_from){
              $tablename="node__".$key;
              $fieldname=$key."_target_id";
            }
          }
          foreach ($node->get($key)->getValue() as $field => $target_id) {
            if($target_id['target_id'] == $term_to){
              $tablename="node__".$key;
              $fieldname=$key."_target_id";
              $query = \Drupal::database()->delete('taxonomy_index');
              $query->condition('tid', $term_from);
              $query->condition('nid', $value['nid']);
              $query->execute();
              $query = \Drupal::database()->delete($tablename);
              $query->condition($fieldname, $term_from);
              $query->condition('entity_id', $value['nid']);
              $query->execute();
            }
          }
        }
      }
    }
    $query = \Drupal::database()->update('taxonomy_index')
      ->fields([
        'tid' => $term_to,
        ])
      ->condition('tid', $term_from, '=')
      ->execute(); 
    if(!empty($tablename))
      $query = \Drupal::database()->update($tablename)
        ->fields([
          $fieldname => $term_to,
         ])
        ->condition($fieldname, $term_from,'=')
        ->execute();   
    drupal_set_message($this->t('Replaced Successfully'));   
 }
}
