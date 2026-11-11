<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'jhcca' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          'vv_+h~paMQl]X+qe~ad3e?=TO3#[x~{*hR~=CmUFlTr|T@BXu@$Y5r^iHTm@IdkS' );
define( 'SECURE_AUTH_KEY',   'D#[:6gtA-MQJdEH}O`eoTBF7%9&T,;BNx{<3R;w8KlHa>2iWrPBb{al!TUY#*T *' );
define( 'LOGGED_IN_KEY',     'a|:f:a`iDyp6sO?]G*4)L7OBQyPQuX[4/_caplh&k6/~<DEf!7[{9ge|)(b|O`}1' );
define( 'NONCE_KEY',         'PU$!W!(W*1[.<GOVf?#.6pdpzuk_H=X?%VG*H2.G)k/qX7ZV,*N]#y,[bMd8}jnF' );
define( 'AUTH_SALT',         'N6S~`Qmib#R*-p; F^m ctiA{*H!_u!p[b0P0Ir6gZVxsHlaN| NL5A+A*?^`.71' );
define( 'SECURE_AUTH_SALT',  '|@hqU,k=K6!B=K@h7|yq5k6bl_&LP!0^JsPT1{F$C:5UutaO/NJi*IOKaDIy|&l2' );
define( 'LOGGED_IN_SALT',    'U$ZM1ASU;saS5(e|Bn^JVOVTJBK>x).3BVq}5+m4]!H5*bR:.r3UCBYpzZr/`ZGJ' );
define( 'NONCE_SALT',        '|J+#evnV}p zxA/ZE`C!VcPD+YT$@`cG4K#~S`e<Ohc2hEPrKPgu$qGzdq()aJ<M' );
define( 'WP_CACHE_KEY_SALT', '0HN/{wn7.*rRFptXL%~-l9C:XzC(:i!%{Zq9]aq*P6tt0.Z>|R]] _zQ2KnZ:a%+' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
