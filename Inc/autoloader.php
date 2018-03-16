<?php

/**
 * Dynamically loads the class attempting to be instantiated elsewhere in the
 * plugin.
 *
 * @package BadgeFactorEvent\Inc
 */

spl_autoload_register( 'BadgeFactorEvent_autoload' );

/**
 * Dynamically loads the class attempting to be instantiated elsewhere in the
 * plugin by looking at the $class_name parameter being passed as an argument.
 *
 * The argument should be in the form: TutsPlus_Namespace_Demo\Namespace. The
 * function will then break the fully-qualified class name into its pieces and
 * will then build a file to the path based on the namespace.
 *
 * The namespaces in this plugin map to the paths in the directory structure.
 *
 * @param string $class_name The fully-qualified name of the class to load.
 */
function BadgeFactorEvent_autoload( $class_name ) {

	// If the specified $class_name does not include our namespace, duck out.
	if ( false === strpos( $class_name, 'BadgeFactorEvent' ) ) {
		return;
	}

	// Split the class name into an array to read the namespace and class.
	$file_parts = explode( '\\', $class_name );

	// Do a reverse loop through $file_parts to build the path to the file.
	$namespace = '';

	for ( $i = (count( $file_parts ) - 1); $i > 0; $i-- ) {

		// If we're at the first entry, then we're at the filename.
		if ( count( $file_parts ) - 1 === $i ) {

			$file_name = $file_parts[$i]. '.php';

		} else {
			$namespace = '/' . $file_parts[$i] . $namespace;
		}

		// Now build a path to the file using mapping to the file location.
		$filepath  = trailingslashit( dirname( dirname( __FILE__ ) ) . $namespace );
		$filepath .= $file_name;
	}

	// If the file exists in the specified path, then include it.
	if ( file_exists( $filepath ) ) {
		include_once( $filepath );
	} else {
		wp_die(
			esc_html( "The file attempting to be loaded at $filepath does not exist." )
		);
	}
}