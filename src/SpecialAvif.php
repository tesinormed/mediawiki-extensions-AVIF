<?php

namespace MediaWiki\Extension\AVIF;

use JobQueueGroup;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\FormSpecialPage;

class SpecialAvif extends FormSpecialPage {
	private JobQueueGroup $jobQueueGroup;

	public function __construct( JobQueueGroup $jobQueueGroup ) {
		parent::__construct( 'AVIF', restriction: 'aviftransform' );

		$this->jobQueueGroup = $jobQueueGroup;
	}

	/** @inheritDoc */
	protected function getDisplayFormat(): string {
		return 'ooui';
	}

	/** @inheritDoc */
	protected function getFormFields(): array {
		return [
			'files' => [
				'type' => 'textarea',
				'label-message' => 'special-avif-files',
				'required' => true,
				'rows' => 10,
			]
		];
	}

	/** @inheritDoc */
	public function onSubmit( array $data ): bool {
		$output = $this->getOutput();
		$output->addWikiTextAsInterface( 'Successfully queued:' );

		$result = '';
		foreach ( explode( "\n", $data['files'] ) as $file ) {
			$this->jobQueueGroup->lazyPush( new AvifTransformJob( [
				'namespace' => NS_FILE,
				'title' => str_replace( ' ', '_', $file ),
			] ) );
			$result .= "*[[:File:$file|$file]]\n";
		}
		$output->addWikiTextAsInterface( $result );

		return true;
	}

	/** @inheritDoc */
	public function getDescription(): Message {
		return $this->msg( 'special-avif' );
	}

	/** @inheritDoc */
	protected function getGroupName(): string {
		return 'media';
	}
}
