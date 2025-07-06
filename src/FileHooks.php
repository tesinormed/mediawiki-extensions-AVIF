<?php

namespace MediaWiki\Extension\AVIF;

use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use RepoGroup;

class FileHooks implements PageMoveCompleteHook, FileDeleteCompleteHook {
	private RepoGroup $repoGroup;

	public function __construct( RepoGroup $repoGroup ) {
		$this->repoGroup = $repoGroup;
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/PageMoveComplete
	 * @inheritDoc
	 */
	public function onPageMoveComplete( $old, $new, $user, $pageid, $redirid, $reason, $revision ): void {
		// get the new file
		$newFile = $this->repoGroup->findFile( $new, [ 'ignoreRedirect' => true, 'latest' => true ] );
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

		$oldAvifFilePath = $oldFile->getPath() . '.avif';
		$newAvifFilePath = $newFile->getPath() . '.avif';

		$fileRepository->getBackend()->move( [
			'src' => $oldAvifFilePath,
			'dst' => $newAvifFilePath,
			'overwrite' => true,
			'ignoreMissingSource' => true
		] );

		$fileRepository->quickPurge( $oldAvifFilePath );
		$fileRepository->quickPurge( $newAvifFilePath );

		// if $wgHashedUploadDirectory is true
		if ( $oldFile->getHashPath() != '' ) {
			// delete the directory if it's empty
			$fileRepository->cleanDir( $oldFile->getHashPath() );
		}
	}

	/**
	 * @see https://www.mediawiki.org/wiki/Manual:Hooks/FileDeleteComplete
	 * @inheritDoc
	 */
	public function onFileDeleteComplete( $file, $oldimage, $article, $user, $reason ): void {
		$fileRepository = $file->getRepo();
		$avifFilePath = $file->getPath() . '.avif';

		$fileRepository->getBackend()->delete( [ 'src' => $avifFilePath, 'ignoreMissingSource' => true ] );
		$fileRepository->quickPurge( $avifFilePath );

		// if $wgHashedUploadDirectory is true
		if ( $file->getHashPath() != '' ) {
			// delete the directory if it's empty
			$fileRepository->cleanDir( $file->getHashPath() );
		}
	}
}
