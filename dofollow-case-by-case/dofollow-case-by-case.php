<?php
/*
Plugin Name: DoFollow Case by Case
Plugin URI: https://apasionados.es/#utm_source=wpadmin&utm_medium=plugin&utm_campaign=wpdofollowplugin
Description: DoFollow Case by Case allows you to selectively apply dofollow to comments and make links in pages or posts nofollow.
Version: 3.6.0
Author: Apasionados, Apasionados del Marketing, NetConsulting
Author URI: https://apasionados.es
Text Domain: dofollow-case-by-case
Domain Path: /i18n/
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const NDF_CAPABILITY = 'manage_options';
const NDF_NONCE_MAIN = 'apa_dofollow_case_by_case_action';
const NDF_NONCE_BULK = 'ndf_bulk_action';

/**
 * Create / update table using dbDelta (safer than raw CREATE TABLE).
 */
function install_table() {
	global $wpdb;

	$table_name      = $wpdb->prefix . 'nodofollow';
	$charset_collate = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// Keep existing column names for backward compatibility, but tighten types + lengths.
	$sql = "CREATE TABLE {$table_name} (
		id mediumint(9) NOT NULL AUTO_INCREMENT,
		id_comment bigint(20) NULL,
		active_dofollow tinyint(1) NOT NULL DEFAULT 0,
		user_email varchar(100) NULL,
		active_dofollow_url_author tinyint(1) NOT NULL DEFAULT 0,
		url varchar(255) NULL,
		opc varchar(20) NULL,
		PRIMARY KEY  (id),
		KEY idx_comment (id_comment),
		KEY idx_email (user_email),
		KEY idx_url (url),
		KEY idx_opc (opc)
	) {$charset_collate};";

	dbDelta( $sql );
}

// -- upload css (admin only)
add_action( 'admin_init', 'upload_css' );
function upload_css() {
	wp_register_style( 'NDF_style', plugins_url( 'css/style.css', __FILE__ ), array(), '1.1', 'all' );
	wp_enqueue_style( 'NDF_style' );
}

// -- Languages
add_action( 'plugins_loaded', 'language_NDF' );
function language_NDF() {
	load_plugin_textdomain( 'dofollow-case-by-case', false, dirname( plugin_basename( __FILE__ ) ) . '/i18n/' );
}

// -- Access plugin settings from PLUGINS / INSTALLED PLUGINS
function ndf_plugin_action_links( $links, $file ) {
	if ( $file === plugin_basename( dirname( __FILE__ ) . '/dofollow-case-by-case.php' ) ) {
		$links[] = '<a href="' . esc_url( admin_url( 'admin.php?page=cont_config_NDF' ) ) . '">' . esc_html__( 'Settings' ) . '</a>';
	}
	return $links;
}
add_filter( 'plugin_action_links', 'ndf_plugin_action_links', 10, 2 );

// --- Config MENU
add_action( 'admin_menu', 'menu_config_NDF' );
function menu_config_NDF() {
	if ( current_user_can( NDF_CAPABILITY ) ) {
		add_menu_page(
			'DoFollow',
			'DoFollow',
			NDF_CAPABILITY,
			'cont_config_NDF',
			'cont_config_NDF',
			plugins_url( 'images/icon.png', __FILE__ )
		);
	}
}

// --- Config SUB-MENU-EMAIL
add_action( 'admin_menu', 'sub_menu_config_NDF_email' );
function sub_menu_config_NDF_email() {
	$textmenu = __( 'Email White List', 'dofollow-case-by-case' );
	add_submenu_page( 'cont_config_NDF', $textmenu, $textmenu, NDF_CAPABILITY, 'cont_config_sub_NDF_email', 'cont_config_sub_NDF_email' );
}

// --- Config SUB-MENU-URL
add_action( 'admin_menu', 'sub_sub_menu_config_NDF_url' );
function sub_sub_menu_config_NDF_url() {
	$textmenu = __( 'URL White List', 'dofollow-case-by-case' );
	add_submenu_page( 'cont_config_NDF', $textmenu, $textmenu, NDF_CAPABILITY, 'cont_config_sub_NDF_url', 'cont_config_sub_NDF_url' );
}

