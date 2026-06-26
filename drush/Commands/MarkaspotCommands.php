<?php

namespace Drush\Commands;

use Drush\Drush;
use Symfony\Component\Console\Output\ConsoleOutput;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Symfony\Component\Console\Input\ArrayInput;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Defines Drush commands for Mark-a-Spot.
 *
 * @see http://docs.drush.org/en/master/commands/
 */
class MarkaspotCommands extends DrushCommands implements CustomEventAwareInterface {

  use CustomEventAwareTrait;
  
  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Initializes the command just after the input has been validated.
   *
   * @throws \Exception
   */
  protected function initialize() {
    if (\Drupal::hasContainer()) {
      $this->configFactory = \Drupal::service('config.factory');
    }
  }

  /**
   * Installs Mark-a-Spot with customizations.
   *
   * @command markaspot:install
   * @aliases mi
   * @bootstrap none
   * @option lat Latitude for geolocation field.
   * @option lng Longitude for geolocation field.
   * @option city The city for geolocation field.
   * @option locale The locale for default country setting.
   * @option skip-confirmation Whether to skip confirmation or not.
   * @option radius The radius in kilometers for the geofence (default: 50).
   */
  public function install($options = [
    'lat' => '40.73',
    'lng' => '-73.93',
    'city' => 'New York',
    'locale' => 'en_US', 
    'skip-confirmation' => FALSE,
    'radius' => 50,
  ]) {
    // Validate coordinates
    $lat = floatval($options['lat']);
    $lng = floatval($options['lng']);
    
    if (!$this->validateCoordinates($lat, $lng)) {
      $this->logger()->error(dt('Invalid coordinates. Latitude must be between -90 and 90, longitude between -180 and 180.'));
      return 1;
    }
    
    $city = $options['city'];
    
    // Validate locale format
    if (!preg_match('/^[a-z]{2}_[A-Z]{2}$/', $options['locale'])) {
      $this->logger()->error(dt('Invalid locale format. Expected format: en_US'));
      return 1;
    }
    
    list($language, $country) = explode('_', $options['locale']);

    $account_name = 'admin';
    $account_pass = 'admin';
    $account_mail = 'admin@example.com';
    
    // FIXED: Use the shell() method which doesn't require a site alias
    // Build the command as a string
    $command = "drush site:install markaspot";
    $command .= " --account-name=$account_name";
    $command .= " --account-pass=$account_pass";
    $command .= " --account-mail=$account_mail";
    $command .= " --locale=" . $language;
    
    if ($options['skip-confirmation']) {
      $command .= " -y";
    }
    
    // Execute the site:install command using shell
    $this->logger()->notice(dt('Installing Mark-a-Spot...'));
    $process = $this->processManager()->shell($command);
    $process->setTimeout(0);
    $process->run();
    
    if (!$process->isSuccessful()) {
      $this->logger()->error(dt('Site installation failed with exit code @code', ['@code' => $process->getExitCode()]));
      $this->logger()->error($process->getErrorOutput());
      return $process->getExitCode();
    }
    
    $this->logger()->success(dt('Site installation completed successfully.'));
    $this->logger()->notice(dt($process->getOutput()));
    
    // Now that Drupal is installed, we can update the configuration
    // We'll do this directly since we can bootstrap Drupal now
    $this->logger()->notice(dt('Updating Mark-a-Spot configurations...'));
    
    // Run a separate command for configuration updates
    // FIXED: Changed command name to match the new method name
    $config_cmd = "drush markaspot:config-update";
    $config_cmd .= " --lat=$lat --lng=$lng --city='$city' --country=$country --radius={$options['radius']}";
    $config_cmd .= " -y";
    
    $process = $this->processManager()->shell($config_cmd);
    $process->setTimeout(0);
    $process->run();
    
    if (!$process->isSuccessful()) {
      $this->logger()->error(dt('Configuration update failed with exit code @code', ['@code' => $process->getExitCode()]));
      $this->logger()->error($process->getErrorOutput());
      return $process->getExitCode();
    }
    
    $this->logger()->success(dt('Mark-a-Spot installation and configuration completed successfully.'));
    return 0;
  }
  
