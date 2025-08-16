<?php

namespace MediaWiki\Extension\AVIF;

use JobQueueGroup;
use MediaWiki\JobQueue\JobFactory;
use MediaWiki\JobQueue\JobQueueGroupFactory;
use MediaWiki\Message\Message;
use MediaWiki\SpecialPage\FormSpecialPage;

class SpecialAvif extends FormSpecialPage {
	private readonly JobQueueGroup $jobQueueGroup;

	public function __construct(
		JobQueueGroupFactory $jobQueueGroupFactory,
		private readonly JobFactory $jobFactory
	) {
		parent::__construct( 'AVIF', restriction: 'aviftransform' );

		$this->jobQueueGroup = $jobQueueGroupFactory->makeJobQueueGroup();
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
			$this->jobQueueGroup->push( $this->jobFactory->newJob(
				AvifTransformJob::COMMAND,
				[ 'filename' => $file ]
			) );
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