// --- Message helpers (escaped)
function show_message_( $message, $errormsg = false ) {
	$cls = $errormsg ? 'error' : 'updated fade';
	echo '<div id="message" class="' . esc_attr( $cls ) . '"><p><strong>' . esc_html( $message ) . '</strong></p></div>';
}
function show_admin_messages( $mensaje, $bool_error ) {
	show_message_( $mensaje, $bool_error );
}

// -- pagination_limit
function pagination_limit( $page, $opc ) {
	global $wpdb;

	$page = max( 1, absint( $page ) );
	$opc  = sanitize_text_field( (string) $opc );

	// Use COUNT(*) for performance & correctness.
	$num_rows = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}nodofollow WHERE opc = %s",
			$opc
		)
	);

	$rows_per_page = 10;
	$lastpage      = max( 1, (int) ceil( $num_rows / $rows_per_page ) );

	if ( $page > $lastpage ) {
		$page = $lastpage;
	}

	$offset = ( $page - 1 ) * $rows_per_page;
	return $wpdb->prepare( 'LIMIT %d, %d', $offset, $rows_per_page );
}

// --- pagination_href
function pagination_href( $paged, $opc ) {
	global $wpdb;

	$paged = max( 1, absint( $paged ) );
	$opc   = sanitize_text_field( (string) $opc );

	$num_rows = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}nodofollow WHERE opc = %s",
			$opc
		)
	);

	$lastpage = max( 1, (int) ceil( $num_rows / 10 ) );

	$result = '<ul>';
	if ( $lastpage !== 1 && $paged > 1 ) {
		$result .= "<li><a href='" . esc_url( get_pagenum_link( $paged - 1 ) ) . "'>&laquo;</a></li>";
	}

	for ( $j = 1; $j <= $lastpage; $j++ ) {
		if ( $paged === $j ) {
			$result .= "<li><a class='visited'>" . esc_html( (string) $j ) . '</a></li>';
		} else {
			$result .= "<li><a href='" . esc_url( get_pagenum_link( $j ) ) . "'>" . esc_html( (string) $j ) . '</a></li>';
		}
	}

	if ( $lastpage !== $paged ) {
		$result .= "<li><a href='" . esc_url( get_pagenum_link( $paged + 1 ) ) . "'>&raquo;</a></li>";
	}

	$result .= '</ul>';
	return $result;
}

// --- Create whitelist (escaped output)
function listWhiteDofollow( $opc ) {
	global $wpdb;

	$opc = sanitize_text_field( (string) $opc );
	$img_base = plugins_url( '/images/', __FILE__ );

	$page  = isset( $_REQUEST['paged'] ) ? absint( wp_unslash( $_REQUEST['paged'] ) ) : 1;
	$limit = pagination_limit( $page, $opc );

	$aNDF = $wpdb->get_results(
		$wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}nodofollow WHERE opc = %s {$limit}",
			$opc
		)
	);

	$table_user = '';

	switch ( $opc ) {
		case 'email':
			if ( $aNDF ) {
				$table_user .= '<table style="margin-top:20px; margin-left:20px; margin-right:20px; border: 1px solid #c3c3c3">';
				$table_user .= '<tr style="background-color:#F1F1F1; font-size:12px"><td style="padding:15px"></td><td style="padding:15px"><strong>' . esc_html__( 'Email', 'dofollow-case-by-case' ) . '</strong></td><td style="padding:15px; text-align:center"><strong>' . esc_html__( 'Url Author Dofollow', 'dofollow-case-by-case' ) . '</strong></td></tr>';

				foreach ( $aNDF as $aUsuario ) {
					$id = absint( $aUsuario->id );
					$table_user .= '<tr style="font-size:12px">';
					$table_user .= '<td width="1%" style="padding: 10px"><input type="checkbox" name="' . esc_attr( $id . '_action' ) . '" value="1"/></td>';
					$table_user .= '<td width="40%" style="padding: 10px">' . esc_html( (string) $aUsuario->user_email ) . '</td>';

					$icon = ( (int) $aUsuario->active_dofollow_url_author === 1 ) ? 'ok.png' : 'ko.png';
					$table_user .= '<th width="30%" style="padding: 10px"><img src="' . esc_url( $img_base . $icon ) . '" width="20" alt="" /></th>';
					$table_user .= '</tr>';
				}

				$table_user .= '</table><p></p>';
				$table_user .= '<div class="pagination">' . pagination_href( $page, $opc ) . '</div>';
			}
			break;

		case 'url':
			if ( $aNDF ) {
				$table_user .= '<table style="margin-top:20px; margin-left:20px; margin-right:20px; border: 1px solid #c3c3c3">';
				$table_user .= '<tr style="background-color:#F1F1F1; font-size:12px"><td></td><td style="padding:15px"><strong>URL</strong></td></tr>';

				foreach ( $aNDF as $aUrl ) {
					$id = absint( $aUrl->id );
					$table_user .= '<tr style="font-size:12px">';
					$table_user .= '<td width="1%" style="padding: 10px"><input type="checkbox" name="' . esc_attr( $id . '_action' ) . '" value="1"/></td>';
					$table_user .= '<td width="60%" style="padding: 10px">' . esc_html( (string) $aUrl->url ) . '</td></tr>';
				}

				$table_user .= '</table><p></p>';
				$table_user .= '<div class="pagination">' . pagination_href( $page, $opc ) . '</div>';
			}
			break;
	}

	return $table_user;
}

