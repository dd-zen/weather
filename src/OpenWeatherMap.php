<?php

namespace Drupal\weather;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;

/**
 * The OpenWeatherMap service.
 */
class OpenWeatherMap implements OpenWeatherMapInterface {

  /**
   * A config object for the weather configuration.
   *
   * @var \Drupal\Core\Config\Config
   */
  protected $config;

  /**
   * The Guzzle HTTP Client service.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * The API key.
   *
   * @var string
   */
  private $key;

  /**
   * The API endpoint.
   *
   * @var string
   */
  private $endpoint;

  /**
   * OpenWeatherMapApi constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   */
  public function __construct(ConfigFactoryInterface $config_factory, ClientInterface $http_client) {
    $this->config = $config_factory->get('weather.settings');
    $this->httpClient = $http_client;
    $this->key = $this->config->get('api.key');
    $this->endpoint = $this->config->get('api.endpoint');
  }

  /**
   * {@inheritdoc}
   */
  public function getWeather(array $location_data, $credentials = []): ?array {
    // Credentials array is coming only in case when we need to validate the
    // configuration form, but on that stage we don't have stored
    // API credentials in the config.
    if (!empty($credentials)) {
      $this->key = $credentials['key'];
      $this->endpoint = $credentials['endpoint'];
    }

    // Set default units.
    if (!isset($location_data['units']) && $this->config->get('other.units')) {
      $location_data['units'] = $this->config->get('other.units');
    }

    $country_code = $location_data['country_code'] ?? '';
    $query = UrlHelper::buildQuery([
      'q' => $location_data['city'] . ',' . $country_code,
      'appid' => $this->key,
      'units' => $location_data['units'],
    ]);

    try {
      $request = $this->httpClient->request('GET', $this->endpoint . '?' . $query);
      $response = $request->getBody()->getContents();
      return \GuzzleHttp\json_decode($response, TRUE);
    }
    catch (RequestException $e) {
      $response = $e->getResponse()->getBody()->getContents();
      return \GuzzleHttp\json_decode($response, TRUE);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validate(string $key, string $endpoint): array {
    // Set dummy location data to make sure that API credentials are correct.
    $query = UrlHelper::buildQuery([
      'q' => 'London',
      'appid' => $key,
    ]);

    try {
      $request = $this->httpClient->request('GET', $endpoint . '?' . $query);
      return [
        'cod' => $request->getStatusCode(),
      ];
    }
    catch (RequestException $e) {
      $response = $e->getResponse()->getBody()->getContents();
      return \GuzzleHttp\json_decode($response, TRUE);
    }
  }

}
