<?php
/**
 * Template Menu Items Backend.

 * @package Freesoul Deactivate Plugins
 */

defined( 'ABSPATH' ) || exit; // Exit if accessed directly.
$no_admin_opts = ! ( eos_dp_get_option( 'eos_dp_admin_setts' ) || eos_dp_get_option( 'eos_dp_by_admin_url' ) );
?>
<li data-section="eos-dp-backend-everywhere" class="hover<?php echo '' === $pro && $no_admin_opts ? ' fdp-pro-feature fdp-dismiss-pro-notice' : ''; ?> eos-dp-setts-menu-item"><a href="<?php echo '' === $pro && $no_admin_opts ? esc_url( EOS_DP_DOCUMENTATION_URL . 'backend?fb=1' ) : esc_url( admin_url( 'admin.php?page=eos_dp_backend_everywhere' ) ); ?>" target="_<?php echo '' === $pro && $no_admin_opts ? 'fdp_backend_docu' : 'self'; ?>"><?php esc_html_e( 'Disabled on backend', 'freesoul-deactivate-plugins' ); ?></a></li>
<li data-section="eos-dp-admin" class="hover<?php echo '' === $pro && $no_admin_opts ? ' fdp-pro-feature fdp-dismiss-pro-notice' : ''; ?> eos-dp-setts-menu-item"><a href="<?php echo '' === $pro && $no_admin_opts ? esc_url( EOS_DP_DOCUMENTATION_URL . 'backend?fb=1' ) : esc_url( admin_url( 'admin.php?page=eos_dp_admin&all=1' ) ); ?>" target="_<?php echo '' === $pro && $no_admin_opts ? 'fdp_backend_docu' : 'self'; ?>"><?php esc_html_e( 'Backend Singles', 'freesoul-deactivate-plugins' ); ?></a></li>
<li data-section="eos-dp-admin-url" class="hover<?php echo '' === $pro && $no_admin_opts ? ' fdp-pro-feature fdp-dismiss-pro-notice' : ''; ?> eos-dp-submenu-item"><a href="<?php echo '' === $pro && $no_admin_opts ? esc_url( EOS_DP_DOCUMENTATION_URL . 'backend?fb=1' ) : esc_url( admin_url( 'admin.php?page=eos_dp_admin_url' ) ); ?>" target="_<?php echo '' === $pro && $no_admin_opts ? 'fdp_backend_docu' : 'self'; ?>"><?php esc_html_e( 'Backend URLs', 'freesoul-deactivate-plugins' ); ?></a></li>