// --- Insert Users (email)
function getEmail( $act ) {
	if ( ! current_user_can( NDF_CAPABILITY ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}

	if ( empty( $_POST['apa_dofollow_case_by_case_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apa_dofollow_case_by_case_nonce'] ) ), NDF_NONCE_MAIN ) ) {
		wp_die( 'Security check failed' );
	}

	global $wpdb;

	$email = isset( $_POST['ndf_email'] ) ? sanitize_email( wp_unslash( $_POST['ndf_email'] ) ) : '';
	$act   = (int) ( $act ? 1 : 0 );

	if ( empty( $email ) || ! is_email( $email ) ) {
		show_admin_messages( __( 'Please enter a valid email address.', 'dofollow-case-by-case' ), true );
		return;
	}

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT user_email FROM {$wpdb->prefix}nodofollow WHERE user_email = %s AND opc = 'email' LIMIT 1",
			$email
		)
	);

	if ( $existing ) {
		show_admin_messages( __( 'This email is already contained in the White List. Please go to Email White List to edit it.', 'dofollow-case-by-case' ), true );
		return;
	}

	$data = array(
		'active_dofollow'            => 1,
		'user_email'                 => $email,
		'opc'                        => 'email',
		'active_dofollow_url_author' => $act,
	);

	$wpdb->insert( $wpdb->prefix . 'nodofollow', $data );
	show_admin_messages( __( 'Email added correctly to the White List', 'dofollow-case-by-case' ), false );
}

// --- Insert URL
function getUrl() {
	if ( ! current_user_can( NDF_CAPABILITY ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}

	if ( empty( $_POST['apa_dofollow_case_by_case_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['apa_dofollow_case_by_case_nonce'] ) ), NDF_NONCE_MAIN ) ) {
		wp_die( 'Security check failed' );
	}

	global $wpdb;

	$ndf_url = isset( $_POST['ndf_url'] ) ? esc_url_raw( wp_unslash( $_POST['ndf_url'] ) ) : '';

	if ( empty( $ndf_url ) || $ndf_url === 'https://' ) {
		show_admin_messages( __( 'Please enter a valid URL.', 'dofollow-case-by-case' ), true );
		return;
	}

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT url FROM {$wpdb->prefix}nodofollow WHERE url = %s AND opc = 'url' LIMIT 1",
			$ndf_url
		)
	);

	if ( $existing ) {
		show_admin_messages( __( 'This URL is already contained in the White List. Please go to URL White List to edit it.', 'dofollow-case-by-case' ), true );
		return;
	}

	$data = array(
		'active_dofollow' => 1,
		'url'             => $ndf_url,
		'opc'             => 'url',
	);

	$wpdb->insert( $wpdb->prefix . 'nodofollow', $data );
	show_admin_messages( __( 'URL added correctly to the URL White List', 'dofollow-case-by-case' ), false );
}

// --- Delete the selected data of the listwhite
function get_delete_list( $aNDFs ) {
	global $wpdb;
	$message = '';

	foreach ( $aNDFs as $aNDF ) {
		$key = absint( $aNDF->id ) . '_action';
		if ( isset( $_POST[ $key ] ) ) {
			$wpdb->delete( $wpdb->prefix . 'nodofollow', array( 'id' => absint( $aNDF->id ) ) );
			$message = __( 'Entry removed correctly', 'dofollow-case-by-case' );
		}
	}

	return $message;
}

// --- Update email URL Author dofollow of the whitelist
function get_update_list( $aNDFs, $active ) {
	global $wpdb;
	$message = '';
	$active  = (int) ( $active ? 1 : 0 );

	foreach ( $aNDFs as $aNDF ) {
		$key = absint( $aNDF->id ) . '_action';
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}

		$data  = array( 'active_dofollow_url_author' => $active );
		$where = array( 'id' => absint( $aNDF->id ) );

		$wpdb->update( $wpdb->prefix . 'nodofollow', $data, $where );
		$message = __( 'Entry updated correctly', 'dofollow-case-by-case' );
	}

	return $message;
}

