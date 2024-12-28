<?php

namespace MediaWiki\Extension\AVIF\Hook;

use JobQueueGroup;
use MediaWiki\Extension\AVIF\Job\AvifTransformJob;
use MediaWiki\Hook\FileDeleteCompleteHook;
use MediaWiki\Hook\FileUndeleteCompleteHook;
use MediaWiki\Hook\FileUploadHook;
use MediaWiki\Hook\PageMoveCompleteHook;
use RepoGroup;

class FileHooks implements FileUploadHook, PageMoveCompleteHook, FileDeleteCompleteHook, FileUndeleteCompleteHook {
	private JobQueueGroup $jobQueueGroup;
	private RepoGroup $repoGroup;

	public function __construct( JobQueueGroup $jobQueueGroup, RepoGroup $repoGroup ) {
		$this->jobQueueGroup = $jobQueueGroup;
		$this->repoGroup = $repoGroup;
	}

	/** @inheritDoc */
	public function onFileUpload( $file, $reupload, $hasDescription ): void {
		// make sure the file is supported to be transformed
		if ( !in_array(
			needle: $file->getMimeType(),
			haystack: AvifTransformJob::SUPPORTED_MIME_TYPES,
			strict: true
		) ) {
			return;
		}

		// run the job
		$this->jobQueueGroup->push( new AvifTransformJob( [
			'title' => $file->getTitle(),
		] ) );
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
		$oldFile->load();
		$newFile->load();

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

	/** @inheritDoc */
	public function onFileUndeleteComplete( $title, $fileVersions, $user, $reason ): void {
		// find the undeleted file
		$file = $this->repoGroup->getLocalRepo()->findFile(
			$title,
			[ 'ignoreRedirect' => true, 'latest' => true ]
		);
		// and if it exists
		if ( $file !== false ) {
			// make sure the file is supported to be transformed
			if ( !in_array(
				needle: $file->getMimeType(),
				haystack: AvifTransformJob::SUPPORTED_MIME_TYPES,
				strict: true
			) ) {
				return;
			}

			// regenerate the AVIF version
			$this->jobQueueGroup->push( new AvifTransformJob( [
				'title' => $title,
			] ) );
		}
	}
}
