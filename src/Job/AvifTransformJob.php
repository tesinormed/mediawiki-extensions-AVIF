<?php

namespace MediaWiki\Extension\AVIF\Job;

use Exception;
use File;
use FileRepo;
use FSFile;
use GenericParameterJob;
use IDBAccessObject;
use Imagick;
use ImagickException;
use Job;
use LocalRepo;
use MediaWiki\Config\Config;
use MediaWiki\MediaWikiServices;
use MediaWiki\Shell\Shell;

/**
 * A {@link Job} to transform an image file into an AVIF file
 */
class AvifTransformJob extends Job implements GenericParameterJob {
	/**
	 * This {@link Job}'s unique identifier
	 */
	public const COMMAND = 'AvifTransform';
	/**
	 * The supported MIME types for avifenc
	 */
	public const SUPPORTED_MIME_TYPES = [
		'image/jpeg',
		'image/png',
	];

	public function __construct( array $params ) {
		parent::__construct( self::COMMAND, $params );
		$this->removeDuplicates = true;
	}

	public function run(): bool {
		// check prerequisites
		if ( Shell::isDisabled() ) {
			$this->setLastError( 'shell is disabled' );
			return false;
		}
		if ( !is_executable( self::getExtensionConfig()->get( 'AVIFExecutablePath' ) ) ) {
			$this->setLastError( 'avifenc is not executable or does not exist' );
			return false;
		}
		if ( !extension_loaded( 'imagick' ) ) {
			$this->setLastError( 'imagick PHP extension required' );
			return false;
		}

		// get the local file repository
		$repoGroup = MediaWikiServices::getInstance()->getRepoGroup();
		/** @var LocalRepo $localFileRepository */
		$localFileRepository = $repoGroup->getLocalRepo();
		$localFile = $repoGroup->findFile(
			$this->params['title'],
			[ 'ignoreRedirect' => true, 'latest' => true ]
		);
		if ( $localFile === false ) {
			$this->setLastError( sprintf( 'file not found: %s', $this->params['title'] ) );
			return false;
		}
		$localFile->load( IDBAccessObject::READ_LATEST );

		// get the parameters
		$width = $this->params['width'] ?? 0;
		$height = $this->params['height'] ?? 0;

		// make a temporary file to store the converted file in
		$temporaryFile = self::createTemporaryFile( 'avif' );

		// transform the file, make sure it worked
		if ( !$this->transformFile( $localFile, $temporaryFile->getPath(), $width, $height ) ) {
			return false;
		}

		// store the transformed file with .avif appended
		if ( $width == 0 && $height == 0 ) {
			// original file
			$storageResult = $localFileRepository->store(
				$temporaryFile,
				dstZone: 'public',
				dstRel: $localFile->getRel() . '.avif',
				flags: FileRepo::OVERWRITE | FileRepo::SKIP_LOCKING
			);
		} else {
			// thumbnail
			$storageResult = $localFileRepository->store(
				$temporaryFile,
				dstZone: 'thumb',
				dstRel: $localFile->getThumbRel(
					$localFile->thumbName( [ 'width' => $width ], File::THUMB_FULL_NAME ) . '.avif'
				),
				flags: FileRepo::OVERWRITE | FileRepo::SKIP_LOCKING
			);
		}
		// make sure storing the file worked
		if ( !$storageResult->isGood() ) {
			$this->setLastError( sprintf( 'storing file failed: %s', $this->getTitle()->getDBkey() ) );
			return false;
		}

		return true;
	}

	private function transformFile(
		File $inputFile,
		string $outputFilePath,
		int $width,
		int $height
	): bool {
		$inputFilePath = $inputFile->getLocalRefPath();
		if ( $inputFilePath === false ) {
			$this->setLastError( sprintf( 'getting local file reference failed: %s', $this->params['title'] ) );
			return false;
		}
		// if the file needs to be resized
		if ( $width != 0 || $height != 0 ) {
			// create a temporary file to output the resized file into
			$temporaryFile = self::createTemporaryFile( $inputFile->getExtension() );
			// resize the file
			try {
				$image = new Imagick( $inputFile->getLocalRefPath() );
				$image->resizeImage( $width, $height, imagick::FILTER_LANCZOS, 1, true );
				$image->writeImages( $temporaryFile->getPath(), true );
				$inputFilePath = $temporaryFile->getPath();
			} catch ( ImagickException $exception ) {
				$this->setLastError( $exception->getMessage() );
				return false;
			}
		}

		// run avifenc
		$command = MediaWikiServices::getInstance()->getShellCommandFactory()->create();
		$command->unsafeParams(
			self::getExtensionConfig()->get( 'AVIFExecutablePath' ),
			// from https://web.dev/articles/compress-images-avif#create_an_avif_image_with_default_settings
			'--min 0',
			'--max 63',
			'--minalpha 0',
			'--maxalpha 63',
			'-a end-usage=q',
			'-a cq-level=18',
			'-a tune=ssim',
			$inputFilePath,
			$outputFilePath,
		);
		try {
			$result = $command->execute();
		} catch ( Exception $exception ) {
			$this->setLastError( $exception->getMessage() );
			return false;
		}

		// make sure the command exited normally
		return $result->getExitCode() === 0;
	}

	private static function getExtensionConfig(): Config {
		return MediaWikiServices::getInstance()->getConfigFactory()
			->makeConfig( 'avif' );
	}

	private static function createTemporaryFile( string $fileExtension ): FSFile {
		return MediaWikiServices::getInstance()->getTempFSFileFactory()
			->newTempFSFile( self::COMMAND . '_', $fileExtension );
	}
}