// --- Comment Clean all words and keep only the first URL
function clearComment( $comment ) {
	preg_match( '%(?:(?:https?|ftp)://)(?:\\S+(?::\\S*)?@|\\d{1,3}(?:\\.\\d{1,3}){3}|(?:(?:[a-z\\d\\x{00a1}-\\x{ffff}]+-?)*[a-z\\d\\x{00a1}-\\x{ffff}]+)(?:\\.(?:[a-z\\d\\x{00a1}-\\x{ffff}]+-?)*[a-z\\d\\x{00a1}-\\x{ffff}]+)*(?:\\.[a-z\\x{00a1}-\\x{ffff}]{2,6}))(?::\\d+)?(?:[^\\s]*)?%iu', (string) $comment, $matches );
	return isset( $matches[0] ) ? trim( $matches[0], '\"' ) : null;
}

// --- main panel - config
function cont_config_NDF() {
	if ( ! current_user_can( NDF_CAPABILITY ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}

	add_action( 'admin_notices', 'show_admin_messages' );

	if ( isset( $_POST['ndf_submit'] ) ) {
		// Email.
		$email = isset( $_POST['ndf_email'] ) ? sanitize_email( wp_unslash( $_POST['ndf_email'] ) ) : '';
		if ( ! empty( $email ) ) {
			$act = isset( $_POST['url_author_ndf'] ) ? 1 : 0;
			getEmail( $act );
		}

		// URL.
		$url = isset( $_POST['ndf_url'] ) ? esc_url_raw( wp_unslash( $_POST['ndf_url'] ) ) : '';
		if ( ! empty( $url ) && $url !== 'https://' ) {
			getUrl();
		}
	}
	?>
	<div id="dofollow-case-by-case" class="wrap">
		<div id="poststuff">
			<div id="dofollow-header">
				<h2><?php esc_html_e( 'DoFollow Configuration', 'dofollow-case-by-case' ); ?></h2>
			</div>
			<div id="left">
				<form action="" method="POST">
					<?php wp_nonce_field( NDF_NONCE_MAIN, 'apa_dofollow_case_by_case_nonce' ); ?>
					<br/>
					<p><?php esc_html_e( 'This plugin allows you to set links in comments to be dofollow instead of nofollow. When editing a comment, now you have the option to remove the rel=\"nofollow\" attributes from the links contained in them.', 'dofollow-case-by-case' ); ?></p>
					<p><?php esc_html_e( 'To make it easier, you can also setup commenters emails whose links in comments should always be dofollow and you can even set their Author URL when commenting to be dofollow.', 'dofollow-case-by-case' ); ?></p>
					<p><?php esc_html_e( 'On the other side you can also define URLs that when contained in a comment are always dofollow, so that you can setup links to your own sites to be always dofollow.', 'dofollow-case-by-case' ); ?></p>

					<div class="postbox">
						<h3 class="hndle"><span><?php esc_html_e( 'Email', 'dofollow-case-by-case' ); ?></span></h3>
						<div class="inside">
							<div class="postboxp">
								<p><?php esc_html_e( 'Removes the \"nofollow\" attribute from the comments made by a certain commenter, identified by his email address. You can also setup that his Author URL when commenting should be dofollow. Adding an email address here adds it to the Email White List. Please edit it there.', 'dofollow-case-by-case' ); ?></p>
								<p><?php esc_html_e( 'Email', 'dofollow-case-by-case' ); ?>: <input type="text" name="ndf_email" id="ndf_email" style="width:300px" value=""/></p>
								<p><i><?php esc_html_e( 'DoFollow Author URL from commenter', 'dofollow-case-by-case' ); ?> <strong>dofollow</strong></i></p>
								<p><?php esc_html_e( 'When checked: Enable', 'dofollow-case-by-case' ); ?> : <input type="checkbox" name="url_author_ndf" id="url_author_ndf" value="1"/></p>
								<p><input type="submit" class="button-primary" id="ndf_submit" name="ndf_submit" value="<?php echo esc_attr__( 'Save', 'dofollow-case-by-case' ); ?>"/></p>
							</div>
						</div>
					</div>

					<div class="postbox">
						<h3 class="hndle"><span>URL</span></h3>
						<div class="inside">
							<div class="postboxp">
								<p><strong><?php esc_html_e( 'Removes the \"nofollow\" attribute from the URLs you setup. Adding a URL here adds it to the URL White List. Please edit it there.', 'dofollow-case-by-case' ); ?></strong></p>
								<p><i><?php esc_html_e( 'You can add links to your own site and also to external sites.', 'dofollow-case-by-case' ); ?></i></p>
								<p>URL:<input type="text" name="ndf_url" id="ndf_url" style="width:300px" value="https://"/></p>
								<p><input type="submit" class="button-primary" id="ndf_submit_2" name="ndf_submit" value="<?php echo esc_attr__( 'Save', 'dofollow-case-by-case' ); ?>"/></p>
							</div>
						</div>
					</div>
				</form>
			</div>
			<div id="right"></div>
		</div>
	</div>
	<?php
}

// --- Bulk nonce/capability validation
function ndf_validate_bulk_request() {
	if ( ! current_user_can( NDF_CAPABILITY ) ) {
		wp_die( 'You do not have sufficient permissions to access this page.' );
	}

	if ( empty( $_POST['ndf_bulk_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ndf_bulk_nonce'] ) ), NDF_NONCE_BULK ) ) {
		wp_die( 'Security check failed' );
	}
}

