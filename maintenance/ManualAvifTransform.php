<?php

namespace MediaWiki\Extension\AVIF\Maintenance;

use Maintenance;
use MediaWiki\Extension\AVIF\AvifTransformJob;
use MediaWiki\MediaWikiServices;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class ManualAvifTransform extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'AVIF' );
		$this->addOption( 'file', 'File(s) to regenerate AVIF versions of', multiOccurrence: true, required: true );
	}

	public function execute(): void {
		$this->output( "queueing AVIF file generation jobs...\n" );

		$files = $this->getOption( 'file' );

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

$maintClass = ManualAvifTransform::class;
require_once RUN_MAINTENANCE_IF_MAIN;
