<?php

namespace MediaWiki\Extension\AVIF\Maintenance;

use Maintenance;
use MediaWiki\Extension\AVIF\Job\AvifTransformJob;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class GenerateAvifFromFiles extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'AVIF' );
		$this->addOption( 'file', 'File(s) to regenerate AVIF versions of', multiOccurrence: true );
	}

	public function execute(): void {
		$this->output( "queueing AVIF file generation jobs...\n" );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()
			->getMaintenanceConnectionRef( DB_REPLICA );

		$files = $this->hasOption( 'file' ) ? $this->getOption( 'file' ) : [];

		if ( empty( $files ) ) {
			$files = $dbr->newSelectQueryBuilder()
				->select( [ 'img_name' ] )
				->from( 'image' )
				->where(
					$dbr->expr( 'img_major_mime', '=', 'image' )
					->andExpr(
						$dbr->expr( 'img_minor_mime', '=', 'png' )
						->or( 'img_minor_mime', '=', 'jpeg' )
					)
				)
				->caller( __METHOD__ )
				->fetchFieldValues();
		}

		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroup();
		foreach ( $files as $title ) {
			$this->output( "queued $title\n" );
			$jobQueueGroup->lazyPush( new AvifTransformJob( [
				'namespace' => NS_FILE,
				'title' => $title,
			] ) );
		}
	}
}

$maintClass = GenerateAvifFromFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