  /**
   * Configures Mark-a-Spot after installation.
   *
   * @command markaspot:config-update
   * @bootstrap full
   * @option lat Latitude for geolocation field.
   * @option lng Longitude for geolocation field.
   * @option city The city for geolocation field.
   * @option country The country code for default setting.
   * @option radius The radius in kilometers for the geofence.
   */
  public function configUpdate($options = [
    'lat' => '40.73',
    'lng' => '-73.93',
    'city' => 'New York',
    'country' => 'US',
    'radius' => 50,
  ]) {
    // This command runs with full bootstrap, so we can access Drupal services
    try {
      // Verify that we have a container
      if (!\Drupal::hasContainer()) {
        $this->logger()->error(dt('Drupal container is not available.'));
        return 1;
      }
      
      // Check if required modules are enabled
      $moduleHandler = \Drupal::service('module_handler');
      if (!$moduleHandler->moduleExists('markaspot_nuxt') ||
          !$moduleHandler->moduleExists('markaspot_validation')) {
        $this->logger()->error(dt('Required modules (markaspot_nuxt and/or markaspot_validation) are not enabled.'));
        return 1;
      }
      
      $config_factory = \Drupal::service('config.factory');
      
      // Update geolocation field configuration
      $this->updateGeolocationConfig(
        $config_factory, 
        $options['lat'], 
        $options['lng'], 
        $options['city'], 
        $options['country'], 
        $options['radius']
      );
      
      return 0;
    }
    catch (\Exception $e) {
      $this->logger()->error(dt('Error updating configurations: @message', ['@message' => $e->getMessage()]));
      return 1;
    }
  }

  /**
   * Validates latitude and longitude values.
   *
   * @param float $lat
   *   The latitude to validate.
   * @param float $lng
   *   The longitude to validate.
   *
   * @return bool
   *   TRUE if the coordinates are valid, FALSE otherwise.
   */
  protected function validateCoordinates($lat, $lng) {
    return is_numeric($lat) && is_numeric($lng) &&
      $lat >= -90 && $lat <= 90 &&
      $lng >= -180 && $lng <= 180;
  }

  /**
   * Updates geolocation configuration.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param float $lat
   *   The latitude.
   * @param float $lng
   *   The longitude.
   * @param string $city
   *   The city name.
   * @param string $country
   *   The country code.
   * @param int $radius
   *   The radius in kilometers.
   */
  protected function updateGeolocationConfig($config_factory, $lat, $lng, $city, $country, $radius) {
    try {
      // Update geolocation field configuration
      $geolocation_config = $config_factory->getEditable('field.field.node.service_request.field_geolocation');
      
      if (!$geolocation_config) {
        $this->logger()->error(dt('Geolocation field configuration not found.'));
        return;
      }

      // Calculate the additional values
      $lat_sin = sin(deg2rad($lat));
      $lat_cos = cos(deg2rad($lat));
      $lng_rad = deg2rad($lng);
      $value = "{$lat}, {$lng}";

      // Set the new values
      $geolocation_config->set('default_value.0', [
        'lat' => $lat,
        'lng' => $lng,
        'lat_sin' => $lat_sin,
        'lat_cos' => $lat_cos,
        'lng_rad' => $lng_rad,
        'value' => $value,
      ]);

      $geolocation_config->save();
      $this->logger()->success(dt('Geolocation field configuration updated.'));

      // Calculate radius more accurately based on latitude
      $km_per_lat_degree = 111.132;
      $km_per_lng_degree = 111.132 * cos(deg2rad($lat));
      
      $lat_radius = $radius / $km_per_lat_degree;
      $lng_radius = $radius / $km_per_lng_degree;

      $min_lat = $lat - $lat_radius;
      $max_lat = $lat + $lat_radius;
      $min_lng = $lng - $lng_radius;
      $max_lng = $lng + $lng_radius;

      // Create polygon
      $coords = [
        [$min_lng, $min_lat],
        [$max_lng, $min_lat],
        [$max_lng, $max_lat],
        [$min_lng, $max_lat],
        [$min_lng, $min_lat]
      ];

      $wkt = 'POLYGON((';
      foreach ($coords as $coord) {
        $wkt .= $coord[0] . ' ' . $coord[1] . ',';
      }
      $wkt = rtrim($wkt, ',');
      $wkt .= '))';
      
      // Update the markaspot_validation.settings.yml file
      $validation_settings = $config_factory->getEditable('markaspot_validation.settings');
      if ($validation_settings) {
        $validation_settings
          ->set('wkt', $wkt)
          ->set('location', [$city])
          ->save();
        $this->logger()->success(dt('Validation settings updated.'));
      }
      
      // Update country settings
      $config_factory->getEditable('system.date')->set('country.default', $country)->save();
      $this->logger()->success(dt('Country settings updated.'));

      // Update form display configurations
      $field_limit_viewbox = "$min_lng,$max_lat,$max_lng,$min_lat";
      $form_display_configurations = [
        'core.entity_form_display.node.service_request.default',
        'core.entity_form_display.node.service_request.management',
      ];

      foreach ($form_display_configurations as $form_display_configuration) {
        $this->updateFormDisplayConfig($config_factory, $form_display_configuration, $lat, $lng, $field_limit_viewbox, $city, $country);
      }

      // Update map settings
      $this->markaspotSettingsConfig($config_factory, 'markaspot_nuxt.settings', $lat, $lng);
      $this->logger()->success(dt('Map settings updated.'));
    }
    catch (\Exception $e) {
      $this->logger()->error(dt('Error updating configurations: @message', ['@message' => $e->getMessage()]));
    }
  }

