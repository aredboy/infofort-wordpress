<?php
/**
 * Class for the Site Health Warnings.

 * @package Freesoul Deactivate Plugins
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.

/**
 * FDP_Site_Healt
 */

class FDP_Site_Health {

    private $plugin_conflicts;

    public function __construct() {
        $this->plugin_conflicts = '';
        add_filter( 'site_status_tests', array( $this, 'get_tests' ) );
    }


    public function get_tests( $tests ) {
        $rewrite_rules_warning = get_site_transient( 'fdp_admin_notice_rewrite_rules' );
        if ( $rewrite_rules_warning ) {
            $tests['direct']['fdp_rewrite_rules'] = array(
                'label' => esc_html__( 'One of your plugins or the theme is flushing the rewrite rules in a not proper way.', 'freesoul-deactivate-plugins' ),
                'test'  => array( $this, 'get_rewrite_rules_test' )
            );
        }
        if ( isset( $GLOBALS['fdp_all_plugins'] ) && is_array( $GLOBALS['fdp_all_plugins'] ) ) {
            $conflicts = $this->plugin_conflicts;
            foreach ( $GLOBALS['fdp_all_plugins'] as $active_plugin ) {
                if ( file_exists( EOS_DP_PLUGIN_DIR . '/inc/plugin-conflicts/' . dirname( $active_plugin ) . '.php' ) ) {
                    require_once EOS_DP_PLUGIN_DIR . '/inc/plugin-conflicts/' . dirname( $active_plugin ) . '.php';
                    $conflicts   .= sprintf( __( 'Another user had an issue with the plugin %1$s. Read this %2$ssupport thread%3$s for more details. It may help you to avoid the same issue on your website.', 'freesoul-deactivate-plugins' ), esc_attr( strtoupper( str_replace( '-', ' ', dirname( $active_plugin ) ) ) ), '<a title="' . __( 'Link to support thread', 'freesoul-deactivate-plugins' ) . '" href="' . esc_url( $support_thread_url ) . '" target="_blank" rel="noopener">', '</a>' );
                }
            }
            if( ! empty( $conflicts ) ) {
                $tests['direct']['fdp_plugin_conflicts'] = array(
                    'label' => esc_html__( 'You may have conflicts between plugins on your site.', 'freesoul-deactivate-plugins' ),
                    'test'  => array( $this, 'get_plugin_conflicts_test' )
                );
            }
            $this->plugin_conflicts = $conflicts;
            
        }
        return $tests;
    }

    public function get_rewrite_rules_test() {
        return array(     
            'label'			=> esc_html__( 'One of your plugins or the theme is flushing the rewrite rules in a not proper way.', 'freesoul-deactivate-plugins' ),
            'status'		=> 'critical',
            'description'		=> sprintf( '<p><a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>', esc_url( admin_url( 'admin.php?page=eos_dp_menu&open-notification=fdp-rewrite_rules-notice' ) ), esc_html__( 'Read the FDP notification for more details', 'freesoul-deactivate-plugins' ) ),
            'test'			=> 'fdp_rewrite_rules',
            'badge'			=> array(
                  'label'	=> esc_html__( 'Performance' ),
                  'color'	=> 'blue'
                )
            );
    }

    public function get_plugin_conflicts_test() {
        return array(     
            'label'			=> esc_html__( 'You have conflicts between plugins on your site.', 'freesoul-deactivate-plugins' ),
            'status'		=> 'critical',
            'description'	=> wp_kses_post( $this->plugin_conflicts ),
            'test'			=> 'fdp_plugin_conflicts',
            'badge'			=> array(
                  'label'	=> esc_html__( 'Conflicts', 'freesoul-deactivate-plugins' ),
                  'color'	=> 'blue'
                )
            );
    }
}

  