// --- Main Panel secondary - whitelist EMAIL
function cont_config_sub_NDF_email() {
	global $wpdb;
	add_action( 'admin_notices', 'show_admin_messages' );

	if ( isset( $_POST['ndf_action_submit'] ) ) {
		ndf_validate_bulk_request();

		$acciones = isset( $_POST['acciones'] ) ? absint( wp_unslash( $_POST['acciones'] ) ) : 0;
		$aNDFs    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nodofollow WHERE active_dofollow = %d AND opc = 'email'", 1 ) );

		$message = '';
		switch ( $acciones ) {
			case 0:
			case 1:
				$message = get_update_list( $aNDFs, $acciones );
				break;
			case 2:
				$message = get_delete_list( $aNDFs );
				break;
		}

		if ( $message !== '' ) {
			show_admin_messages( $message, false );
		}
	}
	?>
	<div id="dofollow-case-by-case" class="wrap">
		<div id="poststuff">
			<div id="dofollow-header">
				<h2><?php esc_html_e( 'Setup EMAIL White List', 'dofollow-case-by-case' ); ?></h2>
			</div>
			<div id="left">
				<form action="" method="POST">
					<?php wp_nonce_field( NDF_NONCE_BULK, 'ndf_bulk_nonce' ); ?>
					<br/>
					<p><?php esc_html_e( 'The Email White List contains a list of emails of commenters, whose links in comments are always dofollow.', 'dofollow-case-by-case' ); ?></p>
					<p><?php esc_html_e( 'And you can also choose to make the Author URL dofollow. By default the Author URL is not followed.', 'dofollow-case-by-case' ); ?></p>
					<p><?php esc_html_e( 'Here you can add for example the email addresses of your staff and collaborators.', 'dofollow-case-by-case' ); ?></p>

					<p>
						<select name="acciones" size="1">
							<option value=""><?php esc_html_e( 'Bulk actions', 'dofollow-case-by-case' ); ?></option>
							<option value="0"><?php esc_html_e( 'Disable DoFollow Author URLs', 'dofollow-case-by-case' ); ?></option>
							<option value="1"><?php esc_html_e( 'Enable DoFollow Author URLs', 'dofollow-case-by-case' ); ?></option>
							<option value="2"><?php esc_html_e( 'Remove', 'dofollow-case-by-case' ); ?></option>
						</select>
						<input type="submit" id="ndf_action_submit" name="ndf_action_submit" value="<?php echo esc_attr__( 'Apply', 'dofollow-case-by-case' ); ?>" class="button action" style="margin-left:5px"/>
					</p>

					<div class="postbox">
						<div class="postboxp">
							<div style="padding-bottom:20px;">
								<h3 class="hndle"><?php esc_html_e( 'Email', 'dofollow-case-by-case' ); ?></h3>
								<?php echo listWhiteDofollow( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					</div>
				</form>
			</div>
			<div id="right"></div>
		</div>
	</div>
	<?php
}

// --- Main Panel secondary - whitelist URL
function cont_config_sub_NDF_url() {
	global $wpdb;
	add_action( 'admin_notices', 'show_admin_messages' );

	if ( isset( $_POST['ndf_action_submit'] ) ) {
		ndf_validate_bulk_request();

		$acciones = isset( $_POST['acciones'] ) ? absint( wp_unslash( $_POST['acciones'] ) ) : 0;
		$aNDFs    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}nodofollow WHERE active_dofollow = %d AND opc = 'url'", 1 ) );

		$message = '';
		if ( $acciones === 3 ) {
			$message = get_delete_list( $aNDFs );
		}

		if ( $message !== '' ) {
			show_admin_messages( $message, false );
		}
	}
	?>
	<div id="dofollow-case-by-case" class="wrap">
		<div id="poststuff">
			<div id="dofollow-header">
				<h2><?php esc_html_e( 'Setup URL White List', 'dofollow-case-by-case' ); ?></h2>
			</div>
			<div id="left">
				<form action="" method="POST">
					<?php wp_nonce_field( NDF_NONCE_BULK, 'ndf_bulk_nonce' ); ?>
					<br/>
					<p><?php esc_html_e( 'The URL White List contains a list of URLs that when linked to in a comment, are always dofollow, nevertheless who links to them.', 'dofollow-case-by-case' ); ?></p>
					<p><?php esc_html_e( 'Here you can setup for example links from your sites or from other sites.', 'dofollow-case-by-case' ); ?></p>

					<p>
						<select name="acciones" size="1">
							<option value="0"><?php esc_html_e( 'Bulk actions', 'dofollow-case-by-case' ); ?></option>
							<option value="3"><?php esc_html_e( 'Remove', 'dofollow-case-by-case' ); ?></option>
						</select>
						<input type="submit" id="ndf_action_submit" name="ndf_action_submit" value="<?php echo esc_attr__( 'Apply', 'dofollow-case-by-case' ); ?>" class="button action" style="margin-left:5px"/>
					</p>

					<div class="postbox">
						<div class="postboxp">
							<div style="padding-bottom:20px;">
								<h3 class="hndle">URL</h3>
								<?php echo listWhiteDofollow( 'url' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>
						</div>
					</div>
				</form>
			</div>
			<div id="right"></div>
		</div>
	</div>
	<?php
}

// --- Create box in edit comment
add_action( 'add_meta_boxes', 'create_box' );
function create_box() {
	add_meta_box( 'box_ext_id', __( 'DoFollow Case by Case Options', 'dofollow-case-by-case' ), 'box_inner_custom_box', 'comment', 'normal' );
}

// --- Create checkbox option in edit comment (escaped output)
function box_inner_custom_box() {
	global $wpdb, $comment;

	$img_base = plugins_url( '/images/', __FILE__ );

	$comment_array = get_comment( $comment->comment_ID, ARRAY_A );
	$comment_id    = absint( $comment_array['comment_ID'] );

	$aCommentNDF = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT active_dofollow FROM {$wpdb->prefix}nodofollow WHERE id_comment = %d",
			$comment_id
		),
		ARRAY_A
	);

	echo '<br /><label><strong>' . esc_html__( 'Change all comment links to DoFollow', 'dofollow-case-by-case' ) . '</strong></label> <br /><br />';
	echo '<input type="checkbox" name="nofollow_text" value="1" style="margin-left:20px" ';

	if ( ! empty( $aCommentNDF['active_dofollow'] ) && (int) $aCommentNDF['active_dofollow'] === 1 ) {
		echo 'checked="checked"';
	}
	echo ' /> <span style="padding-left: 10px">dofollow</span><br /><br />';

	$aAuthorNDF = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT user_email, active_dofollow_url_author FROM {$wpdb->prefix}nodofollow WHERE user_email = %s AND opc = 'email' LIMIT 1",
			(string) $comment_array['comment_author_email']
		),
		ARRAY_A
	);

	echo '<p><strong>' . esc_html__( 'User contained in the DoFollow White List', 'dofollow-case-by-case' ) . '</strong></p>';
	echo '<table width="50%" style="border: 1px solid #c3c3c3">';
	echo '<tr style="background-color:#F1F1F1; font-size:12px"><td style="padding: 10px" >' . esc_html__( 'Email', 'dofollow-case-by-case' ) . '</td><td style="padding: 10px">' . esc_html__( 'Url Author Dofollow', 'dofollow-case-by-case' ) . '</td></tr>';

	if ( ! empty( $aAuthorNDF['user_email'] ) ) {
		echo '<tr><td style="padding: 10px">' . esc_html( (string) $aAuthorNDF['user_email'] ) . '</td>';
		$icon = ( ! empty( $aAuthorNDF['active_dofollow_url_author'] ) && (int) $aAuthorNDF['active_dofollow_url_author'] === 1 ) ? 'ok.png' : 'ko.png';
		echo '<td style="padding: 10px; text-align:center"><img src="' . esc_url( $img_base . $icon ) . '" width="20" alt="" /></td>';
	} else {
		echo '<tr><td colspan="2" style="padding: 10px; text-align:center">' . esc_html__( 'This user is not in the white list.', 'dofollow-case-by-case' ) . '</td>';
	}

	echo '</tr></table>';
}

