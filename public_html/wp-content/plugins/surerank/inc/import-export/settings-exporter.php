<?php
/**
 * Settings Exporter class
 *
 * Handles exporting SureRank settings to JSON format.
 *
 * @package SureRank\Inc\Import_Export
 * @since 1.2.0
 */

namespace SureRank\Inc\Import_Export;

use SureRank\Inc\Functions\Defaults;
use SureRank\Inc\Functions\Get;
use SureRank\Inc\Traits\Get_Instance;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * Class Settings_Exporter
 *
 * @since 1.2.0
 */
class Settings_Exporter {

	use Get_Instance;

	/**
	 * Setting keys for each category within the main surerank_settings option
	 *
	 * @var array<string, array<int, string>>
	 */
	private $setting_keys = [
		'general'  => [
			'separator',
			'page_title',
			'auto_generate_description',
			'page_description',
			'auto_description',
			'canonical_url',
			'home_page_title',
			'home_page_description',
			'home_page_facebook_title',
			'home_page_facebook_description',
			'home_page_twitter_title',
			'home_page_twitter_description',
			'home_page_robots',
			'index_home_page_paginated_pages',
			'enable_page_level_seo',
			'enable_google_console',
			'enable_schemas',
			// Other general settings.
			'author_archive',
			'date_archive',
			'noindex_paginated_pages',
			'paginated_link_relationships',
			'redirect_attachment_pages_to_post_parent',
			'auto_set_image_title',
			'auto_set_image_alt',
		],
		'social'   => [
			'open_graph_tags',
			'facebook_meta_tags',
			'twitter_meta_tags',
			'oembeded_scripts',
			'oembeded_og_title',
			'oembeded_remove_author_name',
			'facebook_page_url',
			'facebook_author_fallback',
			'twitter_card_type',
			'twitter_same_as_facebook',
			'twitter_profile_username',
			'twitter_profile_fallback',
			'social_profiles',
		],
		'advanced' => [
			// Feeds settings.
			'addlink_to_source_below_feed_entries',
			'remove_global_comments_feed',
			'remove_post_authors_feed',
			'remove_post_types_feed',
			'remove_category_feed',
			'remove_tag_feeds',
			'remove_custom_taxonomy_feeds',
			'remove_search_results_feed',
			'remove_atom_rdf_feeds',
			// Sitemaps settings.
			'enable_xml_sitemap',
			'enable_xml_image_sitemap',
			'enable_author_sitemap',
			'enable_html_sitemap',
			'sitemap_display_shortcode',
			'sitemap_display_format',
			'enable_xml_video_sitemap',
			'enable_xml_news_sitemap',
			// Robots settings.
			'no_index',
			'no_follow',
			'no_archive',
		],
		'schema'   => [
			'schemas',
		],
		'images'   => [
			'oembeded_social_images',
			'fallback_image',
			'auto_generated_og_image',
			'home_page_facebook_image_url',
			'home_page_twitter_image_url',
		],
	];

	/**
	 * Get image setting keys that need special handling during import
	 *
	 * @return array<int, string> Array of image setting keys.
	 */
	public function get_image_setting_keys() {
		return $this->setting_keys['images'];
	}

	/**
	 * Export settings for specified categories
	 *
	 * @param array<int, string> $categories Array of category IDs to export.
	 * @param bool               $include_images Whether to include image data in export.
	 * @return array<string, mixed> Export data with success status and data/message.
	 */
	public function export( $categories = [], $include_images = true ) {
		if ( empty( $categories ) ) {
			return Utils::error_response(
				__( 'No categories specified for export.', 'surerank' )
			);
		}

		$export_data             = Utils::get_export_header();
		$export_data['settings'] = [];
		$export_data['images']   = [];

		$exported_count = 0;

		foreach ( $categories as $category ) {
			if ( ! $this->is_valid_category( $category ) ) {
				continue;
			}

			$category_data = $this->export_category( $category );
			if ( ! empty( $category_data ) ) {
				$export_data['settings'][ $category ] = $category_data;
				$exported_count++;
			}
		}

		// Include image data if requested.
		if ( $include_images ) {
			$export_data['images'] = $this->export_images( $export_data['settings'] );
		}

		if ( 0 === $exported_count ) {
			return Utils::error_response(
				__( 'No settings found to export for the selected categories.', 'surerank' )
			);
		}

		return Utils::success_response(
			$export_data,
			sprintf(
				/* translators: %d: number of categories exported */
				__( 'Successfully exported settings for %d categories.', 'surerank' ),
				$exported_count
			)
		);
	}

