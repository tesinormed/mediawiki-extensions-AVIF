<?php

namespace MediaWiki\Extension\AVIF\Hook;

use File;
use JobQueueGroup;
use MediaWiki\Extension\AVIF\Job\AvifTransformJob;
use MediaWiki\Extension\PictureHtmlSupport\Hook\PictureHtmlSupportBeforeProduceHtml;
use RepoGroup;
use ThumbnailImage;

class ThumbnailHooks implements PictureHtmlSupportBeforeProduceHtml {
	private RepoGroup $repoGroup;
	private JobQueueGroup $jobQueueGroup;

	public function __construct( RepoGroup $repoGroup, JobQueueGroup $jobQueueGroup ) {
		$this->repoGroup = $repoGroup;
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/**
	 * @inheritDoc
	 */
	public function onPictureHtmlSupportBeforeProduceHtml( ThumbnailImage $thumbnail, array &$sources ): void {
		// don't use a thumbnail for a source file image
		if ( $thumbnail->fileIsSource() ) {
			return;
		}

		$thumbnailFile = $thumbnail->getFile();
		// get the width and height of the requested thumbnail
		$width = $thumbnail->getWidth();
		$height = $thumbnail->getHeight();
		// get the path and virtual URL of the potentially existing thumbnail
		$avifThumbnailPath = $thumbnailFile->getThumbRel(
			suffix: $thumbnailFile->thumbName( [ 'width' => $width ], File::THUMB_FULL_NAME ) . '.avif'
		);
		$avifThumbnailVirtualUrl = $this->repoGroup->getLocalRepo()->getZonePath( 'thumb' ) . '/' . $avifThumbnailPath;

		// if the thumbnail exists
		if ( $this->repoGroup->getLocalRepo()->fileExists( $avifThumbnailVirtualUrl ) ) {
			// add it to the source set
			$sources[] = [
				'srcset' => $this->repoGroup->getLocalRepo()->getZoneUrl( 'thumb' ) . '/' . $avifThumbnailPath,
				'type' => 'image/avif',
				'width' => $width,
				'height' => $height,
			];
		} else {
			// process the thumbnail
			$this->jobQueueGroup->push( new AvifTransformJob( [
				'title' => $thumbnail->getFile()->getTitle(),
				'width' => $width,
				'height' => $height,
			] ) );
		}
	}
}
