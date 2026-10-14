<?php
/**
 * Elementor helpers: read the layout tree, change one element, save with a backup.
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RAHM_Direct {

	const EL = '_elementor_data';

	public static function is_elementor( $post_id ) {
		$d = get_post_meta( $post_id, self::EL, true );
		return is_string( $d ) && $d !== '' && $d !== '[]';
	}

	public static function el_get( $post_id ) {
		$d = get_post_meta( $post_id, self::EL, true );
		if ( is_array( $d ) ) {
			return $d;
		}
		$t = json_decode( (string) $d, true );
		return is_array( $t ) ? $t : array();
	}

	/** Save a new layout. The old one is backed up first so it can be undone for 30 days. */
	public static function el_save( $post_id, array $tree, $note = 'Elementor change' ) {
		$undo = RAHM_Store::snap_post( $post_id, array( self::EL ), $note );
		update_post_meta( $post_id, self::EL, wp_slash( wp_json_encode( $tree ) ) );
		wp_update_post( array( 'ID' => $post_id ) );
		self::clear_builder_cache( $post_id );
		RAHM_Ctx::$post = (int) $post_id;
		RAHM_Ctx::$undo = $undo;
		return $undo;
	}

	public static function clear_builder_cache( $post_id ) {
		delete_post_meta( $post_id, '_elementor_css' );
		clean_post_cache( $post_id );
		try {
			if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
				\Elementor\Plugin::$instance->files_manager->clear_cache();
			}
		} catch ( Throwable $e ) {
			unset( $e );
		}
	}

	public static function new_id() {
		return substr( md5( uniqid( '', true ) ), 0, 7 );
	}

	/**
	 * Find element $id and let $fn change it.
	 * $fn( $el ) returns: the element, null (delete it), or array( '_list' => array(...) ) to replace it with several.
	 */
	public static function map_tree( array $els, $id, callable $fn, &$found ) {
		$out = array();
		foreach ( $els as $el ) {
			if ( isset( $el['id'] ) && (string) $el['id'] === (string) $id ) {
				$found = true;
				$r     = $fn( $el );
				if ( $r === null ) {
					continue;
				}
				if ( isset( $r['_list'] ) ) {
					foreach ( $r['_list'] as $x ) {
						$out[] = $x;
					}
					continue;
				}
				$out[] = $r;
				continue;
			}
			if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
				$el['elements'] = self::map_tree( $el['elements'], $id, $fn, $found );
			}
			$out[] = $el;
		}
		return $out;
	}

	/** Short overview of the tree for the AI (ids, types and the visible text/image of each element). */
	public static function summarize( array $els, $depth = 0, $max_depth = 6 ) {
		$out = array();
		foreach ( $els as $el ) {
			$row = array(
				'id'   => isset( $el['id'] ) ? $el['id'] : '',
				'type' => ( isset( $el['elType'] ) ? $el['elType'] : '' ) . ( ! empty( $el['widgetType'] ) ? ':' . $el['widgetType'] : '' ),
			);
			$s = isset( $el['settings'] ) && is_array( $el['settings'] ) ? $el['settings'] : array();
			foreach ( array( 'title', 'editor', 'text', 'title_text', 'description_text', 'heading', 'content' ) as $k ) {
				if ( ! empty( $s[ $k ] ) && is_string( $s[ $k ] ) ) {
					$row[ $k ] = mb_substr( wp_strip_all_tags( $s[ $k ] ), 0, 80 );
				}
			}
			if ( ! empty( $s['image']['url'] ) ) {
				$row['image'] = $s['image']['url'];
			}
			if ( ! empty( $s['link']['url'] ) ) {
				$row['link'] = $s['link']['url'];
			}
			if ( ! empty( $el['elements'] ) && $depth < $max_depth ) {
				$row['children'] = self::summarize( $el['elements'], $depth + 1, $max_depth );
			}
			$out[] = $row;
		}
		return $out;
	}
}
