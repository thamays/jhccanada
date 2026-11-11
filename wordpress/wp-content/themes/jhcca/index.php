<?php get_header(); ?>
<main class="center page-wrap">
<?php if (have_posts()) : while (have_posts()) : the_post(); ?>
  <article <?php post_class(); ?>>
    <h1><?php if (is_singular()) the_title(); else echo '<a href="' . esc_url(get_permalink()) . '">' . get_the_title() . '</a>'; ?></h1>
    <?php is_singular() ? the_content() : the_excerpt(); ?>
  </article>
<?php endwhile; the_posts_pagination(); else : ?>
  <p>Nothing found.</p>
<?php endif; ?>
</main>
<?php get_footer();
