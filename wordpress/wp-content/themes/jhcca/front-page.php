<?php get_header(); $img = fn($f) => esc_url(get_theme_file_uri('assets/img/' . $f)); ?>
<div class="center search-row"><?php get_search_form(); ?></div>
<section class="hero">
  <p>Join the Jaffna Hindu college Association Canada</p>
  <h3>Help us bring the</h3>
  <h3>Change we need</h3>
</section>
<div class="center">
  <h3 class="event-title">Kalaiyarasi - 2026</h3>
  <div class="banner"><a href="https://photos.app.goo.gl/5bU99vHAQ2HYp4Kf7"><img src="<?php echo $img('banner.png'); ?>" alt="Kalaiyarasi 2026"></a></div>
  <div class="photos">
    <img src="<?php echo $img('Ratheesh.jpeg'); ?>" alt="">
    <img src="<?php echo $img('platinum-web.jpeg'); ?>" alt="">
    <img src="<?php echo $img('mna-web.jpeg'); ?>" alt="">
    <img src="<?php echo $img('gajan-web.jpeg'); ?>" alt="">
  </div>
</div>
<?php get_footer();
