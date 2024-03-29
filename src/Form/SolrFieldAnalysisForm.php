<?php

namespace Drupal\search_api_solr_field_analysis\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\ServerInterface;
use Drupal\search_api_solr_field_analysis\Helpers\SolrFieldAnalysisHelper;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Form for solr field analysis.
 */
class SolrFieldAnalysisForm extends FormBase {

  /**
   * Search api server.
   *
   * @var \Drupal\search_api\ServerInterface
   */
  protected $server;

  /**
   * Solr field analysis helper.
   *
   * @var \Drupal\search_api_solr_field_analysis\Helpers\SolrFieldAnalysisHelper
   */
  protected $solrFieldAnalysisHelper;

  /**
   * Entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructor for SolrFieldAnalysisForm.
   */
  public function __construct(
    SolrFieldAnalysisHelper $solr_field_analysis_helper,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->solrFieldAnalysisHelper = $solr_field_analysis_helper;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('solr_field_analysis.field_analysis_helper'),
      $container->get('entity_type.manager'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'solr_field_analysis_form';
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Nothing for submission. This is only because this method is abstract.
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ServerInterface $search_api_server = NULL) {
    $this->server = $search_api_server;
    $form['analysis_result'] = [
      '#markup' => '<div id="analysis-result"></div>',
    ];

    $form['index_query_details'] = [
      '#type' => 'details',
      '#title' => $this->t('Field values'),
      '#open' => TRUE,
    ];

    $form['index_query_details']['index_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field index value'),
    ];

    $form['index_query_details']['query_value'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Field query value'),
    ];

    // Get solr field lists.
    $list_builder = $this->entityTypeManager->getListBuilder('solr_field_type');
    $list_builder->setServer($search_api_server);
    $solr_field_types = $list_builder->load();

    $solr_fields = [];
    foreach ($solr_field_types as $solr_field_type) {
      $solr_fields[$solr_field_type->getFieldTypeName()] = $solr_field_type->label();
    }

    $form['analysis_field'] = [
      '#type' => 'select',
      '#options' => $solr_fields,
      '#required' => TRUE,
      '#title' => $this->t('Select Field'),
    ];

    $form['#attached']['library'][] = 'search_api_solr_field_analysis/solr_field_analysis';

    $form['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Show Analyse'),
      '#ajax' => [
        'callback' => '::ajaxFieldAnalysis',
      ],
    ];

    return $form;
  }

  /**
   * Ajax callback for field analysis submission.
   *
   * @param array $form
   *   Form object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   Ajax Response.
   */
  public function ajaxFieldAnalysis(array $form, FormStateInterface $form_state): AjaxResponse {
    $field = $form_state->getValue('analysis_field');
    $index_value = $form_state->getValue('index_value');
    $query_value = $form_state->getValue('query_value');

    $build = [];

    if ($field && $index_value) {
      $this->solrFieldAnalysisHelper->setSearchApiServer($this->server);
      $results = $this->solrFieldAnalysisHelper->getFieldAnalysis($field, $index_value, $query_value);

      // Prepare index analysis rendering data.
      $index_processed_data = $this->solrFieldAnalysisHelper->getIndexDataFromResult($results, 'index');
      $build[] = [
        '#theme' => 'index_analysis',
        '#title' => $this->t('Index Analysis'),
        '#data' => $index_processed_data,
      ];

      if ($query_value) {
        // Prepare query analysis rendering data.
        $query_processed_data = $this->solrFieldAnalysisHelper->getIndexDataFromResult($results, 'query');
        $build[] = [
          '#theme' => 'index_analysis',
          '#title' => $this->t('Query Analysis'),
          '#data' => $query_processed_data,
        ];
      }
    }

    $ajax_response = new AjaxResponse();
    $ajax_response->addCommand(new HtmlCommand('#analysis-result', $build));

    return $ajax_response;
  }

}
