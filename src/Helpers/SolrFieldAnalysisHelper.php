<?php

namespace Drupal\search_api_solr_field_analysis\Helpers;

use Solarium\Client as SolariumClient;
use Solarium\Core\Client\Adapter\Psr18Adapter;
use Drupal\search_api\ServerInterface;
use Http\Factory\Guzzle\RequestFactory;
use Http\Factory\Guzzle\StreamFactory;
use GuzzleHttp\Client;
use Solarium\Core\Query\Result\ResultInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class SolrFieldAnalysisHelper {

  /**
   * Search api server object.
   *
   * @var \Drupal\search_api\ServerInterface|NULL
   */
  protected $searchAapiServer = NULL;

  /**
   * Constructor for SolrFieldAnalysisHelper.
   *
   * @param RequestFactory $requestFactory
   *   Request factory.
   * @param StreamFactory $streamFactory
   *   Stream factory.
   * @param Client $httpClient
   *   Guzzle http client.
   * @param EventDispatcherInterface $eventDispatcher
   *   Event dispatcher.
   */
  public function __construct(
    protected RequestFactory $requestFactory,
    protected StreamFactory $streamFactory,
    protected Client $httpClient,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
    $this->httpClient = $httpClient;
    $this->eventDispatcher = $eventDispatcher;
    $this->requestFactory = $requestFactory;
    $this->streamFactory = $streamFactory;
  }

  /**
   * Get search api server object.
   *
   * @return \Drupal\search_api\ServerInterface|NULL
   */
  public function getSearchApiServer() {
    return $this->searchAapiServer;
  }

  /**
   * Set search api server object.
   *
   * @param \Drupal\search_api\ServerInterface
   */
  public function setSearchApiServer(ServerInterface $search_api_server) {
    $this->searchAapiServer = $search_api_server;
    return $this;
  }

  /**
   * Get solariumn client object.
   *
   * @param ServerInterface $search_api_server
   * @return \Solarium\Client
   */
  public function getSolariumClient(ServerInterface $search_api_server) {
    $backend = $search_api_server->getBackend();
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
   * Anaylse the field with index and query string values.
   *
   * @param string $field
   *   Field that to be analysed.
   * @param string|null $index_value
   *   Index string to anayse.
   * @param string|null $query_value
   *   Query string to analyse.
   *
   * @return \Solarium\Core\Query\Result\ResultInterface;
   *   Solarium query result.
   */
  public function getFieldAnalysis(string $field, string $index_value, ?string $query_value) {
    $search_api_server = $this->getSearchApiServer();
    $solr_client = $this->getSolariumClient($search_api_server);
    $field_analysis = $solr_client->createAnalysisField();
    $field_analysis->setFieldType($field);
    $field_analysis->setFieldValue($index_value);

    if ($query_value) {
      $field_analysis->setQuery($query_value);
    }

    $field_analysis->setShowMatch(TRUE);
    return $solr_client->analyze($field_analysis);
  }

  /**
   * Prepare the query data result set for rendering.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface
   *   Analyse query result.
   *
   * @return array
   *   Array for rendering
   */
  public function getQueryDataFromResult(ResultInterface $results) {
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

  /**
   * Prepare the Index data result set for rendering.
   *
   * @param \Solarium\Core\Query\Result\ResultInterface
   *   Analyse query result.
   *
   * @return array
   *   Array for rendering
   */
  public function getIndexDataFromResult(ResultInterface $results) {
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

}
