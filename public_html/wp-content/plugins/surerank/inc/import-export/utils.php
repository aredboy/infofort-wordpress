<?php
/**
 * Import Export Utilities
 *
 * Utility functions for import/export functionality.
 *
 * @package SureRank\Inc\Import_Export
 * @since 1.2.0
 */

namespace SureRank\Inc\Import_Export;

use SureRank\Inc\Functions\Sanitize;
use SureRank\Inc\Functions\Validate;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Utils
 *
 * Utility functions for import/export operations.
 */
class Utils {

	/**
	 * Ensure WordPress image processing functions are available
	 *
	 * This function ensures that all necessary WordPress image processing functions
	 * are loaded. This is crucial for generating image thumbnails and metadata
	 * that are required for proper display in the WordPress Media Library.
	 *
	 * @return bool True if functions are available, false otherwise.
	 * @since 1.2.0
	 */
	public static function ensure_image_functions() {

		return function_exists( '\wp_generate_attachment_metadata' )
			&& function_exists( '\wp_update_attachment_metadata' )
			&& function_exists( '\wp_create_image_subsizes' );
	}

	/**
	 * Generate and update attachment metadata with proper error handling
	 *
	 * This function generates thumbnails and image metadata for imported images
	 * to ensure they display properly in the WordPress Media Library. Without
	 * this metadata, images won't show preview thumbnails in /wp-admin/upload.php.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path Full path to the image file.
	 * @return bool True if metadata was generated successfully, false otherwise.
	 * @since 1.2.0
	 */
	public static function generate_attachment_metadata( $attachment_id, $file_path ) {
		if ( ! self::ensure_image_functions() ) {
			return false;
		}

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			return false;
		}