	/**
	 * Export settings for a specific category
	 *
	 * @param string $category Category ID.
	 * @return array<string, mixed> Category settings data.
	 */
	public function export_category( $category ) {
		if ( ! isset( $this->setting_keys[ $category ] ) ) {
			return [];
		}

		// Get the default values and merge with saved settings (same as SureRank core).
		$defaults       = Defaults::get_instance()->get_global_defaults();
		$saved_settings = Get::option( SURERANK_SETTINGS, [], 'array' );
		$all_settings   = is_array( $saved_settings ) && is_array( $defaults ) ? array_merge( $defaults, $saved_settings ) : $defaults;

		if ( empty( $all_settings ) ) {
			return [];
		}

		$category_settings = [];
		$keys              = $this->setting_keys[ $category ];

		// Extract only the keys that belong to this category.
		foreach ( $keys as $key ) {
			if ( isset( $all_settings[ $key ] ) ) {
				$category_settings[ $key ] = $all_settings[ $key ];
			}
		}

		return $category_settings;
	}

	/**
	 * Check if a category is valid
	 *
	 * @param string $category Category ID.
	 * @return bool True if valid, false otherwise.
	 */
	public function is_valid_category( $category ) {
		return array_key_exists( $category, $this->get_categories() );
	}

	/**
	 * Get all available categories for export
	 *
	 * @return array<string, string> Array of categories with IDs and labels.
	 */
	public function get_categories() {
		return [
			'general'  => __( 'General Settings', 'surerank' ),
			'advanced' => __( 'Advanced Settings', 'surerank' ),
			'social'   => __( 'Social Media Settings', 'surerank' ),
			'schema'   => __( 'Schema Settings', 'surerank' ),
			'images'   => __( 'Required Resources', 'surerank' ),
		];
	}

	/**
	 * Get setting keys for a specific category
	 *
	 * @param string $category Category ID.
	 * @return array<int, string> Array of setting keys for the category.
	 */
	public function get_category_setting_keys( $category ) {
		return $this->setting_keys[ $category ] ?? [];
	}

	/**
	 * Export images as base64 data from settings
	 *
	 * @param array<string, array<string, mixed>> $settings Exported settings data.
	 * @return array<string, array<string, mixed>> Images data with base64 content.
	 */
	private function export_images( $settings ) {
		$image_keys  = $this->get_image_setting_keys();
		$images_data = [];

		foreach ( $settings as $category => $category_settings ) {
			foreach ( $image_keys as $image_key ) {
				if ( ! isset( $category_settings[ $image_key ] ) || empty( $category_settings[ $image_key ] ) ) {
					continue;
				}

				$image_url = $category_settings[ $image_key ];

				// Skip if already processed.
				if ( isset( $images_data[ $image_url ] ) ) {
					continue;
				}

				// Get image data.
				$image_data = $this->get_image_base64_data( $image_url );
				if ( $image_data['success'] ) {
					$images_data[ $image_url ] = [
						'data'     => $image_data['data']['base64'],
						'mime'     => $image_data['data']['mime_type'],
						'filename' => $image_data['data']['filename'],
						'size'     => $image_data['data']['size'],
					];
				}
			}
		}

		return $images_data;
	}