// --- Update options in edit comment (capability + nonce + sanitization)
add_filter( 'edit_comment', 'update_comment', 17 );
function update_comment( $comment_content ) {
	global $wpdb;

	if ( empty( $_REQUEST['c'] ) ) {
		return;
	}

	$comment_id = absint( wp_unslash( $_REQUEST['c'] ) );
	if ( $comment_id < 1 ) {
		return;
	}

	if ( ! current_user_can( 'edit_comment', $comment_id ) ) {
		return;
	}

	// WordPress core uses update-comment_{ID}.
	if ( empty( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'update-comment_' . $comment_id ) ) {
		return;
	}

	$checked = isset( $_POST['nofollow_text'] ) ? 1 : 0;

	$existing = $wpdb->get_var(
		$wpdb->prepare(
			"SELECT id_comment FROM {$wpdb->prefix}nodofollow WHERE id_comment = %d LIMIT 1",
			$comment_id
		)
	);

	if ( $existing ) {
		$wpdb->update(
			$wpdb->prefix . 'nodofollow',
			array(
				'id_comment'      => $comment_id,
				'active_dofollow' => $checked,
			),
			array( 'id_comment' => $comment_id ),
			array( '%d', '%d' ),
			array( '%d' )
		);
	} else {
		$wpdb->insert(
			$wpdb->prefix . 'nodofollow',
			array(
				'id_comment'      => $comment_id,
				'active_dofollow' => $checked,
			),
			array( '%d', '%d' )
		);
	}
}

