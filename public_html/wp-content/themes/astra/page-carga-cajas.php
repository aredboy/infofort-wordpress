<?php
/* Template Name: Carga de Cajas */
acf_form_head();
get_header();
?>
<div class="container">
    <?php echo do_shortcode('[ansv_cajas_frontend]'); ?>
</div>
<?php get_footer(); ?>