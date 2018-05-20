<?php

/**
	@file
	@brief Implements [[Special:CreatePage]].

	We can link to Special:CreatePage when user asks "Where do I create a new page?".

	Note: this is a modern port of "Uniwiki CreatePage" extension (obsolete in 2012).
	Please see the original extension for authors.
*/

class SpecialCreatePage extends FormSpecialPage {
	public function __construct() {
		parent::__construct( 'CreatePage', 'createpage' );
	}

	protected function getFormFields() {
		return [
			'Title' => [
				'id' => 'mw-input-wptitle',
				'type' => 'text',
				'label-message' => 'createpage-instructions'
			]
		];
	}

	protected function alterForm( HTMLForm $form ) {
		$form->setWrapperLegendMsg( 'createpage' );
	}

	protected function getEditURL( Title $title ) {
		global $wgCreatePageUwUseVE;
		if ( $wgCreatePageUwUseVE ) {
			return $title->getLocalURL( [
				'veaction' => 'edit'
			] );
		}

		return $title->getEditURL();
	}

	public function onSubmit( array $params ) {
		$out = $this->getOutput();

		$name = $params['Title'];
		if ( !$name ) {
			$out->redirect( $this->getTitle()->getFullURL() );
			return Status::newGood();
		}

		$title = Title::newFromText( $name );
		if ( !$title ) {
			return Status::newFatal( 'badtitletext' );
		}

		if ( $title->exists() ) {
			$out->addWikiMsg( 'createpage-titleexists', $title->getFullText() );
			$out->addHTML( Xml::tags( 'a', [
				'href' => $this->getEditURL( $title )
			], $out->msg( 'createpage-editexisting' )->escaped() ) );

			$out->addHTML( Xml::element('br') );
			$out->addHTML( Linker::linkKnown(
				$this->getTitle(),
				$out->msg( 'createpage-tryagain' )->escaped()
			) );

			return Status::newGood();
		}

		$out->redirect( $this->getEditURL( $title ) );
		return Status::newGood();
	}

	protected function getDisplayFormat() {
		return 'ooui';
	}
}
