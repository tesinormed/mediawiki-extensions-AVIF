<?php

namespace MediaWiki\Extension\AVIF;

use FileRepo;
use GenericParameterJob;
use Jcupitt\Vips\Exception;
use Jcupitt\Vips\Image;
use Job;
use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use Wikimedia\FileBackend\FSFile\FSFile;

class AvifTransformJob extends Job implements GenericParameterJob {
	public const COMMAND = 'AvifTransform';

	public function __construct( array $params ) {
		parent::__construct( self::COMMAND, $params );
		$this->removeDuplicates = true;
	}

	public function run(): bool {
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();

		$file = $repoGroup->findFile( $this->params['title'], [ 'ignoreRedirect' => true, 'latest' => true ] );
		if ( $file === false ) {
			$this->setLastError( sprintf( 'file not found: %s', $this->params['title'] ) );
			return false;
		}

		// make a temporary file to store the converted file in
		$temporaryFile = self::createTemporaryFile();

		$fileLocalPath = $file->getLocalRefPath();
		if ( $fileLocalPath === false ) {
			$this->setLastError( "getting local file reference failed: {$this->params['title']}" );
			return false;
		}

		// transform the file, make sure it worked
		if ( !$this->transformFile( $fileLocalPath, $temporaryFile->getPath() ) ) {
			return false;
		}

		// store the transformed file with .avif appended
		$storageResult = $file->getRepo()->store(
			$temporaryFile,
			dstZone: 'public',
			dstRel: $file->getRel() . '.avif',
			flags: FileRepo::OVERWRITE | FileRepo::SKIP_LOCKING
		);
		if ( !$storageResult->isGood() ) {
			$this->setLastError( sprintf( 'storing file failed: %s', $this->getTitle()->getDBkey() ) );
			return false;
		}

		return true;
	}

	private function transformFile( string $inputFilePath, string $outputFilePath ): bool {
		try {
			$vipsImage = Image::newFromFile( $inputFilePath );
			$vipsImage->writeToFile( $outputFilePath, self::getExtensionConfig()->get( 'AVIFSaveOptions' ) );
		} catch ( Exception $exception ) {
			$this->setLastError( "transforming file failed: {$exception->getMessage()}" );
			return false;
		}

		return true;
	}

	private static function getExtensionConfig(): Config {
		return MediaWikiServices::getInstance()
			->getConfigFactory()
			->makeConfig( 'avif' );
	}

	private static function createTemporaryFile(): FSFile {
		return MediaWikiServices::getInstance()
			->getTempFSFileFactory()
			->newTempFSFile( prefix: self::COMMAND . '_', extension: 'avif' );
	}
}
