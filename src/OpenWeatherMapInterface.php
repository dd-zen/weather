<?php

namespace Drupal\weather;

/**
 * Interface OpenWeatherMapInterface.
 *
 * @package Drupal\weather
 */
interface OpenWeatherMapInterface {

  /**
   * Get weather for the given location.
   *
   * @param array $location_data
   *   The location information.
   * @param array $credentials
   *   The API credentials.
   *
   * @return array|null
   *   The weather for the given location.
   */
  public function getWeather(array $location_data, array $credentials = []): ?array;

  /**
   * Validates the configuration.
   *
   * @param string $key
   *   The Open Weather Map API key.
   * @param string $endpoint
   *   The Open Weather Map API endpoint.
   *
   * @return array
   *   The validation result.
   */
  public function validate(string $key, string $endpoint): array;

}