// --- Update rel url nofollow - dofollow for Author URL (escaped + noopener)
add_filter( 'get_comment_author_link', 'remove_DoFollowAuthor', 11 );
function remove_DofollowAuthor( $commentAuthor ) {
	global $comment, $wpdb;

	$comment_array = get_comment( $comment->comment_ID, ARRAY_A );

	$url    = get_comment_author_url();
	$author = get_comment_author();

	$aAuthorEmailNDF = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT active_dofollow, active_dofollow_url_author FROM {$wpdb->prefix}nodofollow WHERE user_email = %s AND active_dofollow_url_author = 1 AND opc = 'email' LIMIT 1",
			(string) $comment_array['comment_author_email']
		),
		ARRAY_A
	);

	$has_author_dofollow = ( ! empty( $aAuthorEmailNDF ) && ! empty( $aAuthorEmailNDF['active_dofollow'] ) && (int) $aAuthorEmailNDF['active_dofollow'] === 1 );

	if ( empty( $url ) || 'https://' === $url ) {
		return esc_html( $author );
	}

	$rel = $has_author_dofollow ? 'external noopener noreferrer' : 'external nofollow noopener noreferrer';

	return sprintf(
		'<a href="%s" rel="%s" target="_blank">%s</a>',
		esc_url( $url ),
		esc_attr( $rel ),
		esc_html( $author )
	);
}

