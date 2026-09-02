<?php
/**
 * Personal-data export/erase callbacks for audit and plan rows.
 *
 * @package Struo
 */

namespace Struo\Audit;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PrivacyExporter {

	public static function register_audit_exporter( $exporters ) {
		return \Struo_Block_Editor::register_audit_data_exporter( $exporters );
	}

	public static function register_audit_eraser( $erasers ) {
		return \Struo_Block_Editor::register_audit_data_eraser( $erasers );
	}

	public static function register_plan_exporter( $exporters ) {
		return \Struo_Block_Editor::register_plan_data_exporter( $exporters );
	}

	public static function register_plan_eraser( $erasers ) {
		return \Struo_Block_Editor::register_plan_data_eraser( $erasers );
	}
}
