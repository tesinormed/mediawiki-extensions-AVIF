<?php

namespace MediaWiki\Extension\AVIF\Maintenance;

use Maintenance;
use MediaWiki\Extension\AVIF\Job\AvifTransformJob;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;

$IP = getenv( 'MW_INSTALL_PATH' );
if ( $IP === false ) {
	$IP = __DIR__ . '/../../..';
}
require_once "$IP/maintenance/Maintenance.php";

class GenerateAvifFromFiles extends Maintenance {
	public function __construct() {
		parent::__construct();
		$this->requireExtension( 'AVIF' );
		$this->addOption( 'titles', 'Comma-seperated list of titles to generate' );
	}

	public function execute(): void {
		$this->output( "generating AVIF files...\n" );

		$dbr = MediaWikiServices::getInstance()->getDBLoadBalancer()
			->getMaintenanceConnectionRef( DB_REPLICA );

		$titles = $this->hasOption( 'titles' ) ? $this->getOption( 'titles' ) : [];

		if ( empty( $titles ) ) {
			$titles = $dbr->newSelectQueryBuilder()
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

		$jobQueueGroup = MediaWikiServices::getInstance()->getJobQueueGroupFactory()->makeJobQueueGroup();
		foreach ( $titles as $title ) {
			$this->output( "generating AVIF version of $title\n" );
			$jobQueueGroup->push( new AvifTransformJob( [
				'title' => Title::makeTitleSafe( NS_FILE, $title ),
			] ) );
		}
	}
}

$maintClass = GenerateAvifFromFiles::class;
require_once RUN_MAINTENANCE_IF_MAIN;
