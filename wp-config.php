<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );
define('WP_HOME', 'https://worthbuy.com.au');
define('WP_SITEURL', 'https://worthbuy.com.au');
define('FORCE_SSL_ADMIN', false);
$_SERVER['HTTPS'] = 'off';

/** Database username */
define( 'DB_USER', 'wordpressuser' );

/** Database password */
define( 'DB_PASSWORD', 'bobby2003SONG' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

define( 'WP_ALLOW_MULTISITE', true );

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
define( 'AUTH_KEY',         '+B4u>-yZEM!bF!<^W$t+n3?J$s1fSPD3T84TI(j4z-?giZmiT&TNzB$[l,,%/yW{' );
define( 'SECURE_AUTH_KEY',  '&]ka<x^Lf{/Qc_n*`<?])ac@TA{gT}h[[V>yF~pIh&.3v|a-b%p]n%!M-z9(tm=)' );
define( 'LOGGED_IN_KEY',    '0Pm*WMS]&YYpPi/J?jdx|x6(5QGAGe,?Bu}pC.kE9/DgtF$?{K!=ZRRlbrklRbJG' );
define( 'NONCE_KEY',        'O~exM;BWg8O(~_0/^ 5g^]M|V#}T&{%Tj8OyP~#r+|r{<!ij#/]v=^l6fVZh?<X`' );
define( 'AUTH_SALT',        '}]8f^FzF<F@w-yRzfrT.xW$giL){]`A^KerG.$c#<QVw~?zQ#+(9_&X?=r4dv<bW' );
define( 'SECURE_AUTH_SALT', 'fQHehb8}2:=}-J+_WL^mhH}kAXa+!EJ~Ri$BMFE= CiHRXODcnu`u6AfgFTJ9-6N' );
define( 'LOGGED_IN_SALT',   'QdIaISyeew]1YRlq:[3bmvXRp-,g&yS~b&GRF|)8J,ldqE6UPYM`R>T2l@JN9p5[' );
define( 'NONCE_SALT',       '=?^)2  wugr,P)v@x~]R{9G1f|<#a.`gAZM!=FLwuaJOU24/3h7Za(O#6;tfT/$[' );

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

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
 * @link https://wordpress.org/documentation/article/debugging-in-wordpress/
 */
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);


/* Add any custom values between this line and the "stop editing" line. */

define('FORCE_SSL_ADMIN', true);
if ($_SERVER['HTTP_X_FORWARDED_PROTO'] == 'https') {
    $_SERVER['HTTPS'] = 'on';
}


/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
