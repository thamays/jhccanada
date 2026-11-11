<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width,initial-scale=1">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<header class="site-header">
  <div class="center"><div class="head_inner">
    <div class="logo"><a href="<?php echo esc_url(home_url('/')); ?>" title="JHCA Canada"><img src="<?php echo esc_url(get_theme_file_uri('assets/img/logo.png')); ?>" alt="JHCA Canada"></a></div>
    <a id="simple-menu" href="#" aria-label="Menu" aria-expanded="false">&#9776;</a>
    <nav id="topmenu">
      <?php wp_nav_menu(['theme_location' => 'header', 'container' => false]); ?>
    </nav>
  </div></div>
</header>
