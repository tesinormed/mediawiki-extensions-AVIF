<?php

namespace MediaWiki\Extension\AVIF;

use FileRepo;
use Jcupitt\Vips\Exception;
use Jcupitt\Vips\Image;
use Job;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\FileBackend\FSFile\TempFSFileFactory;
use RepoGroup;
use Wikimedia\Rdbms\IDBAccessObject;

class AvifTransformJob extends Job {
	public const COMMAND = 'AvifTransform';

	private readonly Config $config;

	public function __construct(
		array $params,
		ConfigFactory $configFactory,
		private readonly RepoGroup $repoGroup,
		private readonly TempFSFileFactory $tempFSFileFactory
	) {
		parent::__construct( self::COMMAND, $params );
		$this->removeDuplicates = true;

		$this->config = $configFactory->makeConfig( 'avif' );
	}

	public function run(): bool {
		$localRepo = $this->repoGroup->getLocalRepo();

		$file = $localRepo->newFile( $this->params['filename'] );
		$file?->load( IDBAccessObject::READ_LATEST );
		if ( $file === null || !$file->exists() ) {
			$this->setLastError( "file not found" );
			return false;
		}

		$localFile = $localRepo->getLocalCopy( $file->getVirtualUrl() );
		if ( $localFile === false || $localFile === null ) {
			$this->setLastError( "getting local file copy failed" );
			return false;
		}

		$temporaryFile = $this->tempFSFileFactory->newTempFSFile( prefix: self::COMMAND . '_', extension: 'avif' );
		if ( $temporaryFile === null ) {
			$this->setLastError( "creating temporary file failed" );
			return false;
		}

		// transform the file, make sure it worked
		try {
			Image::newFromFile( $localFile->getPath() )
				->writeToFile( $temporaryFile->getPath(), $this->config->get( 'AVIFSaveOptions' ) );
		} catch ( Exception $exception ) {
			$this->setLastError( "transforming file failed: {$exception->getMessage()}" );
			return false;
		}

		// store the transformed file with .avif appended
		$storageResult = $file->getRepo()->store(
			$temporaryFile,
			dstZone: 'public',
			dstRel: $file->getRel() . '.avif',
			flags: FileRepo::OVERWRITE
		);
		if ( !$storageResult->isGood() ) {
			$this->setLastError( "storing file failed: {$temporaryFile->getPath()}" );
			return false;
		}

		if ( !$temporaryFile->purge() ) {
			$this->setLastError( "purging temporary file failed: {$temporaryFile->getPath()}" );
		}

		return true;
	}
}
