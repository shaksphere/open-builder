<?php
namespace OpenBuilder;

defined( 'ABSPATH' ) || exit;

/**
 * Global brand settings: colors, fonts and sizes exposed as CSS custom
 * properties (variables). Widget style values may reference these as
 * var(--ob-color-primary), keeping client sites on-brand and consistent.
 */
class Global_Styles {

	const OPTION = 'openb_global_styles';

	public function get(): array {
		$defaults = $this->defaults();
		$saved    = get_option( self::OPTION, [] );
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return wp_parse_args( $saved, $defaults );
	}

	public function defaults(): array {
		return [
			'colors' => [
				[ 'id' => 'primary',   'name' => 'Primary',   'value' => '#2563eb' ],
				[ 'id' => 'secondary', 'name' => 'Secondary', 'value' => '#7c3aed' ],
				[ 'id' => 'text',      'name' => 'Text',      'value' => '#1f2937' ],
				[ 'id' => 'heading',   'name' => 'Heading',   'value' => '#111827' ],
				[ 'id' => 'muted',     'name' => 'Muted',     'value' => '#6b7280' ],
				[ 'id' => 'accent',    'name' => 'Accent',    'value' => '#f59e0b' ],
				[ 'id' => 'light',     'name' => 'Light',     'value' => '#f9fafb' ],
				[ 'id' => 'dark',      'name' => 'Dark',      'value' => '#0f172a' ],
			],
			'fonts' => [
				[ 'id' => 'heading', 'name' => 'Heading', 'value' => "'Inter', system-ui, sans-serif" ],
				[ 'id' => 'body',    'name' => 'Body',    'value' => "system-ui, -apple-system, sans-serif" ],
			],
			'sizes' => [
				[ 'id' => 'sm', 'name' => 'Small',  'value' => '0.875rem' ],
				[ 'id' => 'md', 'name' => 'Medium', 'value' => '1rem' ],
				[ 'id' => 'lg', 'name' => 'Large',  'value' => '1.25rem' ],
				[ 'id' => 'xl', 'name' => 'XL',     'value' => '2rem' ],
			],
		];
	}

	public function save( array $incoming ): array {
		$clean = [ 'colors' => [], 'fonts' => [], 'sizes' => [] ];

		foreach ( [ 'colors', 'fonts', 'sizes' ] as $group ) {
			if ( empty( $incoming[ $group ] ) || ! is_array( $incoming[ $group ] ) ) {
				continue;
			}
			foreach ( array_slice( $incoming[ $group ], 0, 50 ) as $item ) {
				if ( empty( $item['id'] ) ) {
					continue;
				}
				$value = ( 'colors' === $group )
					? Security::sanitize_color( (string) ( $item['value'] ?? '' ) )
					: Security::sanitize_css_value( (string) ( $item['value'] ?? '' ) );
				if ( '' === $value ) {
					continue;
				}
				$clean[ $group ][] = [
					'id'    => sanitize_key( $item['id'] ),
					'name'  => sanitize_text_field( (string) ( $item['name'] ?? $item['id'] ) ),
					'value' => $value,
				];
			}
		}

		update_option( self::OPTION, $clean, false );
		return $this->get();
	}

	/** Build the :root CSS block of custom properties. */
	public function to_css(): string {
		$styles = $this->get();
		$vars   = [];

		foreach ( $styles['colors'] as $c ) {
			$vars[] = sprintf( '--ob-color-%s:%s', $c['id'], $c['value'] );
		}
		foreach ( $styles['fonts'] as $f ) {
			$vars[] = sprintf( '--ob-font-%s:%s', $f['id'], $f['value'] );
		}
		foreach ( $styles['sizes'] as $s ) {
			$vars[] = sprintf( '--ob-size-%s:%s', $s['id'], $s['value'] );
		}

		if ( empty( $vars ) ) {
			return '';
		}
		return ':root{' . implode( ';', $vars ) . '}';
	}
}