		// Validate that the attachment exists and is an image.
		$attachment = \get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return false;
		}

		$mime_type = \get_post_mime_type( $attachment_id );
		if ( ! $mime_type || ! str_starts_with( $mime_type, 'image/' ) ) {
			return false;
		}

		// Generate attachment metadata (creates thumbnails and image sizes).
		$attachment_data = \wp_generate_attachment_metadata( $attachment_id, $file_path );

		if ( ! empty( $attachment_data ) && is_array( $attachment_data ) ) {
			$updated = \wp_update_attachment_metadata( $attachment_id, $attachment_data );
			return false !== $updated;
		}

		return false;
	}

	/**
	 * Create success response
	 *
	 * @param mixed  $data Success data.
	 * @param string $message Success message.
	 * @return array<string, mixed> Success response array.
	 */
	public static function success_response( $data = [], $message = '' ) {
		$response = [
			'success' => true,
			'data'    => $data,
		];

		if ( ! empty( $message ) ) {
			$response['message'] = $message;
		}

		return $response;
	}

	/**
	 * Create error response
	 *
	 * @param string             $message Error message.
	 * @param array<int, string> $errors Array of error details.
	 * @param mixed              $data Additional data.
	 * @return array<string, mixed> Error response array.
	 */
	public static function error_response( $message = '', $errors = [], $data = null ) {
		$response = [
			'success' => false,
			'message' => $message,
		];

		if ( ! empty( $errors ) ) {
			$response['errors'] = $errors;
		}

		if ( null !== $data ) {
			$response['data'] = $data;
		}

		return $response;
	}

	/**
	 * Create validation result
	 *
	 * @param bool               $valid Whether validation passed.
	 * @param string             $message Validation message.
	 * @param array<int, string> $errors Array of validation errors.
	 * @return array<string, mixed> Validation result array.
	 */
	public static function validation_result( $valid, $message = '', $errors = [] ) {
		return [
			'valid'   => $valid,
			'message' => $message,
			'errors'  => Validate::array( $errors, [] ),
		];
	}

	/**
	 * Validate file upload data
	 *
	 * @param array<string, mixed> $file_data $_FILES array data.
	 * @return array<string, mixed> Validation result.
	 */
	public static function validate_uploaded_file( $file_data ) {
		$errors = [];

		// Validate file_data structure.
		if ( ! is_array( $file_data ) ) {
			return self::validation_result(
				false,
				__( 'Invalid file data.', 'surerank' ),
				[ __( 'File data must be an array.', 'surerank' ) ]
			);
		}

		// Check for upload errors.
		if ( ! empty( $file_data['error'] ) ) {
			switch ( $file_data['error'] ) {
				case UPLOAD_ERR_INI_SIZE:
				case UPLOAD_ERR_FORM_SIZE:
					$errors[] = __( 'The uploaded file exceeds the maximum file size.', 'surerank' );
					break;
				case UPLOAD_ERR_PARTIAL:
					$errors[] = __( 'The uploaded file was only partially uploaded.', 'surerank' );
					break;
				case UPLOAD_ERR_NO_FILE:
					$errors[] = __( 'No file was uploaded.', 'surerank' );
					break;
				default:
					$errors[] = __( 'File upload failed.', 'surerank' );
			}
		}

		// Validate required fields.
		if ( empty( $file_data['name'] ) ) {
			$errors[] = __( 'File name is missing.', 'surerank' );
		}

		if ( empty( $file_data['tmp_name'] ) ) {
			$errors[] = __( 'Temporary file path is missing.', 'surerank' );
		}

		// Check file type.
		if ( ! empty( $file_data['name'] ) ) {
			$file_extension = strtolower( pathinfo( $file_data['name'], PATHINFO_EXTENSION ) );
			if ( 'json' !== $file_extension ) {
				$errors[] = __( 'Only JSON files are allowed.', 'surerank' );
			}
		}

		// Check file size (max 5MB).
		$max_size = 5 * 1024 * 1024; // 5MB
		if ( ! empty( $file_data['size'] ) && $file_data['size'] > $max_size ) {
			$errors[] = sprintf(
				/* translators: %s: Maximum file size limit */
				__( 'File size exceeds maximum limit of %s.', 'surerank' ),
				number_format( $max_size / 1024 / 1024, 2 ) . ' MB'
			);
		}

		if ( ! empty( $errors ) ) {
			return self::validation_result(
				false,
				__( 'File validation failed.', 'surerank' ),
				$errors
			);
		}

		return self::validation_result(
			true,
			__( 'File is valid.', 'surerank' )
		);
	}

	/**
	 * Validate JSON data
	 *
	 * @param string $json_data JSON string to validate.
	 * @return array<string, mixed> Validation result with decoded data.
	 */
	public static function validate_json( $json_data ) {
		if ( ! is_string( $json_data ) || empty( trim( $json_data ) ) ) {
			return self::validation_result(
				false,
				__( 'Invalid JSON data.', 'surerank' ),
				[ __( 'JSON data must be a non-empty string.', 'surerank' ) ]
			);
		}

		$decoded_data = json_decode( $json_data, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return self::validation_result(
				false,
				__( 'Invalid JSON format.', 'surerank' ),
				[ json_last_error_msg() ]
			);
		}

		$result         = self::validation_result(
			true,
			__( 'JSON is valid.', 'surerank' )
		);
		$result['data'] = $decoded_data;

		return $result;
	}

	/**
	 * Validate import data structure
	 *
	 * @param array<string, mixed> $settings_data Import data to validate.
	 * @return array<string, mixed> Validation result.
	 */
	public static function validate_import_data( $settings_data ) {
		$errors = [];

		// Check if data is array.
		if ( ! is_array( $settings_data ) ) {
			return self::validation_result(
				false,
				__( 'Invalid settings data format.', 'surerank' ),
				[ __( 'Settings data must be an array.', 'surerank' ) ]
			);
		}

		// Check required fields.
		if ( ! isset( $settings_data['plugin'] ) || 'surerank' !== $settings_data['plugin'] ) {
			$errors[] = __( 'Invalid plugin identifier. This file does not contain SureRank settings.', 'surerank' );
		}

		if ( ! isset( $settings_data['settings'] ) || ! is_array( $settings_data['settings'] ) ) {
			$errors[] = __( 'Missing or invalid settings data.', 'surerank' );
		}

		// Validate version compatibility if needed.
		if ( isset( $settings_data['version'] ) ) {
			$version_check = self::validate_version_compatibility( $settings_data['version'] );
			if ( ! $version_check['compatible'] ) {
				$errors[] = $version_check['message'];
			}
		}

		if ( ! empty( $errors ) ) {
			return self::validation_result(
				false,
				__( 'Import data validation failed.', 'surerank' ),
				$errors
			);
		}

		return self::validation_result(
			true,
			__( 'Import data is valid.', 'surerank' )
		);
	}

	/**
	 * Validate version compatibility
	 *
	 * @param string $import_version Version from import data.
	 * @return array<string, mixed> Compatibility result.
	 */
	public static function validate_version_compatibility( $import_version ) {
		// For now, we'll accept all versions.
		// In the future, you might want to add version-specific logic.
		return [
			'compatible' => true,
			'message'    => __( 'Version compatible.', 'surerank' ),
		];
	}

	/**
	 * Sanitize categories array
	 *
	 * @param array<int, string> $categories Categories to sanitize.
	 * @param array<int, string> $valid_categories Valid category keys.
	 * @return array<int, string> Sanitized categories.
	 */
	public static function sanitize_categories( $categories, $valid_categories = [] ) {
		if ( ! is_array( $categories ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $categories as $category ) {
			$clean_category = Sanitize::text( $category );
			if ( ! empty( $clean_category ) && ( empty( $valid_categories ) || in_array( $clean_category, $valid_categories, true ) ) ) {
				$sanitized[] = $clean_category;
			}
		}

		return array_unique( $sanitized );
	}

	/**
	 * Get export header with metadata
	 *
	 * @return array<string, mixed> Export metadata.
	 */
	public static function get_export_header() {
		return [
			'plugin'     => 'surerank',
			'version'    => defined( 'SURERANK_VERSION' ) ? SURERANK_VERSION : '1.0.0',
			'timestamp'  => \current_time( 'mysql' ),
			'site_url'   => \get_site_url(),
			'wp_version' => \get_bloginfo( 'version' ),
		];
	}

	/**
	 * Generate backup key
	 *
	 * @return string Backup option key.
	 */
	public static function generate_backup_key() {
		return 'surerank_settings_backup_' . time();
	}

	/**
	 * Read file content safely
	 *
	 * @param string $file_path Path to file.
	 * @return array<string, mixed> Result with content or error.
	 */
	public static function read_file_content( $file_path ) {
		if ( ! is_string( $file_path ) || empty( trim( $file_path ) ) ) {
			return self::error_response(
				__( 'Invalid file path.', 'surerank' )
			);
		}

		if ( ! file_exists( $file_path ) ) {
			return self::error_response(
				__( 'File does not exist.', 'surerank' )
			);
		}

		if ( ! is_readable( $file_path ) ) {
			return self::error_response(
				__( 'File is not readable.', 'surerank' )
			);
		}

		// Use VIP-compatible file reading if available, otherwise fallback to standard function.
		$content = file_get_contents( $file_path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		if ( false === $content ) {
			return self::error_response(
				__( 'Failed to read file content.', 'surerank' )
			);
		}

		return self::success_response( $content );
	}

	/**
	 * Initialize import results structure
	 *
	 * @return array<string, mixed> Initial import results array.
	 */
	public static function init_import_results() {
		return [
			'success'        => false,
			'imported_count' => 0,
			'errors'         => [],
			'warnings'       => [],
			'success_items'  => [],
			'backup_key'     => null,
			'message'        => '',
		];
	}

	/**
	 * Get image setting keys that need special handling during import
	 *
	 * @return array<int, string> Array of image setting keys.
	 */
	public static function get_image_setting_keys() {
		return Settings_Exporter::get_instance()->get_image_setting_keys();
	}

	/**
	 * Download and save image from URL to WordPress media library
	 *
	 * @param string $image_url URL of the image to download.
	 * @param string $setting_key Setting key for naming context.
	 * @return array<string, mixed> Result with new URL or error.
	 */
	public static function download_and_save_image( $image_url, $setting_key = '' ) {
		if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return self::error_response(
				__( 'Invalid image URL.', 'surerank' )
			);
		}

		// Check if WordPress media functions are available.
		if ( ! function_exists( '\wp_upload_dir' ) || ! function_exists( '\wp_insert_attachment' ) ) {
			return self::error_response(
				__( 'WordPress media functions are not available.', 'surerank' )
			);
		}

		// Get upload directory info.
		$upload_dir = \wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return self::error_response(
				/* translators: %s: Upload directory error message */
				sprintf( __( 'Upload directory error: %s', 'surerank' ), $upload_dir['error'] )
			);
		}

		// Download the image with proper VIP compatibility.
		$response = wp_safe_remote_get(
			$image_url,
			[
				'timeout'    => 3,
				'user-agent' => 'SureRank WordPress Plugin',
			]
		);

		if ( \is_wp_error( $response ) ) {
			return self::error_response(
				/* translators: %s: Error message from wp_remote_get */
				sprintf( __( 'Failed to download image: %s', 'surerank' ), $response->get_error_message() )
			);
		}

		$response_code = \wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return self::error_response(
				/* translators: %d: HTTP response status code */
				sprintf( __( 'Failed to download image. HTTP status: %d', 'surerank' ), $response_code )
			);
		}

		$image_data = \wp_remote_retrieve_body( $response );
		if ( empty( $image_data ) ) {
			return self::error_response(
				__( 'Downloaded image is empty.', 'surerank' )
			);
		}

		// Check if image already exists before proceeding.
		$file_hash = md5( $image_data );
		$file_size = strlen( $image_data );

		// Get file info from URL.
		$parsed_url = \wp_parse_url( $image_url );
		if ( false === $parsed_url || ! isset( $parsed_url['path'] ) ) {
			return self::error_response(
				__( 'Invalid image URL format.', 'surerank' )
			);
		}

		$path_info         = pathinfo( $parsed_url['path'] );
		$extension         = $path_info['extension'] ?? 'jpg';
		$original_filename = ! empty( $path_info['filename'] ) ? $path_info['filename'] : 'surerank-image';

		$existing_image = self::find_existing_image( $file_hash, $file_size, $original_filename . '.' . $extension );
		if ( $existing_image['found'] ) {
			return self::success_response(
				[
					'url'           => $existing_image['url'],
					'attachment_id' => $existing_image['attachment_id'],
					'filename'      => $existing_image['filename'],
					'source'        => 'existing_download',
					'reused'        => true,
					'match_type'    => $existing_image['match_type'],
				],
				__( 'Using existing downloaded image from media library.', 'surerank' )
			);
		}

		$filename = $original_filename;

		// Add setting context to filename.
		if ( ! empty( $setting_key ) ) {
			$filename = 'surerank-' . \sanitize_title( $setting_key ) . '-' . $filename;
		} else {
			$filename = 'surerank-imported-' . $filename;
		}

		// Ensure unique filename.
		$filename  = \wp_unique_filename( $upload_dir['path'], $filename . '.' . $extension );
		$file_path = $upload_dir['path'] . '/' . $filename;

		// Save the image to WordPress uploads directory (VIP-compatible).
		$saved = file_put_contents( $file_path, $image_data ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		if ( false === $saved ) {
			return self::error_response(
				__( 'Failed to save downloaded image.', 'surerank' )
			);
		}

		// Create attachment in WordPress.
		$attachment = [
			'post_mime_type' => \wp_check_filetype( $filename )['type'],
			'post_title'     => \sanitize_file_name( $filename ),
			'post_content'   => '',
			'post_status'    => 'inherit',
		];

		/**
		 * Create WordPress attachment for downloaded image.
		 *
		 * @var int|\WP_Error $attachment_id Attachment ID or error object.
		 */
		$attachment_id = \wp_insert_attachment( $attachment, $file_path );
		if ( \is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
			// Clean up the file if attachment creation failed (VIP-compatible - file is in uploads directory).
			unlink( $file_path ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
			$error_message = \is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : __( 'Failed to create attachment.', 'surerank' );
			return self::error_response(
				/* translators: %s: Error message from attachment creation */
				sprintf( __( 'Failed to create attachment: %s', 'surerank' ), $error_message )
			);
		}

		// Include WordPress image processing functions if not already loaded.
		if ( ! function_exists( '\wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Generate attachment metadata (creates thumbnails and image sizes).
		self::generate_attachment_metadata( $attachment_id, $file_path );

		// Get the new URL.
		$new_url = \wp_get_attachment_url( $attachment_id );
		if ( ! $new_url ) {
			return self::error_response(
				__( 'Failed to get attachment URL.', 'surerank' )
			);
		}

		return self::success_response(
			[
				'url'           => $new_url,
				'attachment_id' => $attachment_id,
				'filename'      => $filename,
				'source'        => 'download_new_file',
				'reused'        => false,
			],
			__( 'Image downloaded and saved successfully.', 'surerank' )
		);
	}

	/**
	 * Process image settings during import
	 *
	 * @param array<string, mixed> $settings Settings array to process.
	 * @param array<string, mixed> $images_data Base64 images data from export.
	 * @param bool                 $process_images Whether to download and process images.
	 * @return array<string, mixed> Processed settings with downloaded images.
	 */
	public static function process_image_settings_import( $settings, $images_data = [], $process_images = true ) {
		// Allow disabling image processing via filter.
		$process_images = \apply_filters( 'surerank_import_process_images', $process_images );
		if ( ! $process_images ) {
			return $settings;
		}

		$image_keys         = self::get_image_setting_keys();
		$processed_settings = $settings;

		foreach ( $image_keys as $key ) {
			if ( ! isset( $settings[ $key ] ) || empty( $settings[ $key ] ) ) {
				continue;
			}

			$image_url = $settings[ $key ];

			// Check if we have base64 data for this image URL first.
			$found_base64_data = false;
			if ( ! empty( $images_data ) && isset( $images_data[ $image_url ] ) ) {
				$found_base64_data = true;
				$save_result       = self::save_base64_image( $images_data[ $image_url ], $key );
				if ( $save_result['success'] ) {
					$processed_settings[ $key ] = $save_result['data']['url'];
					continue;
				}
			}

			// Only skip local URLs if we don't have base64 data for them.
			// This allows importing to the same site with new copies of images.
			if ( ! $found_base64_data && strpos( $image_url, \home_url() ) !== false ) {
				continue;
			}

			// Fallback to downloading from URL if base64 failed or not available.
			$download_result = self::download_and_save_image( $image_url, $key );

			if ( $download_result['success'] ) {
				$processed_settings[ $key ] = $download_result['data']['url'];
			}
		}

		return $processed_settings;
	}

	/**
	 * Check if an image already exists in the media library
	 *
	 * @param string $file_hash MD5 hash of the file content.
	 * @param int    $file_size Size of the file in bytes.
	 * @param string $original_filename Original filename for additional matching.
	 * @return array<string, mixed> Result with existing attachment info or false.
	 */
	public static function find_existing_image( $file_hash, $file_size, $original_filename = '' ) {
		if ( empty( $file_hash ) || empty( $file_size ) ) {
			return [
				'found'         => false,
				'attachment_id' => 0,
				'url'           => '',
			];
		}

		// Look for potential matches by filename and check their content.
		if ( ! empty( $original_filename ) ) {
			$filename_query = new \WP_Query(
				[
					'post_type'      => 'attachment',
					'post_mime_type' => 'image',
					'post_status'    => 'inherit',
					's'              => basename( $original_filename, '.' . pathinfo( $original_filename, PATHINFO_EXTENSION ) ),
					'fields'         => 'ids',
					'posts_per_page' => 10, // Limit to avoid performance issues.
					'no_found_rows'  => true,
					'cache_results'  => true,
				]
			);

			if ( ! empty( $filename_query->posts ) ) {
				foreach ( $filename_query->posts as $attachment_id ) {
					$attachment_id = is_numeric( $attachment_id ) ? intval( $attachment_id ) : 0;
					if ( $attachment_id <= 0 ) {
						continue;
					}

					$file_path = \get_attached_file( $attachment_id );
					if ( ! $file_path || ! file_exists( $file_path ) ) {
						continue;
					}

					// Check file size first (quick check).
					$existing_size = filesize( $file_path );
					if ( $existing_size !== $file_size ) {
						continue;
					}

					// File sizes match, check content hash.
					$existing_content = function_exists( '\wpcom_vip_file_get_contents' )
						? \wpcom_vip_file_get_contents( $file_path )
						: file_get_contents( $file_path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown

					if ( empty( $existing_content ) ) {
						continue;
					}

					$existing_hash = md5( $existing_content );
					if ( $existing_hash === $file_hash ) {
						$url = \wp_get_attachment_url( $attachment_id );
						if ( $url ) {
							return [
								'found'         => true,
								'attachment_id' => $attachment_id,
								'url'           => $url,
								'filename'      => basename( $file_path ),
								'match_type'    => 'content',
							];
						}
					}
				}
			}
		}

		// As a last resort, if we have very few images, check recent image attachments.
		// This is only for cases where filename matching fails but we want to be thorough.
		$recent_images_query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_mime_type' => 'image',
				'post_status'    => 'inherit',
				'posts_per_page' => 20, // Only check recent images to avoid performance issues.
				'orderby'        => 'date',
				'order'          => 'DESC',
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'cache_results'  => true,
			]
		);

		if ( ! empty( $recent_images_query->posts ) ) {
			foreach ( $recent_images_query->posts as $attachment_id ) {
				$attachment_id = is_numeric( $attachment_id ) ? intval( $attachment_id ) : 0;
				if ( $attachment_id <= 0 ) {
					continue;
				}

				$file_path = \get_attached_file( $attachment_id );

				if ( ! $file_path || ! file_exists( $file_path ) ) {
					continue;
				}

				// Quick file size check.
				$existing_size = filesize( $file_path );
				if ( $existing_size !== $file_size ) {
					continue;
				}

				// File sizes match, check content hash.
				$existing_content = function_exists( '\wpcom_vip_file_get_contents' )
					? \wpcom_vip_file_get_contents( $file_path )
					: file_get_contents( $file_path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown

				if ( empty( $existing_content ) ) {
					continue;
				}

				$existing_hash = md5( $existing_content );
				if ( $existing_hash === $file_hash ) {
					$url = \wp_get_attachment_url( $attachment_id );
					if ( $url ) {
						return [
							'found'         => true,
							'attachment_id' => $attachment_id,
							'url'           => $url,
							'filename'      => basename( $file_path ),
							'match_type'    => 'content',
						];
					}
				}
			}
		}

		return [
			'found'         => false,
			'attachment_id' => 0,
			'url'           => '',
		];
	}

	/**
	 * Save base64 image data to WordPress media library
	 *
	 * @param array<string, mixed> $image_data Base64 image data.
	 * @param string               $setting_key Setting key for naming context.
	 * @return array<string, mixed> Result with new URL or error.
	 */
	public static function save_base64_image( $image_data, $setting_key = '' ) {
		// Handle both new format (data/mime) and export format (base64/mime_type).
		$base64_string = '';
		$mime_type     = '';
		$filename      = 'surerank-image';

		if ( is_array( $image_data ) ) {
			// Check for export format first (base64/mime_type).
			if ( isset( $image_data['base64'] ) && isset( $image_data['mime_type'] ) ) {
				$base64_string = $image_data['base64'];
				$mime_type     = $image_data['mime_type'];
				$filename      = $image_data['filename'] ?? $filename;
			} elseif ( isset( $image_data['data'] ) && isset( $image_data['mime'] ) ) {
				// Check for new format (data/mime).
				$base64_string = $image_data['data'];
				$mime_type     = $image_data['mime'];
				$filename      = $image_data['filename'] ?? $filename;
			}
		}

		if ( empty( $base64_string ) || empty( $mime_type ) ) {
			return self::error_response(
				__( 'Invalid base64 image data format.', 'surerank' )
			);
		}

		// Check if WordPress media functions are available.
		if ( ! function_exists( '\wp_upload_dir' ) || ! function_exists( '\wp_insert_attachment' ) ) {
			return self::error_response(
				__( 'WordPress media functions are not available.', 'surerank' )
			);
		}

		// Get upload directory info.
		$upload_dir = \wp_upload_dir();
		if ( ! empty( $upload_dir['error'] ) ) {
			return self::error_response(
				/* translators: %s: Upload directory error message */
				sprintf( __( 'Upload directory error: %s', 'surerank' ), $upload_dir['error'] )
			);
		}

		// Get file extension from mime type using WordPress function if available.
		$extension = 'jpg'; // fallback.
		if ( function_exists( '\wp_get_default_extension_for_mime_type' ) ) {
			$wp_extension = \wp_get_default_extension_for_mime_type( $mime_type );
			if ( $wp_extension ) {
				$extension = $wp_extension;
			}
		} else {
			// Fallback mapping.
			$mime_to_ext = [
				'image/jpeg'    => 'jpg',
				'image/jpg'     => 'jpg',
				'image/png'     => 'png',
				'image/gif'     => 'gif',
				'image/webp'    => 'webp',
				'image/svg+xml' => 'svg',
			];
			$extension   = $mime_to_ext[ $mime_type ] ?? 'jpg';
		}

		// Clean and decode base64 data.
		$base64_data = str_replace( ' ', '+', $base64_string );

		// Decode in chunks for large images (prevents memory issues).
		$decoded     = '';
		$chunk_size  = 256;
		$data_length = strlen( $base64_data );
		$max_i       = ceil( $data_length / $chunk_size );
		for ( $i = 0; $i < $max_i; $i++ ) {
			$decoded .= base64_decode( substr( $base64_data, $i * $chunk_size, $chunk_size ) );
		}

		if ( empty( $decoded ) ) {
			return self::error_response(
				__( 'Failed to decode base64 image data.', 'surerank' )
			);
		}

		// Check if image already exists before proceeding.
		$file_hash         = md5( $decoded );
		$file_size         = strlen( $decoded );
		$original_filename = ! empty( $image_data['filename'] ) ? $image_data['filename'] : 'surerank-image.' . $extension;

		$existing_image = self::find_existing_image( $file_hash, $file_size, $original_filename );
		if ( $existing_image['found'] ) {
			return self::success_response(
				[
					'url'           => $existing_image['url'],
					'attachment_id' => $existing_image['attachment_id'],
					'filename'      => $existing_image['filename'],
					'source'        => 'existing_file',
					'reused'        => true,
					'match_type'    => $existing_image['match_type'],
				],
				__( 'Using existing image from media library.', 'surerank' )
			);
		}

		// Use the exact original filename.
		$original_filename = ! empty( $image_data['filename'] ) ? $image_data['filename'] : 'surerank-image.' . $extension;

		// Use the original filename exactly as provided.
		$filename = $original_filename;

		// Only ensure the extension is correct if it's missing or wrong.
		$file_extension = pathinfo( $filename, PATHINFO_EXTENSION );
		if ( empty( $file_extension ) || $file_extension !== $extension ) {
			$filename_without_ext = pathinfo( $filename, PATHINFO_FILENAME );
			$filename             = $filename_without_ext . '.' . $extension;
		}

		// Ensure unique filename using WordPress function if available.
		if ( function_exists( '\wp_unique_filename' ) ) {
			$filename = \wp_unique_filename( $upload_dir['path'], $filename );
		} else {
			// Simple unique filename fallback.
			$counter       = 1;
			$original_name = pathinfo( $filename, PATHINFO_FILENAME );
			$ext           = pathinfo( $filename, PATHINFO_EXTENSION );
			while ( file_exists( $upload_dir['path'] . '/' . $filename ) ) {
				$filename = $original_name . '-' . $counter . '.' . $ext;
				$counter++;
			}
		}

		// Use the filename directly.
		$hashed_filename = $filename;

		// Full file path.
		$upload_path = str_replace( '/', DIRECTORY_SEPARATOR, $upload_dir['path'] ) . DIRECTORY_SEPARATOR;
		$file_path   = $upload_path . $hashed_filename;

		// Save the image file to WordPress uploads directory (VIP-compatible).
		$saved = file_put_contents( $file_path, $decoded ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents
		if ( false === $saved ) {
			return self::error_response(
				__( 'Failed to save image from base64 data.', 'surerank' )
			);
		}

		// Create attachment array with proper GUID (following WordPress best practices).
		$attachment = [
			'post_mime_type' => $mime_type,
			'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $hashed_filename ) ),
			'post_content'   => '',
			'post_status'    => 'inherit',
			'guid'           => $upload_dir['url'] . '/' . basename( $hashed_filename ),
		];

		/**
		 * Create WordPress attachment for base64 image.
		 *
		 * @var int|\WP_Error $attachment_id Attachment ID or error object.
		 */
		$attachment_id = \wp_insert_attachment( $attachment, $upload_dir['path'] . '/' . $hashed_filename );
		if ( \is_wp_error( $attachment_id ) || 0 === $attachment_id ) {
			// Clean up the file if attachment creation failed (VIP-compatible - file is in uploads directory).
			if ( file_exists( $file_path ) ) {
				unlink( $file_path ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
			}
			$error_message = \is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : __( 'Failed to create attachment.', 'surerank' );
			return self::error_response(
				/* translators: %s: Error message from attachment creation */
				sprintf( __( 'Failed to create attachment: %s', 'surerank' ), $error_message )
			);
		}

		// Include WordPress image processing functions if not already loaded.
		if ( ! function_exists( '\wp_generate_attachment_metadata' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		// Generate attachment metadata (required for WordPress to recognize the image properly).
		self::generate_attachment_metadata( $attachment_id, $upload_dir['path'] . '/' . $hashed_filename );

		// Get the new URL.
		$new_url = '';
		if ( function_exists( '\wp_get_attachment_url' ) ) {
			$new_url = \wp_get_attachment_url( $attachment_id );
		} else {
			// Fallback URL construction.
			$new_url = $upload_dir['url'] . '/' . $hashed_filename;
		}

		if ( empty( $new_url ) ) {
			return self::error_response(
				__( 'Failed to get attachment URL.', 'surerank' )
			);
		}

		return self::success_response(
			[
				'url'           => $new_url,
				'attachment_id' => $attachment_id,
				'filename'      => $hashed_filename,
				'source'        => 'base64_new_file',
				'reused'        => false,
			],
			__( 'Image saved from base64 data successfully.', 'surerank' )
		);
	}
}
