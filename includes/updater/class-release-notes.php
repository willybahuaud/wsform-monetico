<?php
/**
 * Release notes to HTML.
 *
 * @package WSForm_Monetico\Updater
 */

namespace WSForm_Monetico\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Covers the Markdown GitHub generates for a release: headings, lists, bold and links.
 */
class Release_Notes {

	/**
	 * Convert release notes to HTML.
	 *
	 * @param string $markdown Release notes.
	 *
	 * @return string
	 */
	public static function to_html( $markdown ) {
		if ( '' === trim( $markdown ) ) {
			return '<p>' . esc_html__( 'Aucune note de version.', 'wsform-monetico' ) . '</p>';
		}

		$html    = '';
		$in_list = false;

		foreach ( preg_split( '/\R/', $markdown ) as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$is_list_item = 1 === preg_match( '/^[*-]\s+(.+)$/', $line, $list_parts );

			if ( $is_list_item !== $in_list ) {
				$html   .= $is_list_item ? '<ul>' : '</ul>';
				$in_list = $is_list_item;
			}

			$html .= $is_list_item ? '<li>' . self::format_inline( $list_parts[1] ) . '</li>' : self::format_block( $line );
		}

		return $in_list ? $html . '</ul>' : $html;
	}

	/**
	 * Convert a line outside a list: a heading or a paragraph.
	 *
	 * @param string $line Line of text.
	 *
	 * @return string
	 */
	private static function format_block( $line ) {
		if ( 1 === preg_match( '/^#{1,6}\s+(.+)$/', $line, $heading_parts ) ) {
			return '<h4>' . self::format_inline( $heading_parts[1] ) . '</h4>';
		}

		return '<p>' . self::format_inline( $line ) . '</p>';
	}

	/**
	 * Convert bold text and links in a line.
	 *
	 * Escaping comes first: the text comes from GitHub and shows in the admin.
	 *
	 * @param string $text Text.
	 *
	 * @return string
	 */
	private static function format_inline( $text ) {
		$bold_text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', esc_html( $text ) );

		// Not make_clickable: it would cut compare links like "v1.0.0...v1.0.1" at the dots.
		return preg_replace( '#https://[^\s<]*[^\s<.,)]#', '<a href="$0">$0</a>', $bold_text );
	}
}
