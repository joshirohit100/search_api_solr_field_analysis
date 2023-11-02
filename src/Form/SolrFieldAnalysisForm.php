<?php

namespace Drupal\search_api_solr_field_analysis\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\search_api\ServerInterface;
use GuzzleHttp\Client;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use Solarium\Client as SolariumClient;
use Solarium\Core\Client\Adapter\Psr18Adapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Form for solr field analysis.
 */
class SolrFieldAnalysisForm extends FormBase {

  protected $server;

  protected $httpClient;

  protected $eventDispatcher;

  protected $requestFactory;
  protected $streamFactory;

  /**
   *
   */
  public function __construct(
    Client $http_client,
    EventDispatcherInterface $event_dispatcher,
    RequestFactory $request_factory,
    StreamFactory $stream_factory,
  ) {
    $this->httpClient = $http_client;
    $this->eventDispatcher = $event_dispatcher;
    $this->requestFactory = $request_factory;
    $this->streamFactory = $stream_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client'),
      $container->get('event_dispatcher'),
      $container->get('solr_field_analysis.request_factory'),
      $container->get('solr_field_analysis.stream_factory')
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

    $form['analysis_field'] = [
      '#type' => 'select',
      '#options' => [
        '' => $this->t('Select field'),
        'text_en' => $this->t('Text en'),
      ],
      '#required' => TRUE,
      '#title' => $this->t('Select Field'),
    ];

    $form['#attached']['library'][] = 'search_api_solr_field_analysis/solr_field_analysis';

    $form['submit'] = [
      '#type' => 'button',
      '#value' => $this->t('Show Analyse'),
      '#ajax' => [
        'callback' => '::analyseField',
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
   *   Ajax response.
   */
  public function analyseField(array $form, FormStateInterface $form_state): AjaxResponse {
    $field = $form_state->getValue('analysis_field');
    $index_value = $form_state->getValue('index_value');
    $query_value = $form_state->getValue('query_value');
    $results = $this->getFieldAnalysis($field, $index_value, $query_value);
    $ajax_response = new AjaxResponse();
    $formatted_data = $this->prepareIndexData($results);
    $formatted_data_query = $this->prepareQueryData($results);

    $build[] = [
      '#theme' => 'index_analysis',
      '#title' => $this->t('Index Analysis'),
      '#data' => $formatted_data,
    ];

    $build[] = [
      '#theme' => 'index_analysis',
      '#title' => $this->t('Query Analysis'),
      '#data' => $formatted_data_query,
    ];
    $ajax_response->addCommand(new HtmlCommand('#analysis-result', $build));
    return $ajax_response;
  }

  /**
   *
   */
  protected function prepareIndexData($results) {
    $data = [];
    foreach ($results as $result) {
      foreach ($result as $item) {
        $indexAnalysis = $item->getIndexAnalysis();
        if (!empty($indexAnalysis)) {
          foreach ($indexAnalysis as $classes) {
            $class_name = $classes->getName();
            $exploded_name = explode('.', $class_name);
            $class_name = end($exploded_name);
            $data[$class_name] = [];
            foreach ($classes as $class) {
              $data[$class_name][] = [
                'text' => $class->getText(),
                'raw_text' => $class->getRawText(),
                'matches' => $class->getMatch(),
              ];
            }
          }
        }
      }
    }

    return $data;
  }

  /**
   *
   */
  protected function prepareQueryData($results) {
    $data = [];
    foreach ($results as $result) {
      foreach ($result as $item) {
        $queryAnalysis = $item->getQueryAnalysis();
        if (!empty($queryAnalysis)) {
          foreach ($queryAnalysis as $classes) {
            $class_name = $classes->getName();
            $exploded_name = explode('.', $class_name);
            $class_name = end($exploded_name);
            $data[$class_name] = [];
            foreach ($classes as $class) {
              $data[$class_name][] = [
                'text' => $class->getText(),
                'raw_text' => $class->getRawText(),
                'matches' => $class->getMatch(),
              ];
            }
          }
        }
      }
    }

    return $data;
  }

  protected function getClient() {
    /** @var \Drupal\search_api_solr\Plugin\search_api\backend\SearchApiSolrBackend $backend */
    $backend = $this->server->getBackend();
    $connector = $backend->getSolrConnector();
    $endpoint = $connector->getEndpoint();

    return new SolariumClient(
      new Psr18Adapter(
        $this->httpClient,
        $this->requestFactory,
        $this->streamFactory,
      ),
      $this->eventDispatcher,
      [
        'endpoint' => [
          'search_api_solr' => $endpoint,
        ],
      ],
    );
  }

  /**
   *
   */
  protected function getFieldAnalysis(string $field, ?string $index_value, ?string $query_value,) {
    $solr_client = $this->getClient();
    $field_analysis = $solr_client->createAnalysisField();
    $field_analysis->setFieldType($field);
    $field_analysis->setFieldValue($index_value);
    $field_analysis->setQuery($query_value);
    $field_analysis->setShowMatch(TRUE);
    return $solr_client->analyze($field_analysis);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

}
