<?php
/**
 * Team tools: SEO, Elementor, QA, audit scores and team notes/reports.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAHM_Tools_Team {

	private static function pp( $a ) {
		return RAHM_Tools::pp( $a );
	}

	private static function act( $a, array $allowed ) {
		return RAHM_Tools::act( $a, $allowed );
	}

	/** draft if the page is not public, live if visitors can see it. */
	private static function level_for( $id ) {
		$s = get_post_status( $id );
		return in_array( $s, array( 'publish', 'future', 'private' ), true ) ? 'live' : 'draft';
	}

	public static function define( $d ) {
		$ap = array( 'action' => 'string', 'p' => 'object|action parameters', 'confirm' => 'boolean|true only after the owner said yes' );

		/* ---------- SEO ---------- */
		$d( 'seo', 'All on-site SEO. Actions: get{id} | set{id,title,description,focus_keyword,canonical,og_image,noindex} or {items:[...]} | audit{ids|id|all,limit,page} | head{id|url} (what visitors see in <head>) | links{id} | linkmap{} (internal links, orphan pages) | terms{taxonomy,op:list|create|rename|delete,...} | permalinks{structure?} | faq{id,items:[{q,a}],heading} adds an FAQ block + FAQPage schema (3-12 questions) | schema{id,json} sets JSON-LD structured data (Organization, LocalBusiness, Article, Product, ...) or reads it.', $ap, function ( $a ) {
			$p = self::pp( $a );
			switch ( self::act( $a, array( 'get', 'set', 'audit', 'head', 'links', 'linkmap', 'terms', 'permalinks', 'faq', 'schema' ) ) ) {
				case 'get':
					return RAHM_SEO::read( (int) $p['id'] );
				case 'set':
					$items = isset( $p['items'] ) && is_array( $p['items'] ) ? array_slice( $p['items'], 0, 30 ) : array( $p );
					$live  = false;
					foreach ( $items as $x ) {
						if ( self::level_for( (int) ( isset( $x['id'] ) ? $x['id'] : 0 ) ) === 'live' ) { $live = true; }
					}
					RAHM_Ctx::gate( $live ? 'live' : 'draft', 'change SEO fields on ' . count( $items ) . ' page(s)' );
					$out = array();
					foreach ( $items as $x ) {
						$id = (int) ( isset( $x['id'] ) ? $x['id'] : 0 );
						if ( ! get_post( $id ) ) { $out[] = array( 'id' => $id, 'error' => 'Post not found' ); continue; }
						$used  = RAHM_SEO::write( $id, $x );
						$out[] = array( 'id' => $id, 'updated_in' => $used, 'undo_id' => RAHM_Ctx::$undo );
						RAHM_Ctx::done( 'seo', 'Updated SEO for "' . get_the_title( $id ) . '"', $id );
					}
					return $out;
				case 'audit':
					$limit = min( 40, max( 1, (int) ( isset( $p['limit'] ) ? $p['limit'] : 20 ) ) );
					if ( isset( $p['ids'] ) || isset( $p['id'] ) ) {
						$ids = isset( $p['ids'] ) ? (array) $p['ids'] : array( $p['id'] );
					} else {
						$ids = get_posts( array( 'post_type' => array( 'page', 'post', 'product' ), 'post_status' => 'publish', 'numberposts' => $limit, 'paged' => max( 1, (int) ( isset( $p['page'] ) ? $p['page'] : 1 ) ), 'fields' => 'ids', 'orderby' => 'menu_order date' ) );
					}
					return array_map( function ( $id ) {
						$post = get_post( (int) $id );
						if ( ! $post ) { return array( 'id' => $id, 'error' => 'Post not found' ); }
						$i = RAHM_SEO::check_post( $post );
						return array( 'id' => $post->ID, 'title' => $post->post_title, 'url' => get_permalink( $post ), 'score' => RAHM_SEO::score( $i ), 'issues' => $i ? array_column( $i, 1 ) : array( 'OK' ) );
					}, array_slice( $ids, 0, $limit ) );
				case 'head':
					$url = isset( $p['url'] ) ? $p['url'] : get_permalink( (int) ( isset( $p['id'] ) ? $p['id'] : 0 ) );
					return RAHM_Audit::head_tags( $url );
				case 'links':
					$post = get_post( (int) $p['id'] );
					if ( ! $post ) { throw new Exception( 'Post not found.' ); }
					$l = RAHM_SEO::links( $post->post_content . ' ' . ( RAHM_Direct::is_elementor( $post->ID ) ? wp_json_encode( RAHM_Direct::el_get( $post->ID ) ) : '' ) );
					return array( 'internal' => array_slice( $l['internal'], 0, 60 ), 'external' => array_slice( $l['external'], 0, 60 ), 'tip' => 'External links should usually carry rel="noopener" (and nofollow for paid or untrusted links).' );
				case 'linkmap':
					return RAHM_Audit::link_map();
				case 'terms':
					return self::terms( $p );
				case 'faq':
					return self::faq( $p );
				case 'schema':
					return self::schema( $p );
				case 'permalinks':
					if ( ! isset( $p['structure'] ) ) { return array( 'structure' => get_option( 'permalink_structure' ), 'category_base' => get_option( 'category_base' ), 'tag_base' => get_option( 'tag_base' ) ); }
					RAHM_Ctx::gate( 'danger', 'change the permalink structure to ' . $p['structure'] . ' (every page address may change; add redirects if the site is already indexed)' );
					RAHM_Ctx::$undo = RAHM_Store::snap( 'opt', 'permalink_structure', get_option( 'permalink_structure', RAHM_Store::NONE ) );
					update_option( 'permalink_structure', sanitize_text_field( $p['structure'] ) );
					flush_rewrite_rules( false );
					RAHM_Ctx::done( 'seo', 'Changed permalinks to ' . $p['structure'] );
					return array( 'ok' => true, 'undo_id' => RAHM_Ctx::$undo );
			}
		} );

		/* ---------- Elementor ---------- */
		$d( 'elementor', 'Elementor pages. Actions: get{id,depth} (tree with element ids, types, text, images) | set{id,element_id,settings{}} | add{id,after_id|parent_id,element{elType,widgetType,settings,elements}} | remove{id,element_id} | save{id,data} replaces all. All changes are backed up and undoable.', $ap, function ( $a ) {
			$p  = self::pp( $a );
			$id = (int) ( isset( $p['id'] ) ? $p['id'] : 0 );
			if ( ! get_post( $id ) ) { throw new Exception( 'Page not found.' ); }
			$act = self::act( $a, array( 'get', 'set', 'add', 'remove', 'save' ) );
			if ( $act === 'get' ) {
				if ( ! RAHM_Direct::is_elementor( $id ) ) { return array( 'builder' => 'not elementor', 'hint' => 'Use the content tool for this page.' ); }
				return array( 'id' => $id, 'tree' => RAHM_Direct::summarize( RAHM_Direct::el_get( $id ), 0, max( 1, min( 8, (int) ( isset( $p['depth'] ) ? $p['depth'] : 6 ) ) ) ) );
			}
			RAHM_Ctx::gate( self::level_for( $id ), 'change the Elementor layout of page #' . $id . ' (' . $act . ')' );
			$tree  = RAHM_Direct::el_get( $id );
			$found = false;
			switch ( $act ) {
				case 'set':
					$set  = isset( $p['settings'] ) && is_array( $p['settings'] ) ? $p['settings'] : array();
					$tree = RAHM_Direct::map_tree( $tree, $p['element_id'], function ( $el ) use ( $set ) {
						$el['settings'] = array_merge( isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array(), $set );
						return $el;
					}, $found );
					if ( ! $found ) { throw new Exception( 'Element not found.' ); }
					break;
				case 'remove':
					$tree = RAHM_Direct::map_tree( $tree, $p['element_id'], function () { return null; }, $found );
					if ( ! $found ) { throw new Exception( 'Element not found.' ); }
					break;
				case 'add':
					$el = self::prepare_element( isset( $p['element'] ) ? $p['element'] : array() );
					if ( ! empty( $p['after_id'] ) ) {
						$tree = RAHM_Direct::map_tree( $tree, $p['after_id'], function ( $x ) use ( $el ) { return array( '_list' => array( $x, $el ) ); }, $found );
					} elseif ( ! empty( $p['parent_id'] ) ) {
						$tree = RAHM_Direct::map_tree( $tree, $p['parent_id'], function ( $x ) use ( $el ) { $x['elements'] = array_merge( isset( $x['elements'] ) ? $x['elements'] : array(), array( $el ) ); return $x; }, $found );
					} else {
						$tree[] = $el;
						$found  = true;
					}
					if ( ! $found ) { throw new Exception( 'after_id / parent_id not found.' ); }
					break;
				case 'save':
					$data = isset( $p['data'] ) ? $p['data'] : null;
					if ( is_string( $data ) ) { $data = json_decode( $data, true ); }
					if ( ! is_array( $data ) ) { throw new Exception( 'data must be an Elementor element array (or its JSON).' ); }
					$tree = array_map( array( __CLASS__, 'prepare_element' ), $data );
					break;
			}
			$undo = RAHM_Direct::el_save( $id, $tree, 'Elementor ' . $act );
			RAHM_Ctx::done( 'elementor', 'Elementor ' . $act . ' on "' . get_the_title( $id ) . '"', $id, $undo );
			return array( 'ok' => true, 'undo_id' => $undo );
		} );

		/* ---------- QA ---------- */
		$d( 'qa', 'Open pages like a visitor and check status, load time, size, title, meta description, viewport, lang, H1, images without alt, noindex, PHP errors, mixed content, scripts. {urls:[...]} or {post_ids:[...]} (max 5), mobile:true for a phone check.', array( 'urls' => 'array', 'post_ids' => 'array', 'mobile' => 'boolean' ), function ( $a ) {
			$urls = array();
			foreach ( (array) ( isset( $a['post_ids'] ) ? $a['post_ids'] : array() ) as $id ) { $urls[] = get_permalink( (int) $id ); }
			foreach ( (array) ( isset( $a['urls'] ) ? $a['urls'] : array() ) as $u ) { $urls[] = $u; }
			if ( ! $urls ) { $urls[] = home_url( '/' ); }
			$mobile = ! empty( $a['mobile'] );
			return array_map( function ( $u ) use ( $mobile ) {
				$r = RAHM_Audit::analyze( $u, $mobile );
				return isset( $r['error'] ) ? $r : array( 'url' => $r['url'], 'status' => $r['status'], 'ms' => $r['ms'], 'kb' => $r['kb'], 'device' => $mobile ? 'mobile' : 'laptop', 'issues' => $r['issues'] ? array_column( $r['issues'], 1 ) : array( 'OK' ) );
			}, array_slice( array_filter( $urls ), 0, 5 ) );
		} );

		/* ---------- Audit scores ---------- */
		$d( 'audit', 'Expert score out of 100 from real measurements. Actions: run{role:seo|aeo|copywriter|designer|performance|marketing|qa,limit} | get (latest scores of all roles). Add your own written opinion with inbox post type=report and a score.', $ap, function ( $a ) {
			$p = self::pp( $a );
			switch ( self::act( $a, array( 'run', 'get' ) ) ) {
				case 'run':
					$r = RAHM_Audit::run( isset( $p['role'] ) ? $p['role'] : 'qa', (int) ( isset( $p['limit'] ) ? $p['limit'] : 6 ) );
					RAHM_Store::save_audit( $r['role'], $r );
					RAHM_Store::log( 'read', 'audit', 'Audit ' . $r['role'] . ': ' . $r['score'] . '/100' );
					return $r;
				case 'get':
					return RAHM_Store::audits();
			}
		} );

		/* ---------- Team notes and reports ---------- */
		$d( 'inbox', 'Leave notes and written reports for the owner (shown in the dashboard). Actions: post{type:report|note,title,body,score} | list{type,limit} | get{id}. Plans and approvals happen in the chat with the owner, not here.', $ap, function ( $a ) {
			$p = self::pp( $a );
			switch ( self::act( $a, array( 'post', 'list', 'get' ) ) ) {
				case 'post':
					$type = ( isset( $p['type'] ) ? $p['type'] : '' ) === 'report' ? 'report' : 'note';
					$item = RAHM_Store::inbox_add( array( 'type' => $type, 'title' => sanitize_text_field( isset( $p['title'] ) ? $p['title'] : '' ), 'body' => wp_kses_post( isset( $p['body'] ) ? $p['body'] : '' ), 'score' => isset( $p['score'] ) ? max( 0, min( 100, (int) $p['score'] ) ) : null ) );
					RAHM_Store::log( 'read', 'inbox', ucfirst( $type ) . ' from ' . RAHM_Ctx::who() . ': ' . $item['title'] );
					return array( 'id' => $item['id'], 'note' => 'Saved. The owner can read it in Rashid Ahm MCP > Expert scores.' );
				case 'list':
					$o = array();
					foreach ( RAHM_Store::inbox_all() as $i ) {
						if ( ! empty( $p['type'] ) && $i['type'] !== $p['type'] ) { continue; }
						$o[] = array( 'id' => $i['id'], 'type' => $i['type'], 'from' => $i['from'], 'role' => $i['role'], 'title' => $i['title'], 'score' => $i['score'], 'when' => wp_date( 'm-d H:i', $i['t'] ) );
						if ( count( $o ) >= min( 50, (int) ( isset( $p['limit'] ) ? $p['limit'] : 20 ) ) ) { break; }
					}
					return $o;
				case 'get':
					$i = RAHM_Store::inbox_get( (int) $p['id'] );
					if ( ! $i ) { throw new Exception( 'Not found.' ); }
					return $i;
			}
		} );
	}

	/* ---------- helpers ---------- */

	private static function prepare_element( $el ) {
		if ( ! is_array( $el ) || empty( $el['elType'] ) ) { throw new Exception( 'Each Elementor element needs an elType (container, section, column or widget).' ); }
		if ( $el['elType'] === 'widget' && empty( $el['widgetType'] ) ) { throw new Exception( 'A widget needs a widgetType (heading, text-editor, image, button...).' ); }
		if ( empty( $el['id'] ) ) { $el['id'] = RAHM_Direct::new_id(); }
		if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) { $el['settings'] = array(); }
		$el['elements'] = isset( $el['elements'] ) && is_array( $el['elements'] ) ? array_map( array( __CLASS__, 'prepare_element' ), $el['elements'] ) : array();
		return $el;
	}

	/** Add (or replace) an FAQ block on a page, plus FAQPage structured data. */
	private static function faq( array $p ) {
		$id   = (int) ( isset( $p['id'] ) ? $p['id'] : 0 );
		$post = get_post( $id );
		if ( ! $post ) { throw new Exception( 'Page not found.' ); }
		$items = array();
		foreach ( (array) ( isset( $p['items'] ) ? $p['items'] : array() ) as $x ) {
			$q = isset( $x['q'] ) ? trim( wp_strip_all_tags( $x['q'] ) ) : '';
			$a = isset( $x['a'] ) ? trim( wp_kses_post( $x['a'] ) ) : '';
			if ( $q !== '' && $a !== '' ) { $items[] = array( 'q' => $q, 'a' => $a ); }
		}
		if ( count( $items ) < 3 || count( $items ) > 12 ) { throw new Exception( 'Give 3 to 12 question and answer pairs, each with real, specific answers (no filler).' ); }
		RAHM_Ctx::gate( self::level_for( $id ), 'add an FAQ with ' . count( $items ) . ' questions to "' . get_the_title( $id ) . '"' );
		$heading = isset( $p['heading'] ) ? sanitize_text_field( $p['heading'] ) : 'Frequently asked questions';
		$html    = RAHM_SEO::faq_html( $items, $heading );
		$undo    = RAHM_Store::snap_post( $id, array( '_rahm_faq' ), 'FAQ' );
		update_post_meta( $id, '_rahm_faq', wp_slash( wp_json_encode( $items, JSON_UNESCAPED_UNICODE ) ) );
		$out = array( 'id' => $id, 'questions' => count( $items ), 'undo_id' => $undo );
		if ( RAHM_Direct::is_elementor( $id ) ) {
			$out['placed'] = 'schema only';
			$out['html']   = $html;
			$out['next']   = 'This is an Elementor page: add the html above with the elementor tool (an HTML widget at the end of the page). The FAQPage structured data is already saved.';
		} else {
			$c   = $post->post_content;
			$pat = '#<section class="rahm-faq".*?</section>#s';
			if ( preg_match( $pat, $c ) ) {
				$c = preg_replace_callback( $pat, function () use ( $html ) { return $html; }, $c, 1 );
			} else {
				$c .= "\n\n" . ( has_blocks( $c ) ? "<!-- wp:html -->\n" . $html . "\n<!-- /wp:html -->" : $html );
			}
			wp_update_post( wp_slash( array( 'ID' => $id, 'post_content' => $c ) ) );
			$out['placed'] = 'end of the page content';
		}
		RAHM_Ctx::done( 'seo', 'Added an FAQ (' . count( $items ) . ' questions) to "' . get_the_title( $id ) . '"', $id, $undo );
		return $out;
	}

	/** Read or set JSON-LD structured data for one page. */
	private static function schema( array $p ) {
		$id = (int) ( isset( $p['id'] ) ? $p['id'] : 0 );
		if ( ! get_post( $id ) ) { throw new Exception( 'Page not found.' ); }
		if ( ! array_key_exists( 'json', $p ) ) {
			return array( 'schema' => json_decode( (string) get_post_meta( $id, '_rahm_schema', true ), true ), 'faq_questions' => count( (array) json_decode( (string) get_post_meta( $id, '_rahm_faq', true ), true ) ) );
		}
		$d = is_string( $p['json'] ) ? json_decode( $p['json'], true ) : $p['json'];
		if ( $d === array() || $d === '' || $d === null ) {
			RAHM_Ctx::gate( self::level_for( $id ), 'remove the structured data of "' . get_the_title( $id ) . '"' );
			$undo = RAHM_Store::snap_post( $id, array( '_rahm_schema' ), 'Schema removed' );
			delete_post_meta( $id, '_rahm_schema' );
			RAHM_Ctx::done( 'seo', 'Removed structured data from "' . get_the_title( $id ) . '"', $id, $undo );
			return array( 'removed' => true, 'undo_id' => $undo );
		}
		if ( ! is_array( $d ) ) { throw new Exception( 'json must be a schema.org object (with @type) or a list of them.' ); }
		$nodes = isset( $d['@graph'] ) ? $d['@graph'] : ( isset( $d['@type'] ) ? array( $d ) : $d );
		foreach ( $nodes as $n ) {
			if ( ! is_array( $n ) || empty( $n['@type'] ) ) { throw new Exception( 'Every schema item needs an @type, for example Organization, LocalBusiness, Article, Product or WebSite.' ); }
		}
		$enc = wp_json_encode( array( '@graph' => array_values( $nodes ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( strlen( $enc ) > 20000 ) { throw new Exception( 'Structured data is too large (20 KB max).' ); }
		RAHM_Ctx::gate( self::level_for( $id ), 'set structured data (' . implode( ', ', array_map( function ( $n ) { return is_array( $n['@type'] ) ? implode( '/', $n['@type'] ) : $n['@type']; }, $nodes ) ) . ') on "' . get_the_title( $id ) . '"' );
		$undo = RAHM_Store::snap_post( $id, array( '_rahm_schema' ), 'Schema' );
		update_post_meta( $id, '_rahm_schema', wp_slash( $enc ) );
		RAHM_Ctx::done( 'seo', 'Set structured data on "' . get_the_title( $id ) . '"', $id, $undo );
		return array( 'id' => $id, 'types' => array_map( function ( $n ) { return $n['@type']; }, $nodes ), 'undo_id' => $undo, 'tip' => 'Do not repeat a type your SEO plugin already prints (two Organization blocks conflict).' );
	}

	private static function terms( array $p ) {
		$tax = isset( $p['taxonomy'] ) ? sanitize_key( $p['taxonomy'] ) : 'post_tag';
		if ( ! taxonomy_exists( $tax ) ) { throw new Exception( 'Unknown taxonomy.' ); }
		$op = isset( $p['op'] ) ? $p['op'] : 'list';
		switch ( $op ) {
			case 'list':
				$t = get_terms( array( 'taxonomy' => $tax, 'hide_empty' => false, 'number' => min( 100, (int) ( isset( $p['limit'] ) ? $p['limit'] : 50 ) ) ) );
				return is_wp_error( $t ) ? array() : array_map( function ( $x ) { return array( 'id' => $x->term_id, 'name' => $x->name, 'slug' => $x->slug, 'count' => $x->count ); }, $t );
			case 'create':
				RAHM_Ctx::gate( 'draft', 'create the ' . $tax . ' "' . $p['name'] . '"' );
				$r = wp_insert_term( sanitize_text_field( $p['name'] ), $tax, array( 'slug' => isset( $p['slug'] ) ? sanitize_title( $p['slug'] ) : '', 'description' => isset( $p['description'] ) ? sanitize_text_field( $p['description'] ) : '' ) );
				if ( is_wp_error( $r ) ) { throw new Exception( $r->get_error_message() ); }
				RAHM_Ctx::done( 'seo', 'Created ' . $tax . ' "' . $p['name'] . '"' );
				return $r;
			case 'rename':
				RAHM_Ctx::gate( 'live', 'rename/re-slug ' . $tax . ' #' . (int) $p['id'] );
				$args = array();
				if ( isset( $p['name'] ) ) { $args['name'] = sanitize_text_field( $p['name'] ); }
				if ( isset( $p['slug'] ) ) { $args['slug'] = sanitize_title( $p['slug'] ); }
				$r = wp_update_term( (int) $p['id'], $tax, $args );
				if ( is_wp_error( $r ) ) { throw new Exception( $r->get_error_message() ); }
				RAHM_Ctx::done( 'seo', 'Updated ' . $tax . ' #' . (int) $p['id'] );
				return $r;
			case 'delete':
				RAHM_Ctx::gate( 'danger', 'delete the ' . $tax . ' #' . (int) $p['id'] . ' (it cannot be undone)' );
				$ok = wp_delete_term( (int) $p['id'], $tax );
				RAHM_Ctx::done( 'seo', 'Deleted ' . $tax . ' #' . (int) $p['id'] );
				return array( 'deleted' => (bool) $ok );
		}
		throw new Exception( 'op must be list, create, rename or delete.' );
	}
}
