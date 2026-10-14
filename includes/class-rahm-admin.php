<?php
/**
 * WordPress dashboard: Connect AI, Expert scores and Settings. Nothing else.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAHM_Admin {

	const SLUG = 'rahm-connect';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_post_rahm_keys', array( __CLASS__, 'keys_action' ) );
		add_action( 'admin_post_rahm_settings', array( __CLASS__, 'settings_action' ) );
		add_action( 'admin_post_rahm_measure', array( __CLASS__, 'measure_action' ) );
		add_action( 'admin_post_rahm_selftest', array( __CLASS__, 'selftest_action' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( RAHM_FILE ), function ( $l ) {
			array_unshift( $l, '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::SLUG ) ) . '">' . esc_html( RAHM_I18n::t( 'Connect AI' ) ) . '</a>' );
			return $l;
		} );
	}

	public static function menu() {
		add_menu_page( 'Rashid Ahm MCP', 'Rashid Ahm MCP', 'manage_options', self::SLUG, array( __CLASS__, 'page_connect' ), 'dashicons-groups', 3 );
		$subs = array(
			self::SLUG      => array( 'Connect AI', 'page_connect' ),
			'rahm-scores'   => array( 'Expert scores', 'page_scores' ),
			'rahm-settings' => array( 'Settings', 'page_settings' ),
		);
		foreach ( $subs as $slug => $s ) {
			add_submenu_page( self::SLUG, 'Rashid Ahm MCP', RAHM_I18n::t( $s[0] ), 'manage_options', $slug, array( __CLASS__, $s[1] ) );
		}
	}

	public static function assets( $hook ) {
		if ( strpos( $hook, 'rahm-' ) === false ) {
			return;
		}
		wp_enqueue_style( 'rahm-admin', RAHM_URL . 'assets/admin.css', array(), RAHM_VERSION );
		wp_enqueue_script( 'rahm-admin', RAHM_URL . 'assets/admin.js', array(), RAHM_VERSION, true );
	}

	/* ---------- Shared bits ---------- */

	private static function t( $s ) {
		return RAHM_I18n::t( $s );
	}

	private static function open( $title, $sub = '' ) {
		$dir = RAHM_I18n::rtl() ? ' dir="rtl"' : '';
		echo '<div class="wrap rahm"' . $dir . '><div class="rahm-head"><div class="rahm-brand"><span class="rahm-mark" aria-hidden="true"><svg viewBox="0 0 24 24" width="22" height="22"><path d="M4 18V6l8 7 8-7v12" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg></span><div><h1>' . esc_html( $title ) . '</h1>' . ( $sub ? '<p>' . esc_html( $sub ) . '</p>' : '' ) . '</div></div>';
		echo '<nav class="rahm-nav" aria-label="Rashid Ahm MCP">';
		$items = array( self::SLUG => 'Connect AI', 'rahm-scores' => 'Expert scores', 'rahm-settings' => 'Settings' );
		$cur   = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : ''; // phpcs:ignore
		foreach ( $items as $slug => $label ) {
			echo '<a class="' . ( $cur === $slug ? 'is-on' : '' ) . '" href="' . esc_url( admin_url( 'admin.php?page=' . $slug ) ) . '">' . esc_html( self::t( $label ) ) . '</a>';
		}
		echo '</nav></div>';
	}

	private static function close() {
		echo '<p class="rahm-foot">' . esc_html( self::t( 'Made from scratch by Rashid Ahmad.' ) ) . ' ' . esc_html( self::t( 'Need help?' ) ) . ' <a href="mailto:' . esc_attr( RAHM_CONTACT ) . '">' . esc_html( RAHM_CONTACT ) . '</a> &middot; v' . esc_html( RAHM_VERSION ) . '</p></div>';
	}

	private static function notice() {
		if ( ! empty( $_GET['rahm_msg'] ) ) { // phpcs:ignore
			echo '<div class="rahm-note" role="status">' . esc_html( wp_unslash( $_GET['rahm_msg'] ) ) . '</div>'; // phpcs:ignore
		}
	}

	private static function ago( $t ) {
		return esc_html( human_time_diff( $t, time() ) ) . ' ' . esc_html( self::t( 'ago' ) );
	}

	/* ---------- Connect AI ---------- */

	public static function page_connect() {
		self::open( self::t( 'Connect AI' ), self::t( 'Create a key for each AI account. Each key has a role and an expiry.' ) );
		self::notice();
		$new = get_transient( 'rahm_new_keys_' . get_current_user_id() );
		if ( $new ) {
			delete_transient( 'rahm_new_keys_' . get_current_user_id() );
			echo '<div class="rahm-card rahm-new"><h2>' . esc_html( self::t( 'Your new keys' ) ) . '</h2><p class="rahm-warn">' . esc_html( self::t( 'Copy each link now. For safety it is shown only once. Paste the link into the AI app as a remote MCP server, then paste the starting command into the chat.' ) ) . '</p>';
			foreach ( $new as $n ) {
				echo '<div class="rahm-key"><div class="rahm-key-top"><strong>' . esc_html( $n['label'] ) . '</strong><span class="rahm-pill">' . esc_html( $n['roleLabel'] ) . '</span><span class="rahm-pill rahm-pill-soft">' . esc_html( $n['expiryLabel'] ) . '</span></div>';
				echo '<label>' . esc_html( self::t( 'MCP link' ) ) . '</label><div class="rahm-copy"><input type="text" readonly value="' . esc_attr( $n['link'] ) . '"><button type="button" class="button" data-copy>' . esc_html( self::t( 'Copy' ) ) . '</button></div>';
				echo '<label>' . esc_html( self::t( 'Starting command (paste in the AI chat)' ) ) . '</label><div class="rahm-copy rahm-copy-area"><textarea readonly rows="5">' . esc_textarea( $n['starter'] ) . '</textarea><button type="button" class="button" data-copy>' . esc_html( self::t( 'Copy' ) ) . '</button></div></div>';
			}
			echo '</div>';
		}
		$roles = RAHM_Auth::roles();
		$exp   = RAHM_Auth::expiries();
		?>
		<div class="rahm-card">
			<h2><?php echo esc_html( self::t( 'Generate keys' ) ); ?></h2>
			<ol class="rahm-mini">
				<li><?php echo esc_html( self::t( 'Tick the roles you need and press Generate keys.' ) ); ?></li>
				<li><?php echo esc_html( self::t( 'Paste each MCP link into a different AI app or account.' ) ); ?></li>
				<li><?php echo esc_html( self::t( 'Paste the starting command in that AI\'s chat and write your task. Say yes when it asks, and the change goes live.' ) ); ?></li>
			</ol>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'rahm_keys' ); ?>
				<input type="hidden" name="action" value="rahm_keys"><input type="hidden" name="op" value="generate">
				<div class="rahm-roles">
					<?php foreach ( $roles as $slug => $r ) : ?>
						<label class="rahm-role"><input type="checkbox" name="roles[]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( $slug === 'supervisor' ); ?>><span><strong><?php echo esc_html( self::t( $r['label'] ) ); ?></strong><em><?php echo esc_html( self::t( $r['about'] ) ); ?></em></span></label>
					<?php endforeach; ?>
				</div>
				<div class="rahm-formrow">
					<div><label for="rahm-exp"><?php echo esc_html( self::t( 'Key expires after' ) ); ?></label>
					<select name="expiry" id="rahm-exp"><?php foreach ( $exp as $k => $e ) { echo '<option value="' . esc_attr( $k ) . '"' . selected( $k, '30d', false ) . '>' . esc_html( self::t( $e['label'] ) ) . '</option>'; } ?></select></div>
					<div><label for="rahm-name"><?php echo esc_html( self::t( 'Name (optional)' ) ); ?></label>
					<input type="text" name="name" id="rahm-name" placeholder="<?php echo esc_attr( self::t( 'e.g. ChatGPT account 2' ) ); ?>"></div>
				</div>
				<button type="submit" class="button button-primary button-hero"><?php echo esc_html( self::t( 'Generate keys' ) ); ?></button>
			</form>
		</div>
		<div class="rahm-card">
			<h2><?php echo esc_html( self::t( 'Your keys' ) ); ?></h2>
			<?php
			$keys = array_reverse( RAHM_Auth::keys() );
			if ( ! $keys ) {
				echo '<p>' . esc_html( self::t( 'No keys yet. Generate your first key above.' ) ) . '</p>';
			} else {
				echo '<div class="rahm-table-wrap"><table class="rahm-table"><thead><tr><th>' . esc_html( self::t( 'Name' ) ) . '</th><th>' . esc_html( self::t( 'Role' ) ) . '</th><th>' . esc_html( self::t( 'Status' ) ) . '</th><th>' . esc_html( self::t( 'Expires' ) ) . '</th><th>' . esc_html( self::t( 'Last used' ) ) . '</th><th></th></tr></thead><tbody>';
				foreach ( $keys as $k ) {
					$st = RAHM_Auth::status( $k );
					echo '<tr><td>' . esc_html( $k['label'] ) . '</td><td>' . esc_html( isset( $roles[ $k['role'] ] ) ? self::t( $roles[ $k['role'] ]['label'] ) : $k['role'] ) . '</td><td><span class="rahm-st rahm-st-' . esc_attr( $st ) . '">' . esc_html( self::t( ucfirst( $st ) ) ) . '</span></td><td>' . esc_html( wp_date( 'd M Y, H:i', $k['expires'] ) ) . '</td><td>' . ( $k['last_used'] ? self::ago( $k['last_used'] ) : '-' ) . '</td><td class="rahm-actions">';
					$url = function ( $op ) use ( $k ) { return wp_nonce_url( admin_url( 'admin-post.php?action=rahm_keys&op=' . $op . '&id=' . $k['id'] ), 'rahm_keys' ); };
					if ( $st === 'active' ) { echo '<a href="' . esc_url( $url( 'revoke' ) ) . '">' . esc_html( self::t( 'Turn off' ) ) . '</a> '; }
					echo '<a class="rahm-del" href="' . esc_url( $url( 'delete' ) ) . '" onclick="return confirm(\'' . esc_js( self::t( 'Delete this key?' ) ) . '\')">' . esc_html( self::t( 'Delete' ) ) . '</a></td></tr>';
				}
				echo '</tbody></table></div>';
			}
			?>
		</div>
		<div class="rahm-card" id="rahm-health"
			data-url="<?php echo esc_url( rest_url( 'rahm/v1/health' ) ); ?>"
			data-ok="<?php echo esc_attr( self::t( 'Your site\'s MCP address is reachable from this browser. If your AI app still shows an error, the problem is between the AI app and your host, so see the tip below.' ) ); ?>"
			data-403="<?php echo esc_attr( self::t( 'Your site answered 403 (forbidden). A security plugin, firewall or hosting rule is blocking /wp-json/. Ask your host or security plugin to allow /wp-json/rahm/v1/ and try again.' ) ); ?>"
			data-401="<?php echo esc_attr( self::t( 'Your site answered 401 (login required). Something in front of WordPress asks for a login before the REST address can be used. Allow /wp-json/rahm/v1/ without a login.' ) ); ?>"
			data-404="<?php echo esc_attr( self::t( 'Your site answered 404 (not found). Open Settings > Permalinks in WordPress and press Save once, then check again.' ) ); ?>"
			data-other="<?php echo esc_attr( self::t( 'Your site answered with the status' ) ); ?>"
			data-fail="<?php echo esc_attr( self::t( 'The browser could not reach your site\'s REST address. Check the site address in Settings > General.' ) ); ?>"
			data-wait="<?php echo esc_attr( self::t( 'Checking...' ) ); ?>">
			<h2><?php echo esc_html( self::t( 'Connection check' ) ); ?></h2>
			<p id="rahm-health-text" class="rahm-health" aria-live="polite"><?php echo esc_html( self::t( 'Checking...' ) ); ?></p>
			<p class="rahm-meta"><?php echo esc_html( self::t( 'Tip: if your AI app shows "403", the block is usually a firewall or hosting rule that stops requests from outside. Ask your host to allow requests to this address:' ) ); ?> <code><?php echo esc_html( rest_url( 'rahm/v1/' ) ); ?></code></p>
			<div class="rahm-row">
				<button type="button" class="button" id="rahm-health-run"><?php echo esc_html( self::t( 'Check again' ) ); ?></button>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'rahm_selftest' ); ?>
					<input type="hidden" name="action" value="rahm_selftest">
					<button type="submit" class="button"><?php echo esc_html( self::t( 'Run server test' ) ); ?></button>
				</form>
			</div>
		</div>
		<?php
		self::close();
	}

	public static function keys_action() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'rahm_keys' );
		$op  = isset( $_REQUEST['op'] ) ? sanitize_key( $_REQUEST['op'] ) : '';
		$msg = '';
		if ( $op === 'generate' ) {
			$roles  = isset( $_POST['roles'] ) ? array_map( 'sanitize_key', (array) $_POST['roles'] ) : array();
			$expiry = isset( $_POST['expiry'] ) ? sanitize_key( $_POST['expiry'] ) : '30d';
			$name   = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			$all    = RAHM_Auth::roles();
			$exp    = RAHM_Auth::expiries();
			$out    = array();
			foreach ( array_slice( $roles, 0, 12 ) as $r ) {
				if ( ! isset( $all[ $r ] ) ) { continue; }
				list( $token, $rec ) = RAHM_Auth::generate( $r, count( $roles ) === 1 ? $name : '', $expiry, get_current_user_id() );
				$out[] = array( 'label' => $rec['label'], 'roleLabel' => self::t( $all[ $r ]['label'] ), 'expiryLabel' => isset( $exp[ $expiry ] ) ? self::t( $exp[ $expiry ]['label'] ) : '', 'link' => RAHM_Auth::link( $token ), 'starter' => RAHM_Skills::starter( $r ) );
				RAHM_Ctx::set( 'studio' );
				RAHM_Store::log( 'key', 'keys', 'Created a ' . $all[ $r ]['label'] . ' key' );
				RAHM_Ctx::set( 'system' );
			}
			if ( $out ) { set_transient( 'rahm_new_keys_' . get_current_user_id(), $out, 10 * MINUTE_IN_SECONDS ); }
			$msg = $out ? '' : self::t( 'Choose at least one role.' );
		} elseif ( $op === 'revoke' && ! empty( $_GET['id'] ) ) {
			RAHM_Auth::revoke( sanitize_key( $_GET['id'] ) );
			$msg = self::t( 'Key turned off.' );
		} elseif ( $op === 'delete' && ! empty( $_GET['id'] ) ) {
			RAHM_Auth::delete( sanitize_key( $_GET['id'] ) );
			$msg = self::t( 'Key deleted.' );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'rahm_msg' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Calls this site's own REST address from the server and explains the answers in plain words. */
	public static function selftest_action() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'rahm_selftest' );
		$args = array( 'timeout' => 8, 'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36 RahmMCP' );
		$h    = wp_remote_get( rest_url( 'rahm/v1/health' ), $args );
		$m    = wp_remote_post( rest_url( 'rahm/v1/mcp/rahm_selftest00000000000000000000000000000000' ), $args + array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'jsonrpc' => '2.0', 'id' => 1, 'method' => 'ping' ) ) ) );
		$hc   = is_wp_error( $h ) ? 0 : (int) wp_remote_retrieve_response_code( $h );
		$mc   = is_wp_error( $m ) ? 0 : (int) wp_remote_retrieve_response_code( $m );
		$mb   = is_wp_error( $m ) ? '' : wp_remote_retrieve_body( $m );
		$out  = self::t( 'Server test' ) . ': ' . self::t( 'health address' ) . ' ' . ( $hc ? $hc : self::t( 'no answer' ) ) . '; ' . self::t( 'MCP address' ) . ' ' . ( $mc ? $mc : self::t( 'no answer' ) ) . '. ';
		if ( $hc === 200 && $mc === 401 && strpos( $mb, 'rahm_auth' ) !== false ) {
			$out .= self::t( 'Everything the plugin controls is working (a fake key is correctly refused). If your AI app still shows 403, the block is in front of WordPress, in a firewall or hosting rule: ask your host to allow /wp-json/rahm/v1/.' );
		} elseif ( $hc === 403 || $mc === 403 ) {
			$out .= self::t( 'Your server itself gets 403 when calling this address. A firewall, security plugin or hosting rule is blocking /wp-json/. Ask your host to allow /wp-json/rahm/v1/.' );
		} elseif ( ! $hc && ! $mc ) {
			$out .= self::t( 'The server could not call its own address' ) . ( is_wp_error( $h ) ? ' (' . $h->get_error_message() . ')' : '' ) . '. ' . self::t( 'Some hosts block this; it does not stop AI apps from reaching you. Use the browser check above.' );
		} else {
			$out .= self::t( 'Unexpected answer. Send this line to rashid.ahm199@outlook.com.' );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => self::SLUG, 'rahm_msg' => rawurlencode( $out ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------- Expert scores ---------- */

	public static function page_scores() {
		self::open( self::t( 'Expert scores' ), self::t( 'Scores out of 100, measured on your real pages, plus each expert\'s written report.' ) );
		self::notice();
		$a     = RAHM_Store::audits();
		$roles = array( 'seo' => 'SEO', 'aeo' => 'AEO / GEO', 'copywriter' => 'Copywriter', 'designer' => 'Designer', 'performance' => 'Speed', 'marketing' => 'Marketing', 'qa' => 'Quality check' );
		echo '<div class="rahm-grid">';
		foreach ( $roles as $slug => $label ) {
			$r   = isset( $a[ $slug ] ) ? $a[ $slug ] : null;
			$s   = $r ? (int) $r['score'] : null;
			$cls = $s === null ? 'none' : ( $s >= 85 ? 'good' : ( $s >= 65 ? 'ok' : 'bad' ) );
			echo '<div class="rahm-card rahm-score rahm-score-' . esc_attr( $cls ) . '"><div class="rahm-ring" style="--p:' . (int) $s . '"><span>' . ( $s === null ? '-' : (int) $s ) . '</span></div><h3>' . esc_html( self::t( $label ) ) . '</h3>';
			if ( $r ) {
				echo '<p class="rahm-meta">' . self::ago( $r['t'] ) . ' &middot; ' . esc_html( $r['by'] ) . '</p><ul>';
				foreach ( array_slice( (array) $r['top_fixes'], 0, 4 ) as $f ) { echo '<li>' . esc_html( $f ) . '</li>'; }
				echo '</ul>';
			} else {
				echo '<p class="rahm-meta">' . esc_html( self::t( 'Not measured yet.' ) ) . '</p>';
			}
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
			wp_nonce_field( 'rahm_measure' );
			echo '<input type="hidden" name="action" value="rahm_measure"><input type="hidden" name="role" value="' . esc_attr( $slug ) . '"><button type="submit" class="button">' . esc_html( self::t( 'Measure now' ) ) . '</button></form></div>';
		}
		echo '</div>';
		$reports = RAHM_Store::inbox_all();
		echo '<div class="rahm-card"><h2>' . esc_html( self::t( 'Written reports from the team' ) ) . '</h2>';
		if ( ! $reports ) {
			echo '<p>' . esc_html( self::t( 'No reports yet. Ask an expert AI to review the site and post a report.' ) ) . '</p>';
		}
		foreach ( array_slice( $reports, 0, 12 ) as $i ) {
			echo '<div class="rahm-report"><div class="rahm-key-top"><strong>' . esc_html( $i['title'] ? $i['title'] : self::t( 'Report' ) ) . '</strong><span class="rahm-pill">' . esc_html( $i['from'] ) . '</span>' . ( $i['score'] !== null ? '<span class="rahm-pill rahm-pill-soft">' . (int) $i['score'] . '/100</span>' : '' ) . '</div><div class="rahm-body">' . wp_kses_post( wpautop( $i['body'] ) ) . '</div></div>';
		}
		echo '</div>';
		self::close();
	}

	public static function measure_action() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'rahm_measure' );
		$role = isset( $_POST['role'] ) ? sanitize_key( $_POST['role'] ) : 'qa';
		@set_time_limit( 90 ); // phpcs:ignore
		RAHM_Ctx::set( 'studio' );
		try {
			$r = RAHM_Audit::run( $role, 5 );
			RAHM_Store::save_audit( $r['role'], $r );
			$msg = self::t( 'Measured.' ) . ' ' . $r['score'] . '/100';
		} catch ( Throwable $e ) {
			$msg = $e->getMessage();
		}
		RAHM_Ctx::set( 'system' );
		wp_safe_redirect( add_query_arg( array( 'page' => 'rahm-scores', 'rahm_msg' => rawurlencode( $msg ) ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/* ---------- Settings ---------- */

	public static function page_settings() {
		self::open( self::t( 'Settings' ), self::t( 'Language, safety switches and what the AI should know about your brand.' ) );
		self::notice();
		$s = RAHM_Store::settings();
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="rahm-form">
			<?php wp_nonce_field( 'rahm_settings' ); ?>
			<input type="hidden" name="action" value="rahm_settings">
			<div class="rahm-card">
				<h2><?php echo esc_html( self::t( 'Language' ) ); ?></h2>
				<p><label class="rahm-inline"><input type="radio" name="lang" value="en" <?php checked( $s['lang'], 'en' ); ?>> English</label> &nbsp; <label class="rahm-inline"><input type="radio" name="lang" value="ur" <?php checked( $s['lang'], 'ur' ); ?>> اردو</label></p>
			</div>
			<div class="rahm-card">
				<h2><?php echo esc_html( self::t( 'Going live' ) ); ?></h2>
				<p class="rahm-lead"><?php echo esc_html( self::t( 'Every change is backed up first and can be undone for 30 days (ask the AI: "undo the last change").' ) ); ?></p>
				<label class="rahm-check"><input type="checkbox" name="ask" value="1" <?php checked( $s['ask'] ); ?>><span><strong><?php echo esc_html( self::t( 'Ask me in the chat before anything goes live (recommended)' ) ); ?></strong><em><?php echo esc_html( self::t( 'ON: the AI asks, you say yes, and the change goes straight to your site. OFF: the AI pushes changes to your site immediately, without asking.' ) ); ?></em></span></label>
				<label class="rahm-check"><input type="checkbox" name="readonly" value="1" <?php checked( $s['readonly'] ); ?>><span><strong><?php echo esc_html( self::t( 'Read-only mode' ) ); ?></strong><em><?php echo esc_html( self::t( 'AI can look at the site but cannot change anything.' ) ); ?></em></span></label>
				<label class="rahm-check"><input type="checkbox" name="files" value="1" <?php checked( $s['files'] ); ?>><span><strong><?php echo esc_html( self::t( 'Allow theme and plugin file edits' ) ); ?></strong><em><?php echo esc_html( self::t( 'CSS, JavaScript, HTML and other files, with backups.' ) ); ?></em></span></label>
				<label class="rahm-check"><input type="checkbox" name="php" value="1" <?php checked( $s['php'] ); ?>><span><strong><?php echo esc_html( self::t( 'Allow PHP file edits' ) ); ?></strong><em><?php echo esc_html( self::t( 'PHP is checked for errors before it is written.' ) ); ?></em></span></label>
				<label class="rahm-check"><input type="checkbox" name="maintenance" value="1" <?php checked( $s['maintenance'] ); ?>><span><strong><?php echo esc_html( self::t( 'Maintenance page' ) ); ?></strong><em><?php echo esc_html( self::t( 'Visitors who are not logged in see a short notice.' ) ); ?></em></span></label>
				<label for="rahm-mm"><?php echo esc_html( self::t( 'Maintenance message' ) ); ?></label>
				<input type="text" id="rahm-mm" name="maintenance_msg" value="<?php echo esc_attr( $s['maintenance_msg'] ); ?>" class="large-text">
			</div>
			<div class="rahm-card">
				<h2><?php echo esc_html( self::t( 'Brand profile (the AI reads this)' ) ); ?></h2>
				<div class="rahm-two">
					<div><label for="rb"><?php echo esc_html( self::t( 'Brand or business' ) ); ?></label><input type="text" id="rb" name="brand" value="<?php echo esc_attr( $s['brand'] ); ?>"></div>
					<div><label for="rv"><?php echo esc_html( self::t( 'Tone of voice' ) ); ?></label><input type="text" id="rv" name="voice" value="<?php echo esc_attr( $s['voice'] ); ?>" placeholder="formal, warm, simple"></div>
					<div><label for="rl"><?php echo esc_html( self::t( 'Languages' ) ); ?></label><input type="text" id="rl" name="langs" value="<?php echo esc_attr( $s['langs'] ); ?>"></div>
					<div><label for="rc"><?php echo esc_html( self::t( 'Brand colors' ) ); ?></label><input type="text" id="rc" name="colors" value="<?php echo esc_attr( $s['colors'] ); ?>" placeholder="#14213D, #B08D57"></div>
				</div>
				<label for="rr"><?php echo esc_html( self::t( 'Rules the team must follow' ) ); ?></label>
				<textarea id="rr" name="rules" rows="4" class="large-text"><?php echo esc_textarea( $s['rules'] ); ?></textarea>
				<label for="rs"><?php echo esc_html( self::t( 'Your own playbooks (optional). Start each with ## name' ) ); ?></label>
				<textarea id="rs" name="skills" rows="4" class="large-text" dir="ltr"><?php echo esc_textarea( $s['skills'] ); ?></textarea>
			</div>
			<button type="submit" class="button button-primary button-hero"><?php echo esc_html( self::t( 'Save settings' ) ); ?></button>
		</form>
		<?php
		self::close();
	}

	public static function settings_action() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
		check_admin_referer( 'rahm_settings' );
		$in = wp_unslash( $_POST );
		RAHM_Store::save_settings( array(
			'lang'            => isset( $in['lang'] ) && $in['lang'] === 'ur' ? 'ur' : 'en',
			'ask'             => empty( $in['ask'] ) ? 0 : 1,
			'readonly'        => empty( $in['readonly'] ) ? 0 : 1,
			'files'           => empty( $in['files'] ) ? 0 : 1,
			'php'             => empty( $in['php'] ) ? 0 : 1,
			'maintenance'     => empty( $in['maintenance'] ) ? 0 : 1,
			'maintenance_msg' => sanitize_text_field( isset( $in['maintenance_msg'] ) ? $in['maintenance_msg'] : '' ),
			'brand'           => sanitize_text_field( isset( $in['brand'] ) ? $in['brand'] : '' ),
			'voice'           => sanitize_text_field( isset( $in['voice'] ) ? $in['voice'] : '' ),
			'langs'           => sanitize_text_field( isset( $in['langs'] ) ? $in['langs'] : '' ),
			'colors'          => sanitize_text_field( isset( $in['colors'] ) ? $in['colors'] : '' ),
			'rules'           => sanitize_textarea_field( isset( $in['rules'] ) ? $in['rules'] : '' ),
			'skills'          => sanitize_textarea_field( isset( $in['skills'] ) ? $in['skills'] : '' ),
		) );
		wp_safe_redirect( add_query_arg( array( 'page' => 'rahm-settings', 'rahm_msg' => rawurlencode( self::t( 'Settings saved.' ) ) ), admin_url( 'admin.php' ) ) );
		exit;
	}
}
