<?php

namespace MediaWiki\Extension\AVIF\Hook;

use IDBAccessObject;
use JobQueueGroup;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use RepoGroup;

/** @noinspection PhpUnused */
class FileHooks implements
	PageMoveCompleteHook,
	FileDeleteCompleteHook
{
	private JobQueueGroup $jobQueueGroup;
	private RepoGroup $repoGroup;

	public function __construct( JobQueueGroup $jobQueueGroup, RepoGroup $repoGroup ) {
		$this->jobQueueGroup = $jobQueueGroup;
		$this->repoGroup = $repoGroup;
	}

	/** @inheritDoc */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ): void {
		// get the new file
		$newFile = $this->repoGroup->findFile(
			$new,
			[ 'ignoreRedirect' => true, 'latest' => true ]
		);
		// make sure it was found
		if ( $newFile === false ) {
			return;
		}

		$fileRepository = $newFile->getRepo();

		// get the old file
		$oldFile = $fileRepository->newFile( $old );
		// make sure it was found
		if ( $oldFile === null ) {
			return;
		}

		// load the files
		$oldFile->load( IDBAccessObject::READ_LATEST );
		$newFile->load( IDBAccessObject::READ_LATEST );

		// check if an AVIF version exists
		$oldAvifFilePath = $oldFile->getPath() . '.avif';
		if ( $fileRepository->fileExists( $oldAvifFilePath ) === true ) {
			// move it to the same folder as the new file
			$fileRepository->getBackend()->move( [
				'src' => $oldAvifFilePath,
				'dst' => $newFile->getPath() . '.avif',
				'overwrite' => true,
				'overwriteSame' => false,
				'ignoreMissingSource' => false,
			] );

			// purge the old file from caches
			$fileRepository->quickPurge( $oldAvifFilePath );

			// and if $wgHashedUploadDirectory is true
			if ( $oldFile->getHashPath() != '' ) {
				// delete the directory if it's empty
				$fileRepository->cleanDir( $oldFile->getHashPath() );
			}
		}
	}

	/** @inheritDoc */
	public function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ): void {
		$fileRepository = $file->getRepo();

		$avifFilePath = $file->getPath() . '.avif';
		// if the AVIF version exists
		if ( $fileRepository->fileExists( $avifFilePath ) === true ) {
			// delete it
			$fileRepository->getBackend()->delete( [
				'src' => $avifFilePath,
				'ignoreMissingSource' => false,
			] );

			// purge the deleted file from caches
			$fileRepository->quickPurge( $avifFilePath );

			// and if $wgHashedUploadDirectory is true
			if ( $file->getHashPath() != '' ) {
				// delete the directory if it's empty
				$fileRepository->cleanDir( $file->getHashPath() );
			}
		}
	}
}
