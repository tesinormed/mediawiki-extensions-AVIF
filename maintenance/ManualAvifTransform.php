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
		$this->addOption( 'file', 'File(s) to regenerate AVIF versions of', required: true, multiOccurrence: true );
	}

	public function execute(): void {
		$this->output( "queueing AVIF file generation jobs...\n" );

		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroupFactory()
			->makeJobQueueGroup();
		$jobFactory = MediaWikiServices::getInstance()->getJobFactory();
		foreach ( $this->getOption( 'file' ) as $file ) {
			$this->output( "queued $file\n" );
			$jobQueueGroup->push( $jobFactory->newJob(
				AvifTransformJob::COMMAND,
				[ 'filename' => $file ]
			) );
		}
	}
}

$maintClass = ManualAvifTransform::class;
require_once RUN_MAINTENANCE_IF_MAIN;
