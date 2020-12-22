<?php

namespace Drupal\weather\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\weather\OpenWeatherMapInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Route controller for the weather module.
 */
class WeatherController extends ControllerBase {

  /**
   *  The weather module config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   *  The Open Weather Map service.
   *
   * @var \Drupal\weather\OpenWeatherMapInterface
   */
  protected $openWeatherMap;

  /**
   * WeatherController constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\weather\OpenWeatherMapInterface $open_weather_map
   *   The open weather map.
   */
  public function __construct(ConfigFactoryInterface $config_factory, OpenWeatherMapInterface $open_weather_map) {
    $this->config = $config_factory->get('weather.settings');
    $this->openWeatherMap = $open_weather_map;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('openweathermap')
    );
  }

  /**
   * Checks access to the /weather page.
   */
  public function weatherDefaultPageAccess() {
    if (empty($this->config->get('api')) || empty($this->config->get('location'))) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowedIf($this->currentUser()->hasPermission('access weather page'));
  }

  /**
   * Checks access to the /weather/CITY page.
   */
  public function weatherByCityPageAccess() {
    if (empty($this->config->get('api'))) {
      return AccessResult::forbidden();
    }

    return AccessResult::allowedIf($this->currentUser()->hasPermission('access weather page'));
  }

  /**
   * The page callback to render weather for the default location.
   */
  public function weatherDefaultPage() {
    $location = $this->config->get('location');
    $weather = $this->openWeatherMap->getWeather($location);

    if ($weather['cod'] == 200) {
      $weather['units'] = $this->config->get('other.units');
      $weather['date_format'] = $this->config->get('other.date_format');
      // Cache result only for the /weather page.
      $weather['cache']['tags'] = $this->config->getCacheTags();
      $build = $this->buildRenderArray($weather);
    }
    else {
      $this->messenger()->addError(ucfirst($weather['message']));
      throw new NotFoundHttpException($weather['message']);
    }

    return $build;
  }

  /**
   * The page callback to render weather for the given city.
   *
   * @param $city
   *   The city name.
   *
   * @return array
   *   The render array.
   */
  public function weatherByCityPage(string $city) {
    $weather = $this->openWeatherMap->getWeather([
      'city' => $city,
    ]);

    if ($weather['cod'] == 200) {
      $weather['units'] = $this->config->get('other.units');
      $weather['date_format'] = $this->config->get('other.date_format');
      $build = $this->buildRenderArray($weather);
    }
    else {
      $this->messenger()->addError(ucfirst($weather['message']));
      throw new NotFoundHttpException($weather['message']);
    }

    return $build;
  }

  /**
   * Builds the render array based on the given data.
   *
   * @param array $data
   *   The weather data.
   *
   * @return array
   *   The render array.
   */
  private function buildRenderArray(array $data): array {
    $build = [
      '#theme' => 'weather',
      '#base' => $data['base'],
      '#clouds' => $data['clouds'],
      '#coord' => $data['coord'],
      '#date' => $data['dt'],
      '#date_format' => $data['date_format'],
      '#temperature' => $data['main'],
      '#city' => $data['name'],
      '#sys' => $data['sys'],
      '#timezone' => $data['timezone'],
      '#units' => $data['units'],
      '#visibility' => $data['visibility'],
      '#wind' => $data['wind'],
      '#weather' => reset($data['weather']),
    ];

    if (isset($data['cache']) && isset($data['cache']['tags'])) {
      $build['#cache']['tags'] = $data['cache']['tags'];
    }

    return $build;
  }

}
