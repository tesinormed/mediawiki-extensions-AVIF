<?php

namespace MediaWiki\Extension\AVIF\Hook;

use JobQueueGroup;
use MediaWiki\Extension\AVIF\Job\AvifTransformJob;
use MediaWiki\Hook\BitmapHandlerTransformHook;

class BitmapHooks implements BitmapHandlerTransformHook {
	private JobQueueGroup $jobQueueGroup;

	public function __construct( JobQueueGroup $jobQueueGroup ) {
		$this->jobQueueGroup = $jobQueueGroup;
	}

	/** @inheritDoc */
	public function onBitmapHandlerTransform( $handler, $image, &$scalerParams, &$mto ): void {
		// make sure the image is supported to be transformed
		if ( !in_array(
			needle: $image->getMimeType(),
			haystack: AvifTransformJob::SUPPORTED_MIME_TYPES,
			strict: true
		) ) {
			return;
		}

		// run the job
		$this->jobQueueGroup->push( new AvifTransformJob( [
			'title' => $image->getTitle(),
			'width' => $scalerParams['physicalWidth'],
			'height' => $scalerParams['physicalHeight'],
		] ) );
	}
}