	/**
	 * Get image as base64 data
	 *
	 * @param string $image_url URL of the image to convert.
	 * @return array<string, mixed> Result with base64 data or error.
	 */
	private function get_image_base64_data( $image_url ) {
		if ( empty( $image_url ) || ! filter_var( $image_url, FILTER_VALIDATE_URL ) ) {
			return Utils::error_response(
				__( 'Invalid image URL.', 'surerank' )
			);
		}

		// Try to get the image content.
		$image_content = $this->fetch_image_content( $image_url );
		if ( ! $image_content['success'] ) {
			return $image_content;
		}

		$content = $image_content['data'];

		// Get image info using WordPress upload directory for VIP compatibility.
		$upload_dir = wp_upload_dir();
		$temp_file  = $upload_dir['path'] . '/surerank_image_temp_' . uniqid() . '.tmp';
		// Write to uploads directory (VIP-compatible).
		file_put_contents( $temp_file, $content ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_file_put_contents

		$image_info = getimagesize( $temp_file );
		if ( file_exists( $temp_file ) ) {
			// Clean up temp file from uploads directory (VIP-compatible).
			unlink( $temp_file ); // phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.file_ops_unlink
		}

		if ( false === $image_info ) {
			return Utils::error_response(
				__( 'Invalid image format.', 'surerank' )
			);
		}

		// Get filename from URL.
		$parsed_url = wp_parse_url( $image_url );
		$filename   = 'image';
		if ( isset( $parsed_url['path'] ) ) {
			$path_info = pathinfo( $parsed_url['path'] );
			$filename  = ! empty( $path_info['basename'] ) ? $path_info['basename'] : 'image';
		}

		return Utils::success_response(
			[
				'base64'    => base64_encode( $content ),
				'mime_type' => $image_info['mime'],
				'filename'  => $filename,
				'size'      => strlen( $content ),
				'width'     => $image_info[0],
				'height'    => $image_info[1],
			],
			__( 'Image converted to base64 successfully.', 'surerank' )
		);
	}

	/**
	 * Fetch image content from URL
	 *
	 * @param string $image_url URL to fetch from.
	 * @return array<string, mixed> Result with content or error.
	 */
	private function fetch_image_content( $image_url ) {
		// Check if it's a local file first.
		if ( $this->is_local_url( $image_url ) ) {
			return $this->get_local_image_content( $image_url );
		}

		// For external URLs, use wp_remote_get with VIP compatibility.
		$response = wp_safe_remote_get(
			$image_url,
			[
				'timeout'    => 3,
				'user-agent' => 'SureRank WordPress Plugin',
			]
		);

		if ( is_wp_error( $response ) ) {
			return Utils::error_response(
				/* translators: %s: Error message from wp_remote_get */
				sprintf( __( 'Failed to fetch image: %s', 'surerank' ), $response->get_error_message() )
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $response_code ) {
			return Utils::error_response(
				/* translators: %d: HTTP response status code */
				sprintf( __( 'Failed to fetch image. HTTP status: %d', 'surerank' ), $response_code )
			);
		}

		$content = wp_remote_retrieve_body( $response );
		if ( empty( $content ) ) {
			return Utils::error_response(
				__( 'Fetched image is empty.', 'surerank' )
			);
		}

		return Utils::success_response( $content );
	}

	/**
	 * Check if URL is local to current site
	 *
	 * @param string $url URL to check.
	 * @return bool True if local, false otherwise.
	 */
	private function is_local_url( $url ) {
		$site_url = home_url();
		return strpos( $url, $site_url ) === 0;
	}

	/**
	 * Get local image content
	 *
	 * @param string $image_url Local image URL.
	 * @return array<string, mixed> Result with content or error.
	 */
	private function get_local_image_content( $image_url ) {
		// Convert URL to file path.
		$upload_dir = wp_upload_dir();
		$base_url   = $upload_dir['baseurl'];

		if ( strpos( $image_url, $base_url ) !== 0 ) {
			return Utils::error_response(
				__( 'Image URL is not in uploads directory.', 'surerank' )
			);
		}

		$relative_path = str_replace( $base_url, '', $image_url );
		$file_path     = $upload_dir['basedir'] . $relative_path;

		if ( ! file_exists( $file_path ) ) {
			return Utils::error_response(
				__( 'Image file does not exist.', 'surerank' )
			);
		}

		if ( ! is_readable( $file_path ) ) {
			return Utils::error_response(
				__( 'Image file is not readable.', 'surerank' )
			);
		}

		// Use VIP-compatible file reading if available, otherwise fallback to standard function.
		$content = file_get_contents( $file_path ); // phpcs:ignore WordPressVIPMinimum.Performance.FetchingRemoteData.FileGetContentsUnknown
		if ( false === $content ) {
			return Utils::error_response(
				__( 'Failed to read image file.', 'surerank' )
			);
		}

		return Utils::success_response( $content );
	}
}
