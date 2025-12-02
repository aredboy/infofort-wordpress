<?php
/**
 * Settings Importer class
 *
 * Handles importing SureRank settings from JSON format.
 *
 * @package SureRank\Inc\Import_Export
 * @since 1.2.0
 */

namespace SureRank\Inc\Import_Export;

use SureRank\Inc\Functions\Get;
use SureRank\Inc\Functions\Update;
use SureRank\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Settings_Importer
 *
 * Handles settings import functionality.
 */
class Settings_Importer {
	use Get_Instance;

	/**
	 * Settings Exporter instance
	 *
	 * @var Settings_Exporter
	 */
	private $exporter;

	/**
	 * Import results
	 *
	 * @var array<string, mixed>
	 */
	private $import_results = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->exporter = Settings_Exporter::get_instance();
	}

	/**
	 * Import settings from data array
	 *
	 * @param array<string, mixed> $settings_data Import data structure.
	 * @param array<string, mixed> $options Import options (overwrite, backup, etc.).
	 * @return array<string, mixed> Import results.
	 */
	public function import( $settings_data, $options = [] ) {
		$this->reset_import_results();

		// Validate the import data.
		$validation_result = Utils::validate_import_data( $settings_data );
		if ( ! $validation_result['valid'] ) {
			return Utils::error_response(
				$validation_result['message'],
				$validation_result['errors']
			);
		}

		$enable_backup = apply_filters( 'surerank_create_settings_backup', true );
		// Create backup if requested.
		if ( ! empty( $options['create_backup'] ) && $enable_backup ) {
			$this->create_backup();
		}

		// Process import.
		$this->process_import( $settings_data, $options );

		return $this->get_import_results();
	}

	/**
	 * Import settings from JSON string.
	 *
	 * @param string               $json_data JSON string containing settings data.
	 * @param array<string, mixed> $options Import options.
	 * @return array<string, mixed> Import results.
	 */
	public function import_from_json( $json_data, $options = [] ) {
		$json_result = Utils::validate_json( $json_data );

		if ( ! $json_result['valid'] ) {
			return Utils::error_response(
				$json_result['message'],
				$json_result['errors']
			);
		}

		return $this->import( $json_result['data'], $options );
	}

	/**
	 * Import settings from uploaded file.
	 *
	 * @param array<string, mixed> $file_data $_FILES array data.
	 * @param array<string, mixed> $options Import options.
	 * @return array<string, mixed> Import results.
	 */
	public function import_from_file( $file_data, $options = [] ) {
		// Validate file.
		$file_validation = Utils::validate_uploaded_file( $file_data );
		if ( ! $file_validation['valid'] ) {
			return Utils::error_response(
				$file_validation['message'],
				$file_validation['errors']
			);
		}

		// Read file content.
		$file_read_result = Utils::read_file_content( $file_data['tmp_name'] );
		if ( ! $file_read_result['success'] ) {
			return $file_read_result;
		}

		return $this->import_from_json( $file_read_result['data'], $options );
	}

	/**
	 * Process the actual import.
	 *
	 * @param array<string, mixed> $settings_data Settings data to import.
	 * @param array<string, mixed> $options Import options.
	 * @return void
	 */
	private function process_import( $settings_data, $options = [] ) {
		$overwrite      = ! empty( $options['overwrite'] );
		$process_images = ! isset( $options['process_images'] ) || ! empty( $options['process_images'] );
		$settings       = $settings_data['settings'];
		$images_data    = $settings_data['images'] ?? [];

		// Get current SureRank settings once.
		$current_settings  = Get::option( SURERANK_SETTINGS, [], 'array' );
		$updated_settings  = is_array( $current_settings ) ? $current_settings : [];
		$all_imported_keys = [];

		foreach ( $settings as $category => $category_options ) {
			if ( ! $this->exporter->is_valid_category( $category ) ) {
				$this->add_import_error(
					/* translators: %s: Category name that was skipped during import */
					sprintf( __( 'Skipped invalid category: %s', 'surerank' ), $category )
				);
				continue;
			}

			$imported_keys     = $this->process_category_options( $category, $category_options, $updated_settings, $overwrite, $process_images, $images_data );
			$all_imported_keys = array_merge( $all_imported_keys, $imported_keys );
		}

		// Update the database once with all changes.
		if ( ! empty( $all_imported_keys ) ) {
			$this->save_imported_settings( $updated_settings, $all_imported_keys );
		}
	}

	/**
	 * Process options for a specific category
	 *
	 * @param string               $category Category ID.
	 * @param array<string, mixed> $options Category options to import.
	 * @param array<string, mixed> &$updated_settings Reference to updated settings array.
	 * @param bool                 $overwrite Whether to overwrite existing options.
	 * @param bool                 $process_images Whether to download and process images.
	 * @param array<string, mixed> $images_data Base64 images data.
	 * @return array<int, string> Array of successfully processed setting keys.
	 */
	private function process_category_options( $category, $options, &$updated_settings, $overwrite = true, $process_images = true, $images_data = [] ) {
		$valid_setting_keys = $this->exporter->get_category_setting_keys( $category );

		if ( empty( $valid_setting_keys ) ) {
			$this->add_import_error(
				/* translators: %s: Category name with no valid setting keys */
				sprintf( __( 'No valid setting keys found for category: %s', 'surerank' ), $category )
			);
			return [];
		}

		// Process image settings first if they exist.
		$processed_options = Utils::process_image_settings_import( $options, $images_data, $process_images );

		$imported_keys = [];

		foreach ( $processed_options as $setting_key => $setting_value ) {
			// Security check: only allow valid SureRank setting keys for this category.
			if ( ! in_array( $setting_key, $valid_setting_keys, true ) ) {
				$this->add_import_error(
					/* translators: %s: Option name that was skipped during import */
					sprintf( __( 'Skipped invalid option: %s', 'surerank' ), $setting_key )
				);
				continue;
			}

			// Check if setting already exists and overwrite is disabled.
			if ( ! $overwrite && isset( $updated_settings[ $setting_key ] ) ) {
				$this->add_import_warning(
					/* translators: %s: Option name that was skipped because it already exists */
					sprintf( __( 'Skipped existing option: %s', 'surerank' ), $setting_key )
				);
				continue;
			}

			// Import the setting.
			$updated_settings[ $setting_key ] = $setting_value;
			$imported_keys[]                  = $setting_key;

			// Add success note for processed images.
			if ( $process_images &&
				in_array( $setting_key, Utils::get_image_setting_keys(), true ) &&
				isset( $options[ $setting_key ] ) &&
				$options[ $setting_key ] !== $setting_value ) {
				$this->add_import_warning(
					/* translators: %s: Setting key name for which an image was downloaded and processed */
					sprintf( __( 'Downloaded and processed image for: %s', 'surerank' ), $setting_key )
				);
			}
		}

		return $imported_keys;
	}

	/**
	 * Save imported settings to database
	 *
	 * @param array<string, mixed> $updated_settings The updated settings array.
	 * @param array<int, string>   $imported_keys Array of imported setting keys.
	 * @return void
	 */
	private function save_imported_settings( $updated_settings, $imported_keys ) {
		// Always use update_option with forced update to handle cases where values are the same.
		Update::option( SURERANK_SETTINGS, $updated_settings );

		// For WordPress, update_option returns false if the value is the same.
		// So we need to check if the current value in database matches what we tried to save.
		$saved_settings  = Get::option( SURERANK_SETTINGS, [], 'array' );
		$save_successful = true;

		// Verify that all imported keys were saved correctly.
		foreach ( $imported_keys as $key ) {
			if ( ! isset( $saved_settings[ $key ] ) || $saved_settings[ $key ] !== $updated_settings[ $key ] ) {
				$save_successful = false;
				break;
			}
		}

		if ( $save_successful ) {
			// Mark all imported keys as successful.
			foreach ( $imported_keys as $imported_key ) {
				$this->add_import_success( $imported_key );
			}
		} else {
			$this->add_import_error(
				__( 'Failed to save imported settings to database.', 'surerank' )
			);
		}
	}

	/**
	 * Create backup of current settings
	 *
	 * @return void
	 */
	private function create_backup() {
		// Get all categories current snapshot.
		// This ensures we backup all settings, not just the ones being imported.
		$backup_data = Get::option( SURERANK_SETTINGS, [], 'array' );
		$backup_key  = Utils::generate_backup_key();

		Update::option( $backup_key, $backup_data );
		$this->import_results['backup_key'] = $backup_key;
	}

	/**
	 * Reset import results
	 *
	 * @return void
	 */
	private function reset_import_results() {
		$this->import_results = Utils::init_import_results();
	}

	/**
	 * Add import success
	 *
	 * @param string $option_key Successfully imported option key.
	 * @return void
	 */
	private function add_import_success( $option_key ) {
		$this->import_results['imported_count']++;
		$this->import_results['success_items'][] = $option_key;
	}

	/**
	 * Add import error
	 *
	 * @param string $error Error message.
	 * @return void
	 */
	private function add_import_error( $error ) {
		$this->import_results['errors'][] = $error;
	}

	/**
	 * Add import warning
	 *
	 * @param string $warning Warning message.
	 * @return void
	 */
	private function add_import_warning( $warning ) {
		$this->import_results['warnings'][] = $warning;
	}

	/**
	 * Get final import results
	 *
	 * @return array<string, mixed> Import results.
	 */
	private function get_import_results() {
		$this->import_results['success'] = $this->import_results['imported_count'] > 0;

		if ( $this->import_results['success'] ) {
			$this->import_results['message'] = sprintf(
				/* translators: %d: Number of settings that were successfully imported */
				__( 'Successfully imported %d settings.', 'surerank' ),
				$this->import_results['imported_count']
			);
		} else {
			$this->import_results['message'] = __( 'No settings were imported.', 'surerank' );
		}

		return $this->import_results;
	}
}