// --- Helper: add target/rel safely to links
function ndf_harden_comment_links( $html ) {
	if ( ! is_string( $html ) || $html === '' ) {
		return $html;
	}

	return preg_replace_callback( '/<a\s+[^>]*>/i', function ( $m ) {
		$tag = $m[0];

		// Ensure target=_blank
		if ( stripos( $tag, 'target=' ) === false ) {
			$tag = rtrim( $tag, '>' ) . ' target="_blank">';
		}

		// Ensure rel contains noopener noreferrer. Preserve existing rel if present.
		if ( preg_match( '/\srel=("|\')([^"\']*)(\1)/i', $tag, $rm ) ) {
			$existing = $rm[2];
			$tokens   = preg_split( '/\s+/', trim( $existing ) );
			foreach ( array( 'noopener', 'noreferrer' ) as $t ) {
				if ( ! in_array( $t, $tokens, true ) ) {
					$tokens[] = $t;
				}
			}
			$new_rel = implode( ' ', array_filter( $tokens ) );
			$tag     = preg_replace(
				'/\srel=("|\')([^"\']*)(\1)/i',
				' rel="$new_rel"',
				$tag,
				1
			);
		} else {
			$tag = rtrim( $tag, '>' ) . ' rel="external noopener noreferrer">';
		}

		return $tag;
	}, $html );
}

// --- Update rel url nofollow - dofollow in execution time of comments
add_filter( 'get_comment_text', 'remove_DoFollowComment' );
function remove_DoFollowComment( $c ) {
	global $comment, $wpdb;

	$comment_array = get_comment( $comment->comment_ID, ARRAY_A );

	$aAuthorEmailNDF = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT active_dofollow FROM {$wpdb->prefix}nodofollow WHERE user_email = %s AND active_dofollow = 1 AND opc = 'email' LIMIT 1",
			(string) $comment_array['comment_author_email']
		),
		ARRAY_A
	);

	$aCommentNDF = $wpdb->get_row(
		$wpdb->prepare(
			"SELECT id_comment, active_dofollow FROM {$wpdb->prefix}nodofollow WHERE id_comment = %d LIMIT 1",
			absint( $comment_array['comment_ID'] )
		),
		ARRAY_A
	);

	$url    = clearComment( $c );
	$aUrlNDF = null;
	if ( $url ) {
		$aUrlNDF = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT active_dofollow FROM {$wpdb->prefix}nodofollow WHERE url = %s AND opc = 'url' LIMIT 1",
				$url
			),
			ARRAY_A
		);
	}

	$should_dofollow = false;

	if ( ! empty( $aAuthorEmailNDF ) && ! empty( $aAuthorEmailNDF['active_dofollow'] ) && (int) $aAuthorEmailNDF['active_dofollow'] === 1 ) {
		$should_dofollow = true;
	} elseif ( ! empty( $aUrlNDF ) && ! empty( $aUrlNDF['active_dofollow'] ) && (int) $aUrlNDF['active_dofollow'] === 1 ) {
		$should_dofollow = true;
	} elseif ( ! empty( $aCommentNDF ) && (int) $aCommentNDF['id_comment'] === absint( $comment_array['comment_ID'] ) && ! empty( $aCommentNDF['active_dofollow'] ) && (int) $aCommentNDF['active_dofollow'] === 1 ) {
		$should_dofollow = true;
	}

	if ( $should_dofollow ) {
		// Only replace rel token, not arbitrary 'nofollow' text.
		$c = preg_replace( '/\\bnofollow\\b/i', 'external', $c );
	}

	return ndf_harden_comment_links( $c );
}

// Activate Plugin
register_activation_hook( __FILE__, 'NDF_activation' );
function NDF_activation() {
	install_table();
}

// Deactivate plugin
register_deactivation_hook( __FILE__, 'NDF_deactivation' );
function NDF_deactivation() {}

// Uninstall: delete table
register_uninstall_hook( __FILE__, 'NDF_deleteplugin' );
function NDF_deleteplugin() {
	global $wpdb;
	$table = $wpdb->prefix . 'nodofollow';
	$wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}
