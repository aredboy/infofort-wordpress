<?php
/**
 * Template Menu Items Fronentd URLs.

 * @package Freesoul Deactivate Plugins
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
$no_url_admin_opts = ! eos_dp_get_option( 'eos_dp_by_admin_url' );
?>
<li data-section="eos-dp-plugin-by-url" class="eos-dp-submenu-item hover"><a href="<?php echo esc_url( admin_url( 'admin.php?page=eos_dp_one_place' ) ); ?>"><?php esc_html_e( 'Plugin by URL', 'freesoul-deactivate-plugins' ); ?></a></li>
<li data-section="eos-dp-url" class="eos-dp-submenu-item hover"><a href="<?php echo esc_url( admin_url( 'admin.php?page=eos_dp_url' ) ); ?>"><?php esc_html_e( 'Frontend URLs', 'freesoul-deactivate-plugins' ); ?></a></li>
<li data-section="eos-dp-admin-url" class="hover<?php echo '' === $pro && $no_url_admin_opts ? ' fdp-pro-feature fdp-dismiss-pro-notice' : ''; ?> eos-dp-submenu-item"><a href="<?php echo '' === $pro && $no_url_admin_opts ? esc_url( EOS_DP_DOCUMENTATION_URL . 'backend?fb=1' ) : esc_url( admin_url( 'admin.php?page=eos_dp_admin_url' ) ); ?>" target="_<?php echo '' === $pro && $no_url_admin_opts ? 'fdp_backend_docu' : 'self'; ?>"><?php esc_html_e( 'Backend URLs', 'freesoul-deactivate-plugins' ); ?></a></li>
<li data-section="eos-dp-translation-url" class="eos-dp-submenu-item hover
<?php
echo '' === $pro ? ' fdp-pro-feature fdp-dismiss-pro-notice fdp-show-only-on-pro' : '';
?>
"><a href="<?php echo '' === $pro ? esc_url( EOS_DP_DOCUMENTATION_URL . 'translation-urls' ) : esc_url( admin_url( 'admin.php?page=eos_dp_translation_urls' ) ); ?>"><?php esc_html_e( 'Translation URLs', 'freesoul-deactivate-plugins' ); ?></a></li>
