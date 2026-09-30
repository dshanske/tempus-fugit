<?php
/**
 * Merges the single-site and multisite coverage reports and prints a text summary.
 *
 * Usage: php bin/merge-coverage.php <report.cov> [<report.cov> ...]
 *
 * The reports are written by `phpunit --coverage-php`. Run by `composer coverage`.
 *
 * @package TempusFugit
 */

require dirname( __DIR__ ) . '/vendor/autoload.php';

$tempus_reports = array_slice( $argv, 1 );
if ( ! $tempus_reports ) {
	fwrite( STDERR, "Usage: php bin/merge-coverage.php <report.cov> [<report.cov> ...]\n" );
	exit( 1 );
}

$tempus_coverage = include array_shift( $tempus_reports );
foreach ( $tempus_reports as $tempus_report ) {
	$tempus_coverage->merge( include $tempus_report );
}

echo ( new SebastianBergmann\CodeCoverage\Report\Text() )->process( $tempus_coverage, false );
