<?php

namespace Drupal\cleantaxonomy\Controller;
use Drupal\Core\Url;
use Drupal\Core\Database\Connection;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Provides route responses for the cleantaxonomy module.
 */
class TaxonomyList extends ControllerBase {
  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;
  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database')
    );
  }
  /**
   * Constructs a cleantaxonomy object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   A database connection.
   */
  public function __construct(Connection $database) {
    $this->database = $database;
  }
  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function TaxonomyTermsList() {
    $header = [
      $this->t('Term ID'),
      $this->t('Taxonomy Term'),
      $this->t('Vocabulary'),
      [
        'data' => $this->t('No. of nodes attached'),
        'sort' => 'desc',
      ],
      [
        'data' => $this->t('Operation'),
        'colspan' => '4',
      ],
    ];
    $id_name = \Drupal::database()->select('taxonomy_term_field_data')
      ->fields('taxonomy_term_field_data', ['tid', 'name', 'vid'])
      ->execute();
    $id_name_records = $id_name->fetchAll();
    $id_name_values = [];
    $size = 0;
    foreach ($id_name_records as $id_name_record) {
      $id_name_values[$id_name_record->tid] = $id_name_record;
      $size++;
    }
    $node_count = \Drupal::database()->select('taxonomy_index','x')
      ->fields('x', ['tid']);
    $node_count->addExpression('COUNT(x.nid)', 'n');
    $node_count->groupBy('x.tid');
    $data=$node_count->execute();
    $node_count_records= $data->fetchAll(\PDO::FETCH_ASSOC);
    foreach ($node_count_records as $node_count_record) {
      $tags[$node_count_record['tid']]['count']=$node_count_record['n'];
    }  
    foreach ($id_name_values as $id_name_value) {
      $tags[$id_name_value->tid]['name']=$id_name_value->name;
      $tags[$id_name_value->tid]['tid']=$id_name_value->tid;
      $tags[$id_name_value->tid]['vid']=$id_name_value->vid;
      if(!$tags[$id_name_value->tid]['count'])
      {
        $tags[$id_name_value->tid]['count']='0';
      }
    }
    $rows = [];
    foreach ($tags as $key => $value) {  
          $rows[] = [
          'data' => [
            $this->t($value['tid']),
            $this->t($value['name']),
            $this->t($value['vid']),
            $this->t($value['count']),
            \Drupal::l($this->t('View'), Url::fromUri('internal:/taxonomy/term/' . $value['tid'], [$value['tid']])),
            \Drupal::l($this->t('Edit'), Url::fromUri('internal:/taxonomy/term/' . $value['tid'] . '/edit')),
            \Drupal::l($this->t('Delete'), Url::fromUri('internal:/taxonomy/term/' . $value['tid'] . '/delete')),
            \Drupal::l($this->t('Replace'), new Url('cleantaxonomy.admin_cleantaxonomy.tid.replace', ['tid' => $value['tid']])),
          ],
        ];
    }
    $build['admin_cleantaxonomy_list_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#empty' => $this->t('No taxonomy terms available.'),
    ];
    $build['admin_cleantaxonomy_list_pager'] = ['#theme' => 'pager'];
    return $build;
  }
}