  /**
   * Updates form display configurations.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param string $config_name
   *   The configuration name.
   * @param float $lat
   *   The latitude.
   * @param float $lng
   *   The longitude.
   * @param string $limit_viewbox
   *   The viewbox limits.
   * @param string $city
   *   The city name.
   * @param string $country
   *   The country code.
   */
  protected function updateFormDisplayConfig($config_factory, $config_name, $lat, $lng, $limit_viewbox, $city, $country) {
    $form_display_config = $config_factory->getEditable($config_name);
    if ($form_display_config) {
      $form_display_config->set('content.field_geolocation.settings.limit_viewbox', $limit_viewbox)
        ->set('content.field_geolocation.settings.city', $city)
        ->set('content.field_geolocation.settings.limit_country_code', $country)
        ->set('content.field_geolocation.settings.center_lat', $lat)
        ->set('content.field_geolocation.settings.center_lng', $lng)
        ->save();
    }
    else {
      $this->logger()->warning(dt('Form display configuration @name not found.', ['@name' => $config_name]));
    }
  }

  /**
   * Fetches a city boundary from Nominatim and saves it to a Group entity.
   *
   * Queries the OpenStreetMap Nominatim API for the city boundary polygon
   * and stores it as a GeoJSON FeatureCollection in the Group entity's
   * field_boundary field. Falls back to a 10km circle if no polygon is found.
   *
   * @command markaspot:fetch-boundary
   * @aliases mfb
   * @bootstrap full
   * @option city The city name to search for on Nominatim.
   * @option group The Group entity ID to save the boundary to.
   *
   * @usage drush markaspot:fetch-boundary --city="Cologne"
   *   Fetches the boundary for Cologne and saves it to Group 1.
   * @usage drush markaspot:fetch-boundary --city="Bonn" --group=15
   *   Fetches the boundary for Bonn and saves it to Group 15.
   */
  public function fetchBoundary($options = [
    'city' => self::REQ,
    'group' => 1,
  ]) {
    $city = $options['city'];
    $groupId = (int) $options['group'];

    // Load the Group entity.
    $groupStorage = \Drupal::entityTypeManager()->getStorage('group');
    $group = $groupStorage->load($groupId);

    if (!$group) {
      $this->logger()->error(dt('Group entity @id not found.', ['@id' => $groupId]));
      return 1;
    }

    if ($group->getGroupType()->id() !== 'jur') {
      $this->logger()->error(dt('Group @id is type "@type", expected "jur".', [
        '@id' => $groupId,
        '@type' => $group->getGroupType()->id(),
      ]));
      return 1;
    }

    if (!$group->hasField('field_boundary')) {
      $this->logger()->error(dt('Group @id has no field_boundary field.', ['@id' => $groupId]));
      return 1;
    }

    // Query Nominatim (max 1 request/second per usage policy).
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
      'q' => $city,
      'format' => 'json',
      'polygon_geojson' => 1,
      'limit' => 1,
    ]);

    $this->logger()->notice(dt('Querying Nominatim for "@city"...', ['@city' => $city]));

    try {
      $response = \Drupal::httpClient()->get($url, [
        'headers' => [
          'User-Agent' => 'Mark-a-Spot/11.x (https://mark-a-spot.com)',
          'Accept' => 'application/json',
        ],
        'timeout' => 15,
      ]);

      $data = json_decode((string) $response->getBody(), TRUE);
    }
    catch (\Exception $e) {
      $this->logger()->error(dt('Nominatim request failed: @msg', ['@msg' => $e->getMessage()]));
      return 1;
    }

    if (empty($data) || !is_array($data)) {
      $this->logger()->error(dt('Nominatim returned no results for "@city".', ['@city' => $city]));
      return 1;
    }

    $result = $data[0];
    $geojson = $result['geojson'] ?? NULL;
    $boundaryType = $geojson['type'] ?? NULL;

    // Build the FeatureCollection, with circle fallback.
    if ($geojson && in_array($boundaryType, ['Polygon', 'MultiPolygon'], TRUE)) {
      $featureCollection = $this->buildBoundaryFeatureCollection($geojson, $city);
      $coordCount = $this->countCoordinates($geojson);
      $this->logger()->success(dt('Fetched @type boundary with @count coordinate pairs.', [
        '@type' => $boundaryType,
        '@count' => $coordCount,
      ]));
    }
    else {
      // Fallback: generate a 10km circle around the result center.
      $lat = (float) ($result['lat'] ?? 0);
      $lon = (float) ($result['lon'] ?? 0);

      if ($lat == 0 && $lon == 0) {
        $this->logger()->error(dt('No polygon and no coordinates returned for "@city".', ['@city' => $city]));
        return 1;
      }

      $this->logger()->warning(dt('No polygon found. Generating 10km circle fallback at @lat, @lon.', [
        '@lat' => $lat,
        '@lon' => $lon,
      ]));

      $circleGeometry = $this->generateCircleBoundary($lat, $lon, 10.0);
      $featureCollection = $this->buildBoundaryFeatureCollection($circleGeometry, $city);
    }

    // Save to group entity.
    $json = json_encode($featureCollection, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $group->set('field_boundary', $json);
    $group->save();

    $this->logger()->success(dt('Boundary saved to Group @id (@label).', [
      '@id' => $groupId,
      '@label' => $group->label(),
    ]));

    return 0;
  }

  /**
   * Wraps a GeoJSON geometry in a FeatureCollection with city name property.
   *
   * @param array $geometry
   *   A GeoJSON geometry array (Polygon or MultiPolygon).
   * @param string $name
   *   The city/feature name.
   *
   * @return array
   *   A GeoJSON FeatureCollection.
   */
  protected function buildBoundaryFeatureCollection(array $geometry, string $name): array {
    return [
      'type' => 'FeatureCollection',
      'features' => [
        [
          'type' => 'Feature',
          'properties' => ['name' => $name],
          'geometry' => $geometry,
        ],
      ],
    ];
  }

  /**
   * Generates a circular polygon boundary around a center point.
   *
   * @param float $lat
   *   Center latitude.
   * @param float $lng
   *   Center longitude.
   * @param float $radiusKm
   *   Radius in kilometers.
   * @param int $points
   *   Number of points on the circle.
   *
   * @return array
   *   A GeoJSON Polygon geometry.
   */
  protected function generateCircleBoundary(float $lat, float $lng, float $radiusKm, int $points = 32): array {
    $coords = [];
    $latOffset = $radiusKm / 111.0;
    $lngOffset = abs($lat) < 89.9 ? $radiusKm / (111.0 * cos(deg2rad($lat))) : $latOffset;

    for ($i = 0; $i < $points; $i++) {
      $angle = 2 * M_PI * $i / $points;
      $coords[] = [
        round($lng + $lngOffset * cos($angle), 6),
        round($lat + $latOffset * sin($angle), 6),
      ];
    }
    // Close the ring.
    $coords[] = $coords[0];

    return [
      'type' => 'Polygon',
      'coordinates' => [$coords],
    ];
  }

  /**
   * Counts coordinate pairs in a GeoJSON geometry.
   *
   * @param array $geojson
   *   A GeoJSON geometry array.
   *
   * @return int
   *   The number of coordinate pairs.
   */
  protected function countCoordinates(array $geojson): int {
    $type = $geojson['type'] ?? '';
    $coordinates = $geojson['coordinates'] ?? [];

    if ($type === 'Polygon') {
      return array_sum(array_map('count', $coordinates));
    }
    elseif ($type === 'MultiPolygon') {
      $count = 0;
      foreach ($coordinates as $polygon) {
        $count += array_sum(array_map('count', $polygon));
      }
      return $count;
    }

    return 0;
  }

  /**
   * Updates Mark-a-Spot map settings.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory service.
   * @param string $config_name
   *   The configuration name.
   * @param float $lat
   *   The latitude.
   * @param float $lng
   *   The longitude.
   */
  protected function markaspotSettingsConfig($config_factory, $config_name, $lat, $lng) {
    $markaspot_map = $config_factory->getEditable($config_name);
    if ($markaspot_map) {
      $markaspot_map
        ->set('center_lat', $lat)
        ->set('center_lng', $lng)
        ->save();
    }
    else {
      $this->logger()->warning(dt('Mark-a-Spot map settings configuration @name not found.', ['@name' => $config_name]));
    }
  }
}