<?php

namespace Drupal\weather\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\weather\OpenWeatherMapInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure weather module settings.
 */
class WeatherSettingsForm extends ConfigFormBase {

  /**
   *  The Open Weather Map service.
   *
   * @var \Drupal\weather\OpenWeatherMapInterface
   */
  protected $openWeatherMap;

  /**
   * WeatherSettingsForm constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\weather\OpenWeatherMapInterface $open_weather_map
   *   The open weather map.
   */
  public function __construct(ConfigFactoryInterface $config_factory, OpenWeatherMapInterface $open_weather_map) {
    parent::__construct($config_factory);
    $this->openWeatherMap = $open_weather_map;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('openweathermap'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'weather.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'weather_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->get('weather.settings');

    $form['api'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('API settings'),
      '#tree' => TRUE,
    ];

    $form['api']['key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Key'),
      '#description' => $this->t('Please enter the API key.'),
      '#required' => TRUE,
      '#default_value' => $config->get('api.key') ?: '',
    ];

    $form['api']['endpoint'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Endpoint'),
      '#description' => $this->t('Please enter the API endpoint.'),
      '#required' => TRUE,
      '#default_value' => $config->get('api.endpoint') ?: '',
    ];

    $form['location'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Location settings'),
      '#tree' => TRUE,
    ];

    $form['location']['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#description' => $this->t('Please enter the city name.'),
      '#required' => TRUE,
      '#default_value' => $config->get('location.city') ?: '',
    ];

    $form['location']['country_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Country code'),
      '#description' => $this->t('Please enter the country code (use <a href="@wiki" target="_blank">ISO 3166</a> for country codes).', [
        '@wiki' => 'https://en.wikipedia.org/wiki/List_of_ISO_3166_country_codes',
      ]),
      '#required' => TRUE,
      '#default_value' => $config->get('location.country_code') ?: '',
    ];

    $form['other'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Other settings'),
      '#tree' => TRUE,
    ];

    $form['other']['date_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Date format'),
      '#options' => [
        'D, F j, H:i' => date('D, F j, H:i'),
        'Y-m-d H:i' => date('Y-m-d H:i'),
        'F j, Y, g:i a' => date('F j, Y, g:i a'),
      ],
      '#default_value' => $config->get('other.date_format') ?: 'D, F j, H:i',
      '#required' => TRUE,
      '#description' => $this->t('Please select the date format.')
    ];

    $form['other']['units'] = [
      '#type' => 'select',
      '#title' => $this->t('Units'),
      '#options' => [
        'metric' => $this->t('Metric'),
        'standard' => $this->t('Standard'),
        'imperial' => $this->t('Imperial'),
      ],
      '#default_value' => $config->get('other.units') ?: 'metric',
      '#required' => TRUE,
      '#description' => $this->t('Temperature is available in Fahrenheit, Celsius and Kelvin units. <ul>
<li>For temperature in Fahrenheit use units=imperial</li>
<li>For temperature in Celsius use units=metric</li>
<li>Temperature in Kelvin is used by default, no need to use units parameter in API call</li></ul>')
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $key = $form_state->getValue(['api', 'key']);
    $endpoint = $form_state->getValue(['api', 'endpoint']);
    $credentials = [
      'key' => $key,
      'endpoint' => $endpoint,
    ];

    $credentials_validate = $this->openWeatherMap->validate($key, $endpoint);
    // Validate the Open Weather Map API credentials.
    if ($credentials_validate['cod'] !== 200) {
      $form_state->setErrorByName('api', $credentials_validate['message']);
    }

    // Validate the configured location.
    $location_data = [
      'city' => $form_state->getValue(['location', 'city']),
      'country_code' => $form_state->getValue(['location', 'country_code']),
      'units' => $form_state->getValue(['other', 'units']),
    ];
    $location_validate = $this->openWeatherMap->getWeather($location_data, $credentials);

    // If code is not equal 200 show the message.
    if ($location_validate['cod'] !== 200) {
      $form_state->setErrorByName('location', ucfirst($location_validate['message']));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('weather.settings')
      ->set('api.key', $form_state->getValue(['api', 'key']))
      ->set('api.endpoint', $form_state->getValue(['api', 'endpoint']))
      ->set('location.city', $form_state->getValue(['location', 'city']))
      ->set('location.country_code', $form_state->getValue(['location', 'country_code']))
      ->set('other.units', $form_state->getValue(['other', 'units']))
      ->set('other.date_format', $form_state->getValue(['other', 'date_format']))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
